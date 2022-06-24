<?php

namespace IpswichJAFFARunningClubAPI\V2\GrandPrix;

require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/BaseController.php';
require_once IPSWICH_JAFFA_API_PLUGIN_PATH . 'V2/IRoute.php';
require_once 'GrandPrixDataAccess.php';

use IpswichJAFFARunningClubAPI\V2\BaseController as BaseController;
use IpswichJAFFARunningClubAPI\V2\IRoute as IRoute;

class GrandPrixController extends BaseController implements IRoute
{
	public function __construct(string $route, $db)
	{
		parent::__construct($route, new GrandPrixDataAccess($db));
	}

	public function registerRoutes()
	{
		register_rest_route( $this->route, '/results/grandPrix/(?P<year>[\d]{4})/(?P<sexId>[\d]+)', array(
			'methods'             => \WP_REST_Server::READABLE,				
			'callback'            => array( $this, 'getGrandPrixPoints' ),
			'args'                => array(
				'sexId'           => array(
					'required'          => true,						
					'validate_callback' => array( $this, 'isValidId' )
					)
				),
				'year'           => array(
					'required'          => true
				)
		) );
	}

	// Group data in structure:
	// {
	// "5": {
	// "id": "5",
	// "name": "Alan Jackson",
	// "dateOfBirth": "1980-01-02",
	// "races": [
	// {
	// "id": "954",
	// "points": "85"
	// },
	// {
	// "id": "1512",
	// "points": "79"
	// },
	// {
	// "id": "729",
	// "points": "90"
	// }
	// ],
	// "totalPoints": 254
	// },
	// "9": {
	// "id": "9",
	// "name": "Alistair Dick",
	// "races": [
	// {
	// "id": "954",
	// "points": "88"
	// },
	// {
	// "id": "549",
	// "points": "96"
	// }
	// ],
	// "totalPoints": 184
	// }
	public function getGrandPrixPoints(\WP_REST_Request $request)
	{
		$response = $this->dataAccess->getGrandPrixPoints($request['year'], $request['sexId']);

		// Calculate GP points
		// Handicap - base on position
		// Ekiden - base on time for each race distance
		// Others - base on time then position for event

		// Group data in to events
		$events = array();
		$races = array();
		$results = array();
		foreach ($response as $item) {
			$eventId = $item->eventId;

			if ($eventId == 203) {
				$resultSetId = $eventId + '_' + $item->distanceId; // Change resultSetId to be eventId + distanceId to give a unique grouping.
			} else {
				$resultSetId = $eventId;
			}

			if (!array_key_exists($resultSetId, $events)) {
				if ($eventId == 203) {
					$sortOrder = 'RESULT';
				} else if ($eventId == 89) {
					$sortOrder = 'POSITION';
				} else if ($item->result != '00:00:00' && $item->result != '') {
					$sortOrder = 'RESULT';
				} else {
					$sortOrder = 'POSITION';
				}

				$events[$resultSetId] = array("id" => $eventId, "name" => $item->eventName, "sortOrder" => $sortOrder, "results" => array());
			}

			$events[$resultSetId]['results'][] = $item;

			$runnerId = $item->runnerId;
			if (!array_key_exists($runnerId, $results)) {
				$gpCategory = $this->getGrandPrixCategory($item->dateOfBirth, $request['year']);
				$results[$runnerId] = array("id" => $runnerId, "name" => $item->name, "categoryCode" => $gpCategory, "races" => array());
			}

			$raceId = $item->raceId;
			if (!in_array($raceId, $races)) {
				$races[] = $raceId;
			}
		}

		$events = $this->removeDuplicateEkidenRunnerResults($events);

		foreach ($events as $key => $event) {
			if ($event['sortOrder'] == 'POSITION') {
				uasort($event['results'], array($this, 'compareGrandPrixEventByPosition'));
			} else {
				uasort($event['results'], array($this, 'compareGrandPrixEventByResult'));
			}
			// Re-index array.
			$events[$key]['results'] = array_values($event['results']);
		}

		foreach ($events as $event) {
			$points = 100;

			foreach ($event['results'] as $result) {
				if (array_key_exists($result->runnerId, $results)) {
					$results[$result->runnerId]['races'][] = array("id" => $result->raceId, "points" => $points);
					$results[$result->runnerId]['totalPoints'] += $points;
				}
				$points--;
			}
		}

		// Get race details
		$raceDetails = $this->dataAccess->getRaceDetails($races);

		foreach ($results as $runner) {
			$results[$runner['id']]['best8Score'] = $this->getGrandPrixBest8Score($runner['races']);
		}

		$getGrandPrixPointsResponse = array(
			"races" => $raceDetails,
			"results" => array_values($results)
		);

		return rest_ensure_response($getGrandPrixPointsResponse);
	}

	private function getGrandPrixCategory($dateOfBirth, int $year)
	{
		//http://stackoverflow.com/questions/3776682/php-calculate-age		

		$dob = new \DateTime($dateOfBirth);
		$gpDate = new \DateTime("$year-04-01");

		$diff = $dob->diff($gpDate);

		$category = "V60";
		
		if ($diff->y < 40) {
			$category = "Open";
		} else if ($diff->y < 50) {
			$category = "V40";
		} else if ($diff->y < 60) {
			$category = "V50";
		}

		return $category;
	}

	private function getGrandPrixBest8Score($races)
	{
		uasort($races, array($this, 'compareGrandPrixRaces'));

		// Get best 8 scores 
		$best8Score = 0;

		if (count($races) < 8) {
			return 0;
		}

		$count = 1;
		foreach ($races as $race) {
			$best8Score += $race['points'];
			if ($count == 8) {
				break;
			}
			$count++;
		}

		return $best8Score;
	}

	private function removeDuplicateEkidenRunnerResults($events)
	{
		foreach ($events as $key => $event) {
			if ($event["id"] == 203) {
				$events[$key]["results"] = $this->uniqueMultidimArray($event["results"]);
			}
		}

		return $events;
	}

	// From http://php.net/manual/en/function.array-unique.php
	private function uniqueMultidimArray($array)
	{
		$temp_array = array();
		$i = 0;
		$key_array = array();

		foreach ($array as $val) {
			if (!in_array($val->runnerId, $key_array)) {
				$key_array[$i] = $val->runnerId;
				$temp_array[$i] = $val;
				$i++;
			}
		}

		return $temp_array;
	}

	private function compareGrandPrixEventByPosition($a, $b)
	{
		if ($a->position == $b->position) {
			return 0;
		}

		return ($a->position > $b->position) ? 1 : -1;
	}

	private function compareGrandPrixEventByResult($a, $b)
	{
		if ($a->result == $b->result) {
			return 0;
		}

		// Add 00: prefix to compare hh:mm:ss to mm:ss
		$aFullTime = $a->result;
		if (strlen($a->result) < 8) {
			$aFullTime = '00:' . $a->result;
		}

		$bFullTime = $b->result;
		if (strlen($b->result) < 8) {
			$bFullTime = '00:' . $b->result;
		}

		return ($aFullTime > $bFullTime) ? 1 : -1;
	}

	private function compareGrandPrixRaces($a, $b)
	{
		if ($a['points'] == $b['points']) {
			return 0;
		}

		return ($a['points'] > $b['points']) ? -1 : 1;
	}
}
