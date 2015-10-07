<?php

/**
 * Description of artists
 *
 * @author Hemant Mann
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use LastFm\Src\Geo as Geo;
use LastFm\Src\Artist as Artst;
use LastFm\Src\Track as Trck;
use Framework\ArrayMethods as ArrayMethods;
use LastFm\Src\Util as Util;

class Tracks extends Admin {
	
	public function top() {
		$view = $this->getActionView();
		$session = Registry::get("session");

		// if (!$session->get("country")) {
  //           $ip = $_SERVER['REMOTE_ADDR'];
  //           $country = $this->getCountry($ip);
  //           $session->set("country", $country);
  //       }
		if (!$session->get('Tracks\Top:$tracks')) {
			try {
				$topTracks = Geo::getTopTracks("india");

				$tracks = array();
				foreach ($topTracks as $track) {
					$tracks[] = array(
						"name" => $track->getName(),
						"mbid" => $track->getMbid(),
						"image" => $track->getImage(4),
						"artist" => $track->getArtist()->getName()
					);
				}
				$tracks = ArrayMethods::toObject($tracks);

				$session->set('Tracks\Top:$tracks', $tracks);

			} catch (\Exception $e) {
				self::redirect("/404");
			}	
		}

		$view->set("tracks", $session->get('Tracks\Top:$tracks'));
		$view->set("count", array(1,2,3,4,5));
	}

	public function view($track, $artist) {
		$view = $this->getActionView();
		$session = Registry::get("session");

		if (empty($artist) || empty($track)) {
		    self::redirect("/404");
		}

		if ($session->get('Tracks\View:track') != $track || $session->get('Tracks\View:artist') != $artist) {
			try {
				$track = Trck::getInfo($artist, $track, $mbid);

				// $session->set('Tracks\View:track', $track);
				// $session->set('Tracks\View:artist', $artist);

				/*** Track Info ***/
				$t = array();
				$t["name"] = $track->getName();
				$t["duration"] = $track->getDuration();
				$t["mbid"] = $track->getMbid();
				$t["artist"] = $track->getArtist()->getName();
				$t["artistMbid"] = $track->getArtist()->getMbid();
				$t["playCount"] = $track->getPlayCount();
				$t["wiki"] = $track->getWiki();

				/*** Track - Album ***/
				$album = $track->getAlbum();
				$t["album"] = Util::toString($album["title"]);
				$t["image"] = Util::toString($album["image"][0]);

				/*** Track - Tags ***/
				$tags = $track->getTrackTopTags();
				foreach ($tags as $tag) {
				    $t["tags"][] = array(
				        "name" => $tag->getName()
				    );
				}

				/*** Track - Artist => TopTracks ***/
				$topTracks = Artst::getTopTracks($t["artist"]);
				$tracks = array();
				foreach ($topTracks as $track) {
				    $tracks[] = array(
				        "name" => $track->getName(),
				        "playCount" => $track->getPlayCount(),
				        "mbid" => $track->getMbid()
				    );
				}
				$tracks = ArrayMethods::toObject($tracks);
				$t = ArrayMethods::toObject($t);

				// Also find similar tracks
				$similarTracks = Trck::getSimilar(null, null, $t->mbid);
				$similar = array();
				foreach ($similarTracks as $track) {
				    $similar[] = array(
				        "name" => $track->getName(),
				        "mbid" => $track->getMbid(),
				        "playCount" => $track->getPlayCount(),
				        "artist" => $track->getArtist()->getName(),
				        "thumbnail" => $track->getImage(2)
				    );
				}
				$similar = ArrayMethods::toObject($similar);
				// echo "<pre>". print_r($t, true). "</pre>";
				// var_dump($tracks);
				// var_dump($similar);
				// $session->set('Tracks\View:$trackInfo', $t);
				// $session->set('Tracks\View:$topTracks', $tracks);
				// $session->set('Tracks\View:$similarTracks', $similar);
			} catch (\Exception $e) {
				// $session->erase('Tracks\View:track');
				// $session->erase('Tracks\View:artist');
				// $session->erase('Tracks\View:$trackInfo');
				// $session->erase('Tracks\View:$topTracks');
				// $session->erase('Tracks\View:$similarTracks');
				// self::redirect("/404");
				var_dump($e);
			}
		}
		
		// $view->set('Track', $session->get('Tracks\View:$trackInfo'));
		// $view->set('Tracks', $session->get('Tracks\View:$topTracks'));
		// $view->set('similar', $session->get('Tracks\View:$similarTracks'));

		$view->set('track', $t);
		$view->set('tracks', $tracks);
		$view->set('similar', $similar);
	}
}