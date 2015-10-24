<?php

/**
 * The Home Controller
 *
 * @author Hemant Mann
 */
use Shared\Controller as Controller;
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
use Framework\ArrayMethods as ArrayMethods;

// LastFm Library
use LastFm\Src\Track as Trck;
use LastFm\Src\Geo as Geo;
use LastFm\Src\Artist as Artst;
use LastFm\Src\Tag as Tag;

use WebBot\lib\WebBot\Bot as Bot;

class Home extends Controller {

    public function index($page = 1) {
        $view = $this->getActionView();
        if (is_numeric($page) === FALSE) { self::redirect("/404"); }
        
        $page = (int) $page; $pageMax = 50;
        if ($page > $pageMax) {
            $page = $pageMax;
        }
        $session = Registry::get("session");

        if (!$session->get("country")) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $country = $this->getCountry($ip);
            $session->set("country", $country);
        }

        if (!$session->get('Home\Index:$topArtists') || $session->get('Home\Index:page') != $page) {
            $topArtists = Geo::getTopArtists($session->get("country"), $page);
            $artists = array();
            $i = 1;
            foreach ($topArtists as $art) {
                $artists[] = array(
                    "mbid" => $art->getMbid(),
                    "name" => $art->getName(),
                    "image" => $art->getImage(4)
                );
                ++$i;

                if ($i > 30) break;
            }
            $artists = ArrayMethods::toObject($artists);    

            $session->set('Home\Index:page', $page);
            $session->set('Home\Index:$topArtists', $artists);
        }

        $view->set("count", array(1,2,3,4,5));
        $view->set("pagination", $this->setPagination("/index/", $page, 1, $pageMax));
        $view->set("artists", $session->get('Home\Index:$topArtists'));
    }

    public function genres($name = null, $page = 1) {
        $view = $this->getActionView();
        if (is_numeric($page) === FALSE) { self::redirect("/404"); }
        
        $page = (int) $page; $pageMax = 5;
        if ($page > $pageMax) {
            $page = $pageMax;
        }
        $session = Registry::get("session");

        if (!$name) {
            $name = "acoustic";
        }

        // Get top Tags for displaying - currently not working (Last Fm Fault)        
        if (!$session->get('Home\Genres:$topTags')) {
            $topTags = Genre::all(array(), array("title"));
            // $topTags = Tag::getTopTags();
            $tags = array();
            foreach ($topTags as $t) {
                $tags[] = array(
                    // "name" => $t->getName()
                    "name" => $t->title
                );
            }
            $tags = ArrayMethods::toObject($tags);
            $session->set('Home\Genres:$topTags', $tags);
        }
        
        // Display songs for 'Genre' if given
        $tracks = array();
        if ($session->get('Home\Genre:$set') != $name || $session->get('Home\Genre:page') != $page) {
            $topTracks = Tag::getTopTracks($name, $page);

            foreach ($topTracks as $t) {
                $tracks[] = array(
                    "name" => $t->getName(),
                    "mbid" => $t->getMbid(),
                    "artist" => $t->getArtist()->getName(),
                    "artistId" => $t->getArtist()->getMbid(),
                    "image" => $t->getImage(2)
                );
            }
            $tracks = ArrayMethods::toObject($tracks);
            $session->set('Home\Genre:page', $page);
            $session->set('Home\Genre:$set', $name);
            $session->set('Home\Genre:$topTracks', $tracks);
        }

        $view->set("pagination", $this->setPagination("/genres/{$name}/", $page));
        $view->set("genre",  ucfirst($session->get('Home\Genre:$set')));
        $view->set("tags", $session->get('Home\Genres:$topTags'));
        $view->set("tracks", $session->get('Home\Genre:$topTracks'));
        
    }

    public function listen($artistName) {
        self::redirect("/404");
    }

    public function videos() {
    	$view = $this->getActionView();
    	$results = null; $text = '';

        $q = 'latest songs';
        $results = $this->searchYoutube($q);

        // @todo add error checking in videos page
        if (!is_object($results) && $results == "Error") {
            $view->set("error", $results);
        } else {
            $view->set("results", $results);    
        }
    }

    /**
     * Finds the youtube video id of a given song
     */
    public function findTrack() {
        $this->noview();
        $return = Registry::get("session")->get('Home\findLyrics:$return');
        if (RequestMethods::post("action") == "findTrack" || $return) {
            $artist = RequestMethods::post("artist");
            $track = RequestMethods::post("track");

            $videoId = null; $error = null;
            $q = $track. " ". $artist;

            $videoId = $this->searchYoutube($q, 1, true);
            if ($videoId != "Error") {
                if ($return) {
                    return $videoId;
                }
            }
            echo $videoId;

        } else {
            self::redirect("/404");
        }
    }

    public function findLyrics() {
        $this->noview();
        if (RequestMethods::post("action") == "findLyrics") {
            $artist = RequestMethods::post("artist");
            $track = RequestMethods::post("track");
            $mbid = RequestMethods::post("mbid");

            if ($mbid) {
                $where = array("mbid = ?" => $mbid, "live = ?" => true);
            } else {
                $where = array("artist = ?" => $artist, "track = ?" => $track, "live = ?" => true);
            }
            $strack = SavedTrack::first($where, array("id", "yid"));
            if (!$strack) {
                Registry::get("session")->set('Home\findLyrics:$return', true);

                if (RequestMethods::post("yid")) {
                    $id = RequestMethods::post("yid");    
                } else {
                    $id = $this->findTrack();    
                }
                $strack = new SavedTrack(array(
                    "track" => $track,
                    "artist" => $artist,
                    "mbid" => $mbid,
                    "yid" => $id
                ));
                $strack->save();
            }

            $lyric = Lyric::first(array("strack_id = ?" => $strack->id, "live = ?" => true));
            Registry::get("session")->erase('Home\findLyrics:$return');
            if ($lyric) {
                echo $lyric->lyrics;
                return;
            }
            
            $shared = new Shared\Lyrics(array('library' => 'LyricsnMusic', 'track' => $track, 'artist' => $artist));
            $api = $shared->findLyrics();
            
            if (is_object($api)) {
                $lyric = new Lyric(array(
                    "lyrics" => $api->getLyrics(),
                    "strack_id" => $strack->id
                ));
                $lyric->save();

                echo $api->getLyrics();
            } else {
                echo "Could not find the lyrics";
            }
        } else {
            self::redirect("/404");
        }
    }

    /**
     * Searches for music from the supplied query on last.fm | Youtube
     */
    public function searchMusic($page = 1) {
        $view = $this->getActionView();
        if (is_numeric($page) === FALSE) { self::redirect("/404"); }

        $page = (int) $page; $pageMax = 7;
        if ($page > $pageMax) {
            $page = $pageMax;
        }
        $session = Registry::get("session");
        $stored = $session->get('Home\searchMusic:$vars');

        if (RequestMethods::post("action") == "searchMusic") {
            $type = RequestMethods::post("type");
            $q = RequestMethods::post("q");

            if ($stored && ($stored['q'] !== $q || $stored['type'] !== $type)) {
                $this->setResults($type, $q);
                unset($stored);
            }
        } elseif (!$stored || !$stored['results']) {
            self::redirect("/");
        }

        // if ($stored && $stored['type'] === 'song') {
        //     $this->setResults($stored['type'], $stored['q'], $page);
        // } elseif ($stored['type'] === 'video') {
        //     // $results = $this->sear
        // }

        $stored = $session->get('Home\searchMusic:$vars');
        if ($stored['error']) {
            $view->set('error', $stored['error']);
        } else {
            $view->set('type', $stored['type']);
            $view->set('results', $stored['results']);
        }

        $view->set('pagination', $this->setPagination('/home/searchMusic/', $page, 1, $pageMax));
    }

    protected function setResults($type, $q, $page = 1, $limit = 50) {
        $session = Registry::get("session");
        $get = $session->get('Home\searchMusic:$vars');

        if ($get && $page == $get['page'] && $type === $get['type'] && $q === $type['q']) {
            return;
        }

        switch ($type) {
            case 'song':
                $results = $this->searchLastFm($q, $page);
                break;
            
            case 'video':
                $results = $this->searchYoutube($q, $limit);
                break;
        }

        if (!is_object($results) || $results == "Error") {
            $session->set('Home\searchMusic:$vars', array('error' => 'Error'));
        } else {
            $session->set('Home\searchMusic:$vars', array('q' => $q, 'type' => $type, 'results' => $results, 'page' => $page));
        }
    }

    /**
     * Searches for country name based on client's IP Address
     */
    protected function getCountry($ip) {
        $ip = ($ip == '127.0.0.1') ? '203.122.5.25' : $ip;

        $url = 'http://www.geoplugin.net/json.gp?ip='.$ip;
        $bot = new Bot(array('country' => $url));
        $bot->execute();
        $document = array_shift($bot->getDocuments());
        $data = json_decode($document->getHttpResponse()->getBody());

        return $data->geoplugin_countryName;
    }

    /**
     * Searches last.fm for the given song
     */
    protected function searchLastFm($q, $page = 1, $limit = 30) {
    	try {
    		$tracks = @Trck::search($q, null, $limit, $page)->getResults();    // Will return an array of objects
    		// echo "<pre>". print_r($tracks, true). "</pre>";
    		
    		$results = array();
    		foreach ($tracks as $t) {
    		    $results[] = array(
    		    	"name" => $t->getName(),
    		        "artist" => $t->getArtist(),
    		        "album" => $t->getAlbum(),
    		        "wiki" => $t->getWiki(),
    		        "mbid" => $t->getMbid(),
    		        "image" => $t->getImage(4)
    		    );
    		}
    		$results = ArrayMethods::toObject($results);
    		
    		return $results;	
    	} catch (\Exception $e) {
    		return "Error";
    	}
        
    }

    /**
     * Searches youtube for a given query
     * @return object|string
     */
    protected function searchYoutube($q, $max = 15, $returnId = false) {
        $youtube = Registry::get("youtube");
        
        try {
            $searchResponse = $youtube->search->listSearch('id,snippet', array(
                'q' => $q,
                'maxResults' => $max,
                "type" => "video"
            ));

            // Add each result to the appropriate list, and then display the lists of
            // matching videos, channels, and playlists.
            $results = array();
            foreach ($searchResponse['items'] as $searchResult) {
                $thumbnail = $searchResult['snippet']['thumbnails']['medium']['url'];
                $title = $searchResult['snippet']['title'];
                $href = $searchResult['id']['videoId'];

                $results[] = array(
                    "img" => $thumbnail,
                    "title" => $title,
                    "videoId" => $href
                );
            }
            $results = ArrayMethods::toObject($results);
            return ($returnId) ? $href : $results;

        } catch (Google_Service_Exception $e) {
            return "Error";
        } catch (Google_Exception $e) {
            return "Error";
        }
    }
}
