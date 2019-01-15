<?php
/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists( 'Ipswich_JAFFA_Results_Data_Access' ) ) {	
		
	require_once plugin_dir_path( __FILE__ ) .'config.php';
	
	class Ipswich_JAFFA_Results_Data_Access {		

		private $jdb;

		public function __construct() {
			
			// Needs $this->dbh = mysql_connect( $this->dbhost, $this->dbuser, $this->dbpassword, false,65536 );
			$this->jdb = new wpdb(JAFFA_RESULTS_DB_USER, JAFFA_RESULTS_DB_PASSWORD, JAFFA_RESULTS_DB_NAME, DB_HOST);		
		    $this->jdb->show_errors();
		}
		
		 public function getDistances() {		
			 $sql = 'SELECT id, distance as text FROM distance';

			 $results = $this->jdb->get_results($sql, OBJECT);

			 if (!$results)	{			
				 return new WP_Error( 'ipswich_jaffa_api_getDistances',
						 'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			 }

			 return $results;
		 } // end function getDistances
		 
		 public function getCourseTypes() {

			$sql = 'SELECT id, description FROM `course_type` ORDER BY id ASC';

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getCourseTypes',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		 
		public function getEvents() {

			$sql = 'SELECT e.id, e.name, e.distance_id, e.website, d.distance, count( r.id ) AS count
					FROM `events` e
					LEFT JOIN `distance` d ON e.distance_id = d.id
					LEFT JOIN `results` r ON e.id = r.event_id
					GROUP BY e.id, e.name, e.distance_id, e.website, d.distance
					ORDER BY e.name';

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getEvents',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function insertEvent($event)
		{			
			$sql = $this->jdb->prepare('INSERT INTO events (`name`, `distance_id`, `website`) VALUES(%s, %d, %s);', $event['name'], $event['distanceId'], $event['website']);

			$result = $this->jdb->query($sql);

			if ($result)
			{
				return $this->getEvent($this->jdb->insert_id);
			}

			return new WP_Error( 'ipswich_jaffa_api_insertEvent',
						'Unknown error in inserting event in to the database', array( 'status' => 500 ) );
		}
		
		public function getRaces($eventId) {

			$sql = $this->jdb->prepare(
					'SELECT ra.id, e.id AS eventId, e.Name as name, ra.date, ra.description, ra.course_type_id AS courseTypeId, c.description AS courseType, ra.area, ra.county, ra.country_code AS countryCode, ra.conditions, ra.venue, d.distance, ra.grand_prix as isGrandPrixRace
					FROM `events` e
					LEFT JOIN `race` ra ON ra.event_id = e.id
					LEFT JOIN `distance` d ON ra.distance_id = d.id
					LEFT JOIN `course_type` c ON ra.course_type_id = c.id
					WHERE e.id = %d
					ORDER BY ra.date DESC', $eventId);

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getRaces',
						'Unknown error in reading races from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getRace($raceId) {

			$sql = $this->jdb->prepare(
					'SELECT ra.id, e.id AS eventId, e.Name as eventName, ra.description as description, ra.date, ra.course_type_id AS courseTypeId, c.description AS courseType, ra.area, ra.county, ra.country_code AS countryCode, ra.conditions, ra.venue, d.distance, ra.grand_prix as isGrandPrixRace
					FROM `events` e
					INNER JOIN `race` ra ON ra.event_id = e.id
					LEFT JOIN `distance` d ON ra.distance_id = d.id
					LEFT JOIN `course_type` c ON ra.course_type_id = c.id
					WHERE ra.id = %d', $raceId);

			$results = $this->jdb->get_row($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getRaces',
						'Unknown error in reading race from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function insertRace($race)
		{			
			$sql = $this->jdb->prepare('INSERT INTO `race`(`event_id`, `date`, `course_id`, `venue`, `description`, `conditions`, `distance_id`, `course_type_id`, `county`, `country_code`, `area`, `grand_prix`) VALUES(%d, %s, %d, %s, %s, %s, %d, %d, %s, %s, %s, %d)', $race['eventId'], $race['date'], $race['courseId'], $race['venue'], $race['description'], $race['conditions'], $race['distanceId'], $race['courseTypeId'], $race['county'], $race['countryCode'], $race['area'], $race['isGrandPrixRace']);

			$result = $this->jdb->query($sql);

			if ($result)
			{
				return $this->getRace($this->jdb->insert_id);
			}

			return new WP_Error( 'ipswich_jaffa_api_insertRace',
						'Unknown error in inserting race in to the database', array( 'status' => 500 ) );
		}
		
		public function insertCourse($course)
		{			
			$sql = $this->jdb->prepare('INSERT INTO course (`event_id`, `type_id`, `registered_distance`, `certified_accurate`, `course_number`, `area`, `county`, `country_code`) VALUES(%d, %d, %d, %d, %s, %s, %s);', $course['eventId'], $course['typeId'], $course['registeredDistance'], $course['certifiedAccurate'], $course['courseNumber'], $course['area'], $course['county'], $course['countryCode']);

			$result = $this->jdb->query($sql);

			if ($result)
			{
				return $this->getCourse($this->jdb->insert_id);
			}

			return new WP_Error( 'ipswich_jaffa_api_insertCourse',
						'Unknown error in inserting course in to the database', array( 'status' => 500 ) );
		}
		
		public function getCourse($courseId) {
			// Get updated event
			$sql = $this->jdb->prepare("SELECT e.name, c.id, c.event_id as 'eventId', c.type_id as 'typeId', c.registered_distance as 'registeredDistance', c.certified_accurate as 'certifiedAccurate', c.course_number as 'courseNumber', c.area, c.county, c.country_code  FROM `events` e LEFT OUTER JOIN `course` c ON e.id = c.event_id WHERE c.id = %d", $courseId);			

			$result = $this->jdb->get_row($sql, OBJECT);
			
			if ($result) return $result;
			
			return new WP_Error( 'ipswich_jaffa_api_getCourse',
						'Unknown error in getting the course from the database', array( 'status' => 500 ) );
		}
		
		public function getEvent($eventId) {
			// Get updated event
			$sql = $this->jdb->prepare("SELECT e.id, e.name, e.distance_id as 'distanceId', d.distance, e.website FROM `events` e LEFT JOIN `distance` d ON e.distance_id = d.id WHERE e.id = %d", $eventId);

			$result = $this->jdb->get_row($sql, OBJECT);
			
			if ($result) return $result;
			
			return new WP_Error( 'ipswich_jaffa_api_getEvent',
						'Unknown error in getting the event in to the database', array( 'status' => 500 ) );
		}

		public function updateEvent($eventId, $field, $value) {		

			// Only name and website may be changed.
			if ($field == 'name' || $field == 'website') 
			{
				$result = $this->jdb->update( 
					'events', 
					array( 
						$field => $value
					), 
					array( 'id' => $eventId ), 
					array( 
						'%s'
					), 
					array( '%d' ) 
				);

				if ($result)
				{
					// Get updated event
					return $this->getEvent($eventId);
				}
				
				return new WP_Error( 'ipswich_jaffa_api_updateEvent',
						'Unknown error in updating event in to the database'.$sql, array( 'status' => 500 ) );
			}

			return new WP_Error( 'ipswich_jaffa_api_updateEvent',
						'Field in event may not be updated', array( 'status' => 500 , 'Field' => $field, 'Value' => $value) );
		}
		
		public function updateRace($raceId, $field, $value) {		
			// Race date and distance can not be changed - affected PBs etc
			if ($field == 'event_id' || 
			    $field == 'description' || 
				$field == 'course_type_id' || 
				$field == 'course_id' || 
				$field == 'area' || 
				$field == 'county' ||
				$field == 'country_code' || 
				$field == 'venue' || 
				$field == 'conditions' || 
				$field == 'grand_prix') 
			{
				$result = $this->jdb->update( 
					'race', 
					array( 
						$field => $value
					), 
					array( 'id' => $raceId ), 
					array( 
						'%s'
					), 
					array( '%d' ) 
				);

				if ($result)
				{
					// Get updated race
					return $this->getRace($raceId);
				}
				
				return new WP_Error( 'ipswich_jaffa_api_updateRace',
						'Unknown error in updating event in to the database'.$sql, array( 'status' => 500 ) );
			}

			return new WP_Error( 'ipswich_jaffa_api_updateRace',
						'Field in event may not be updated', array( 'status' => 500 , 'Field' => $field, 'Value' => $value) );
		}
				
		public function updateRunner($runnerId, $field, $value) {		

			// Only name and website may be changed.
			if ($field == 'name' || $field == 'current_member') 
			{
				$result = $this->jdb->update( 
					'runners', 
					array( 
						$field => $value
					), 
					array( 'id' => $runnerId ), 
					array( 
						'%s'
					), 
					array( '%d' ) 
				);

				if ($result)
				{
					return $this->getRunner($runnerId);
				}
				
				return new WP_Error( 'ipswich_jaffa_api_updateRunner',
						'Unknown error in updating runner in to the database'.$sql, array( 'status' => 500 ) );
			}

			return new WP_Error( 'ipswich_jaffa_api_updateRunner',
						'Field in event may not be updated', array( 'status' => 500 , 'Field' => $field, 'Value' => $value) );
		}
		
		public function updateResult($resultId, $field, $value) {		

			// Only name and website may be changed.
			if ($field == 'info' || $field == 'position' || $field == "grandprix" || $field == "scoring_team") 
			{
				$result = $this->jdb->update( 
					'results', 
					array( 
						$field => $value
					), 
					array( 'id' => $resultId ), 
					array( 
						'%s'
					), 
					array( '%d' ) 
				);

				if ($result)
				{
					return $this->getResult($resultId);
				}
				
				return new WP_Error( 'ipswich_jaffa_api_updateResult',
						'Unknown error in updating result in to the database'.$sql, array( 'status' => 500 ) );
			} else if ($field == 'result') {
				// Update result, percentage grading and standard
				$existingResult = $this->getResult($resultId);
				$pb = 0;
				$seasonBest = 0;
				$standardType = 0;
		
				if ($this->isCertificatedCourseAndResult($existingResult->eventId, $existingResult->courseId, $value)) {
					$pb = $this->isPersonalBest($existingResult->eventId, $existingResult->runnerId, $value);
					
					$seasonBest = $this->isSeasonBest($existingResult->eventId, $existingResult->runnerId, $value);

					$standardType = $this->getStandardTypeId($existingResult->categoryId, $value, $existingResult->eventId);				
				}
				
				$success = $this->jdb->update( 
					'results', 
					array( 
						'result' => $value,
						'personal_best' => $pb,
						'season_best' => $seasonBest,
						'standard_type_id' => $standardType
					), 
					array( 'id' => $resultId ), 
					array( 
						'%s', 
						'%d',
						'%d',
						'%d'
					), 
					array( '%d' ) 
				);							

				if ($success)
				{
					$this->updateAgeGrading($resultId, $existingResult->eventId, $existingResult->runnerId, $existingResult->date);
				
					// If a PB query to see whether a new certificate is required.
					if ($pb == true)
					{
						$isNewStandard = $this->isNewStandard($resultId);

						if ($isNewStandard == true)
						{
							$this->saveStandardCertificate($resultId);
						}
					}
			
					return $this->getResult($resultId);
				}
				
				return new WP_Error( 'ipswich_jaffa_api_updateResult',
						'Unknown error in updating result in to the database', array( 'status' => 500 ) );
			}

			return new WP_Error( 'ipswich_jaffa_api_updateResult',
						'Field in result may not be updated', array( 'status' => 500 ) );
		}
		
		/// Both events must have the same distance Id for this to be successful.
		public function mergeEvents($fromEventId, $toEventId) {		
	
			// Distance cannot be updated - TODO check for results
			$sql = $this->jdb->prepare("UPDATE results r, events fe, events te SET r.event_id = te.id WHERE r.event_id = %d AND fe.id = %d AND te.id = %d AND fe.distance_id = te.distance_id", $fromEventId, $fromEventId, $toEventId);

			$result = $this->jdb->query($sql);

			if ($result)
			{
				// Delete old event
				return $this->deleteEvent($fromEventId, false);
			}

			return new WP_Error( 'ipswich_jaffa_api_mergeEvents',
						'Unknown error in merging events in the database', array( 'status' => 500 ) );
		}

		public function deleteEvent($eventId, $deleteResults) {		

			$sql = $this->jdb->prepare('SELECT COUNT(`id`) FROM results WHERE event_id = %d LIMIT 1;',$eventId);

			$exists = $this->jdb->get_var($sql); // $jdb->get_var returns a single value from the database. In this case 1 if the find term exists and 0 if it does not.

			if ($exists != 0) {
				if (empty($deleteResults)) {
					return new WP_Error( 'ipswich_jaffa_api_validation',
						'Event cannot be deleted; a number results are associated with this event. Delete the existing results for this event and try again.', array( 'status' => 500 ) );
				}
				
				// Delete all associated results
				$result = $this->deleteEventResults($eventId);				
				if ($result != true) return $result;
			}		

			$sql = $this->jdb->prepare('DELETE FROM events WHERE id = %d;', $eventId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new WP_Error( 'ipswich_jaffa_api_deleteEvent',
						'Unknown error in deleting event from the database', array( 'status' => 500 ) );			
			}	

			return $result;
		} // end function deleteEvent
		
		public function deleteCourse($courseId, $deleteResults) {		

			$sql = $this->jdb->prepare('SELECT COUNT(`id`) FROM results WHERE course_id = %d LIMIT 1;',$courseId);

			$exists = $this->jdb->get_var($sql); // $jdb->get_var returns a single value from the database. In this case 1 if the find term exists and 0 if it does not.

			if ($exists != 0) {
				if (empty($deleteResults)) {
					return new WP_Error( 'ipswich_jaffa_api_validation',
						'Course cannot be deleted; a number results are associated with this course. Delete the existing results for this event and course and try again.', array( 'status' => 500 ) );
				}
				
				// Delete all associated results TODO
				$result = $this->deleteCourseResults($courseId);				
				if ($result != true) return $result;
			}		

			$sql = $this->jdb->prepare('DELETE FROM course WHERE id = %d;', $courseId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new WP_Error( 'ipswich_jaffa_api_deleteCourse',
						'Unknown error in deleting course from the database', array( 'status' => 500 ) );			
			}	

			return $result;
		} // end function deleteCourse
	
		private function deleteCourseResults($courseId) {
			$sql = $this->jdb->prepare('DELETE FROM results WHERE course_id = %d;', $courseId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new WP_Error( 'ipswich_jaffa_api_deleteCourseResults',
						'Unknown error in deleting results from the database', array( 'status' => 500 ) );			
			}

			return true;
		}
		
		private function deleteEventResults($eventId) {
			$sql = $this->jdb->prepare('DELETE FROM results WHERE event_id = %d;', $eventId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new WP_Error( 'ipswich_jaffa_api_deleteEventResults',
						'Unknown error in deleting results from the database', array( 'status' => 500 ) );			
			}

			return true;
		}
		
		public function deleteResult($resultId) {
			$sql = $this->jdb->prepare('DELETE FROM results WHERE id = %d;', $resultId);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new WP_Error( 'ipswich_jaffa_api_deleteResult',
						'Unknown error in deleting results from the database', array( 'status' => 500 ) );			
			}

			return true;
		}
		
		public function getRunners() {
			$sql = "SELECT r.id, r.name, r.sex_id as 'sexId', r.dob as 'dateOfBirth', r.current_member as 'isCurrentMember', s.sex FROM `runners` r, `sex` s WHERE r.sex_id = s.id ORDER BY r.name";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getRunners',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getRunner($runnerId) {
			$sql = $this->jdb->prepare("SELECT r.id, r.name, r.sex_id as 'sexId', r.dob as 'dateOfBirth', r.current_member as 'isCurrentMember', s.sex FROM `runners` r, `sex` s WHERE r.sex_id = s.id AND r.id = %d", $runnerId);

			$results = $this->jdb->get_row($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getRunner',
						'Unknown error in reading runner from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function insertRunner($runner) {
		
			$sql = $this->jdb->prepare('INSERT INTO runners (`membership_no`, `name`, `dob`, `sex_id`, `current_member`, `club_id`) VALUES(0, %s, %s, %d, %d, 439);', $runner['name'], $runner['dateOfBirth'], $runner['sexId'], $runner['isCurrentMember']);
 
			$result = $this->jdb->query($sql);

			if ($result) {
				return $this->getRunner($this->jdb->insert_id);
			}

			return new WP_Error( 'ipswich_jaffa_api_insertRunner',
						'Unknown error in inserting runner in to the database', array( 'status' => 500 ) );
		} // end function addRunner
		
		public function deleteRunner($id) {
		
			// Check whether their are any results for this runner already.
			$sql = $this->jdb->prepare('SELECT COUNT(`id`) FROM results WHERE runner_id = %d LIMIT 1;', $id);

			$exists = $this->jdb->get_var($sql);

			if ($exists != 0) {
				// Runners cannot be deleted; a number results are associated with this runner. Delete these results first and then try again.

				return new WP_Error( 'ipswich_jaffa_api_validation',
							'Runner cannot be deleted; a number results are associated with this runner. Delete the existing results for this runner and try again.', array( 'status' => 500 ) );
			}
			
			$sql = $this->jdb->prepare('DELETE FROM runners WHERE id = %d;', $id);

			$result = $this->jdb->query($sql);

			if (!$result) {			
				return new WP_Error( 'ipswich_jaffa_api_deleteRunner',
						'Unknown error in deleting runner from the database', array( 'status' => 500 ) );			
			}	

			return $result;
		} // end function deleteRunner
	
		public function getGenders(){
		
			$sql = 'SELECT * FROM sex ORDER BY sex';

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getGenders',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		} // end function getGenders
		
		public function getCourses($eventId) {
			$sql = $this->jdb->prepare("SELECT e.name, c.id, c.event_id as 'eventId', c.type_id as 'typeId', c.registered_distance as 'registeredDistance', c.certified_accurate as 'certifiedAccurate', c.course_number as 'courseNumber', c.area, c.county, c.country_code  FROM `events` e LEFT OUTER JOIN `course` c ON e.id = c.event_id WHERE e.id = %d ORDER BY c.course_number DESC", $eventId);

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getCourses',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function insertResult($result) {
	
			$categoryId = $this->getCategoryId($result['runnerId'], $result['date']);
			$pb = 0;
			$seasonBest = 0;
			$standardType = 0;
			
			if ($this->isCertificatedCourseAndResult($result['eventId'], $result['courseId'], $result['time'])) {
				$pb = $this->isPersonalBest($result['eventId'], $result['runnerId'], $result['time']);
				
				$seasonBest = $this->isSeasonBest($result['eventId'], $result['runnerId'], $result['time']);

				$standardType = $this->getStandardTypeId($categoryId, $result['time'], $result['eventId']);				
			}
			
			$sql = $this->jdb->prepare('INSERT INTO results (`result`, `event_id`, `course_id`, `racedate`, `info`, `runner_id`, `club_id`, `position`, `category_id`, `personal_best`, `season_best`, `standard_type_id`, `grandprix`, `scoring_team`, `race_id`) VALUES(%s, %d, %d, %s, %s, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d);', $result['time'], $result['eventId'], $result['courseId'], $result['date'], $result['info'], $result['runnerId'], 439, $result['position'], $categoryId, $pb, $seasonBest, $standardType, $result['isGrandPrixResult'], $result['team'], $result['raceId']);
			
			$success = $this->jdb->query($sql);

			if (!$success) {
				return new WP_Error( 'ipswich_jaffa_api_insertResult',
					'Unknown error in inserting results in to the database : ', array( 'status' => 500 ) );	
			}

			// Get the ID of the inserted event
			$resultId = $this->jdb->insert_id;
						
			$response = $this->updateAgeGrading($resultId, $result['eventId'], $result['runnerId'], $result['date']);

			if ($response != true)
				return $response;
			
			// If a PB query to see whether a new certificate is required.
			if ($pb == true)
			{
				$isNewStandard = $this->isNewStandard($resultId);

				if ($isNewStandard == true)
				{
					$this->saveStandardCertificate($resultId);
				}
			}					

			return $this->getResult($resultId);			
		}
	
		public function getResults($eventId, $fromDate, $toDate, $numberOfResults) {
			
			if (empty($eventId)) {
				$whereEvent = '';				
			} else {
				$whereEvent = ' AND r.event_id = '.$eventId;
			}
			
			if (empty($fromDate)) {
				$whereFrom = '';				
			} else {
				$whereFrom = " AND r.racedate >= '$fromDate'";
			}
			
			if (empty($toDate)) {
				$whereTo = '';				
			} else {
				$whereTo = " AND r.racedate <= '$toDate'";
			}
			
			$limit = abs(intval($numberOfResults));
			
			if ($limit <= 0)
				$limit = 100;

			$sql = "SELECT r.id, r.event_id as 'eventId', r.runner_id as 'runnerId', r.position, r.racedate as 'date', r.result as 'time', r.info, r.event_division_id as 'eventDivisionId', r.standard_type_id as 'standardTypeId', r.category_id as 'categoryId', r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', r.grandprix as 'isGrandPrixResult', r.scoring_team as 'team', r.percentage_grading as 'percentageGrading', r.course_id as 'courseId', p.name as 'runnerName', e.name as 'eventName', d.distance FROM results r, runners p, events e LEFT JOIN distance d ON e.distance_id = d.id WHERE r.runner_id = p.id AND r.event_id = e.id $whereEvent $whereFrom $whereTo ORDER BY r.racedate DESC, r.event_id, r.position ASC, r.result ASC LIMIT $limit";
							
			$results = $this->jdb->get_results($sql, OBJECT);

			if ($this->jdb->num_rows == 0)
				return null;
			
			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getResults',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getRaceResults($raceId) {
			
			$sql = "SELECT r.id, r.runner_id as 'runnerId', r.position, r.result as 'time', r.info, s.name as standardType, c.code as categoryCode, r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', r.scoring_team as 'team', r.percentage_grading as 'percentageGrading', r.course_id as 'courseId', p.name as 'runnerName', r.race_id as raceId
			FROM results r, 
				runners p,
				standard_type s,
				category c
			WHERE r.runner_id = p.id 
			AND r.race_id = $raceId 
			AND c.id = r.category_id
			AND s.id = r.standard_type_id
			ORDER BY r.position ASC, r.result ASC";
							
			$results = $this->jdb->get_results($sql, OBJECT);

			if ($this->jdb->num_rows == 0)
				return null;
			
			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getRaceResults',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
				
		public function getResult($resultId) {
						
			$sql = "SELECT r.id, r.event_id as 'eventId', r.runner_id as 'runnerId', r.position, r.racedate as 'date', r.result as 'time', r.info, r.event_division_id as 'eventDivisionId', r.standard_type_id as 'standardTypeId', r.category_id as 'categoryId', r.personal_best as 'isPersonalBest', r.season_best as 'isSeasonBest', r.grandprix as 'isGrandPrixResult', r.scoring_team as 'team', r.percentage_grading as 'percentageGrading', r.course_id as 'courseId', p.name as 'runnerName', e.name as 'eventName', d.distance FROM results r, runners p, events e LEFT JOIN distance d ON e.distance_id = d.id WHERE r.runner_id = p.id AND r.event_id = e.id AND r.id = $resultId";
							
			$results = $this->jdb->get_row($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getResult',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function insertRunnerOfTheMonthWinners($runnerId, $category, $month, $year) {
			$sql = $this->jdb->prepare("insert into runner_of_the_month_winners set runner_id=%d, category='%s', month=%d, year=%d",
                        $runnerId, $category, $month, $year);			
 
			$result = $this->jdb->query($sql);

			if ($result) {
				return true;
			}

			return new WP_Error( 'ipswich_jaffa_api_insertRunnerOfTheMonthWinners',
						'Unknown error in inserting runner in to the database', array( 'status' => 500 ) );
		}
		
		public function getResultsByYearAndCounty() {
			$sql = "SELECT YEAR(r.racedate) as year, c.county, count(r.id) as count FROM `course` c INNER join results r on c.id = r.course_id WHERE c.county IS NOT NULL GROUP BY YEAR(r.racedate), c.county ORDER BY `year` ASC";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getResultsByYearAndCounty',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getResultsByYearAndCountry() {
			$sql = "SELECT YEAR(r.racedate) as year, c.country_code, count(r.id) as count FROM `course` c INNER join results r on c.id = r.course_id WHERE c.country_code IS NOT NULL GROUP BY YEAR(r.racedate), c.country_code ORDER BY `year` ASC";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getResultsByYearAndCountry',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getResultsCountByYear() {
			$sql = "SELECT YEAR(r.racedate) as year, count(r.id) as count FROM results r GROUP BY YEAR(r.racedate) ORDER BY `year` DESC";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getResultsCountByYear',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getPersonalBestTotals() {
			$sql = "SELECT p.id as runnerId, p.name, count(r.id) as count, MIN(r.racedate) AS firstPB, MAX(r.racedate) AS lastPB FROM `results` r inner join runners p on r.runner_id = p.id where r.personal_best = 1 group by runnerId, p.name order by count DESC limit 10";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getPersonalBestTotals',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getPersonalBestTotalByYear() {
			$sql = "SELECT count(*) AS count, YEAR(r.racedate) as year from results r where r.personal_best = 1 GROUP by year order by year desc";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getPersonalBestTotalByYear',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getTopAttendedRaces() {
			$sql = "SELECT e.id as eventId, e.name, r.racedate, count(r.id) as count FROM `results` r inner join events e on r.event_id = e.id group by eventId, e.name, r.racedate order by count desc limit 10";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getTopAttendedRaces',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getTopMembersRacing() {
			$sql = "SELECT p.id as runnerId, p.name, count(r.id) as count FROM `results` r inner join runners p on r.runner_id = p.id group by runnerId, p.name order by count desc limit 10";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getTopMembersRacing',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getTopMembersRacingByYear() {
			$sql = "select YEAR(r.racedate) AS year, count(r.id) AS count, p.id as runnerId, p.name from results r inner join runners p on p.id = r.runner_id group by year, runnerId, p.name order by count DESC, year ASC LIMIT 10";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getTopMembersRacingByYear',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		private function updateAgeGrading($resultId, $eventId, $runnerId, $date) {

			$sql = $this->jdb->prepare("update wma_age_grading g,
 results r,
 wma_records a,
 runners p,
events e
set r.percentage_grading = 
 (ROUND((a.record * 100) / (((substring(r.result, 1, 2) * 3600) +  (substring(r.result, 4, 2) * 60) + (substring(r.result, 7, 2))) * g.grading_percentage), 2))
WHERE g.distance_id = a.distance_id
AND a.distance_id = e.distance_id
AND r.event_id = e.id
AND g.age = (YEAR(r.racedate) - YEAR(p.dob) - IF(DATE_FORMAT(p.dob, '%%j') > DATE_FORMAT(r.racedate, '%%j'), 1, 0))
AND g.sex_id = p.sex_id 
AND g.sex_id = a.sex_id
AND r.runner_id = p.id
AND r.runner_id = %d
AND p.dob <> '0000-00-00'
AND p.dob is not null
AND r.racedate = '%s'
and r.result <> '00:00:00'
and e.id = %d
and e.distance_id <> 0
and r.id = %d", $runnerId, $date, $eventId, $resultId);

			$result = $this->jdb->query($sql);

			if (!$result) {
				return new WP_Error( 'ipswich_jaffa_api_updateAgeGrading',
					'Unknown error in updating age grading results from the database', array( 'status' => 500 ) );	
			}

			return true;
		}

		private function getCategoryId($runnerId, $date) {
			$sql = $this->jdb->prepare(
					"SELECT  c.id
					FROM results r, runners p, category c
					WHERE p.id = %d
					AND r.runner_id = p.id
					AND p.sex_id = c.sex_id
					AND (year(from_days(to_days('%s')-to_days(p.dob) + 1)) >= c.age_greater_equal
					   AND  year(from_days(to_days('%s')-to_days(p.dob) + 1)) <  c.age_less_than)
					LIMIT 1", $runnerId, $date, $date);

			$id = $this->jdb->get_var($sql);

			return $id;
		}
	
		private function isCertificatedCourseAndResult($eventId, $courseId = 0, $result) {
			// TODO
			// First determine if a valid event and result to get a PB
			if ($result == "00:00:00")
				return false;
				
			$sql = $this->jdb->prepare("select
								distance_id
								from							
								events e
								where
								e.id = %d", $eventId);

			$distanceId = $this->jdb->get_var($sql);
			
			return $distanceId > 0;
		}

		private function isPersonalBest($eventId, $runnerId, $result) {				
			$sql = $this->jdb->prepare("select
								count(r.id)
								from
								runners p,
								events e,
								events e2,
								results r
								where
								e.id = r.event_id AND
								e.distance_id = e2.distance_id AND
								e2.id = %d AND
								e.distance_id <> 0 AND
								r.runner_id = p.id AND
								r.result != '00:00:00' AND
								r.result <= %s AND
								r.runner_id = %d
								ORDER BY result
								LIMIT 1", $eventId, $result, $runnerId);

			$count = $this->jdb->get_var($sql);

			return ($count == 0);
		}	
	
		private function isSeasonBest($eventId, $runnerId, $result) {
			$sql = $this->jdb->prepare("select
								count(r.id)
								from
								runners p,
								events e,
								events e2,
								results r
								where
								e.id = r.event_id AND
								e.distance_id = e2.distance_id AND
								e2.id = %d AND
								e.distance_id <> 0 AND
								r.runner_id = p.id AND
								r.result != '00:00:00' AND
								r.result <= %s AND
								r.runner_id = %d AND
								YEAR(r.racedate) = YEAR(NOW())
								ORDER BY result
								LIMIT 1", $eventId, $result, $runnerId);

			$count = $this->jdb->get_var($sql);

			return ($count == 0);
		}

		private function getStandardTypeId($catgeoryId, $time, $eventId) {
			$sql = $this->jdb->prepare("SELECT
									s.standard_type_id
								  FROM
									standard_type st,
									standards s,
									events e
								  WHERE
									s.standard_type_id = st.id AND
									s.category_id = %d AND
									s.distance_id = e.distance_id AND
									e.id = %d AND
									'%s' <= s.standard AND
									st.obsolete = 0
								  ORDER BY
									s.standard
								   LIMIT 1", $catgeoryId, $eventId, $time);

			$standard = $this->jdb->get_var($sql);

			if (empty($standard))
				$standard = 0;

			return $standard;
		}

		private function isNewStandard($resultId) {
			// -- Match results of the same runner
			// -- Match results of the same distance
			// -- Find results with the same standard or better
			// -- Find results in the same age category
			// -- Find results only for first claim club
			// -- Only use the new standards - those 5+
			$sql = $this->jdb->prepare("SELECT  count(r2.id)
									   FROM results r1, results r2, events e1, events e2, runners p, category c1, category c2
									   WHERE r1.id = %d
							   AND r1.id != r2.id
							   AND r1.runner_id = r2.runner_id
							   AND p.club_id = 439
							   AND r1.event_id = e1.id
							   AND r2.event_id = e2.id
							   AND e1.distance_id = e2.distance_id
							   AND r2.standard_type_id < r1.standard_type_id
							   AND r2.standard_type_id > 4
							   AND r2.runner_id = p.id
							   AND p.sex_id = c1.sex_id
							   AND (year(from_days(to_days(r1.racedate)-to_days(p.dob) + 1)) >= c1.age_greater_equal
									   AND  year(from_days(to_days(r1.racedate)-to_days(p.dob) + 1)) <  c1.age_less_than)
							   AND p.sex_id = c2.sex_id
							   AND (year(from_days(to_days(r2.racedate)-to_days(p.dob) + 1)) >= c2.age_greater_equal
									   AND  year(from_days(to_days(r2.racedate)-to_days(p.dob) + 1)) <  c2.age_less_than)
							   AND c1.id = c2.id",
							   $resultId);

			$count = $this->jdb->get_var($sql);

			return ($count == 0);
		}

		private function saveStandardCertificate($resultId) {
			$sql = $this->jdb->prepare("insert into standard_certificates set result_id=%d, issued = 0", $resultId);

			$result = $this->jdb->query($sql);

			if (!$result) {
				return new WP_Error( 'ipswich_jaffa_api_isNewStandard',
						'Unknown error in inserting standard certificate in to the database', array( 'status' => 500 ) );	
			}
			
			return true;
		}
		
		public function getClubRecords($distanceId) {
			$sql = $this->jdb->prepare("           
				SELECT r.runner_id as runnerId, p.Name as runnerName, e.id as eventId, e.Name as eventName, ra.date, r.result, c.code as categoryCode, ra.id as raceId, ra.description, ra.venue
				FROM results AS r
				JOIN (
				  SELECT r1.runner_id, r1.result, MIN(r1.racedate) AS earliest
				  FROM results AS r1
				  JOIN (
					SELECT MIN(r2.result) AS quickest, r2.category_id
					FROM results r2
					INNER JOIN race ra
					ON r2.race_id = ra.id
					INNER JOIN events e
					ON ra.event_id = e.id
					INNER JOIN `distance` d
					ON ra.distance_id = d.id
					INNER JOIN `runners` p2
					ON r2.runner_id = p2.id
					WHERE r2.result != '00:00:00' and d.id = %d and r2.category_id <> 0
					GROUP BY r2.category_id
				   ) AS rt
				   ON r1.result = rt.quickest and r1.category_id = rt.category_id
				   GROUP BY r1.runner_id, r1.result, r1.category_id
				   ORDER BY r1.result asc
				) as rd
				ON r.runner_id = rd.runner_id AND r.result = rd.result AND r.racedate = rd.earliest
				INNER JOIN race ra ON r.race_id = ra.id
				INNER JOIN events e ON ra.event_id = e.id
				INNER JOIN runners p ON r.runner_id = p.id
				INNER JOIN category c ON r.category_id = c.id      
				WHERE c.age_less_than is NOT NULL and ra.distance_id = %d             
				ORDER BY c.age_less_than, c.sex_id", $distanceId, $distanceId);
				
			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getClubRecords',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getResultRankings($distanceId, $year = 0, $sexId = 0)	{		

			// Get the results
			if ($year != 0)
			{
				$dateQuery1 = "  WHERE ra1.date >= '$year-01-01' and ra1.date <= '$year-12-31'";
				$dateQuery2 = "  AND ra2.date >= '$year-01-01' and ra2.date <= '$year-12-31'";
			}
			else
			{
				$dateQuery1 = "";
				$dateQuery2 = "";
			}

			if ($sexId != 0)
			{
				$sexQuery = " AND p2.sex_id = $sexId";
			}
			else
			{
				$sexQuery = "";
			}
		
			$sql = "SET @cnt := 0;";
			
			$this->jdb->query($sql);		

			$sql = "
				SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
					SELECT r.runner_id, p.Name, e.id, e.Name as Event, ra3.date, r.result
					FROM results AS r
					JOIN (
					  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
					  FROM results AS r1
					  INNER JOIN race ra1 ON r1.race_id = ra1.id
					  JOIN (
						SELECT r2.runner_id, MIN(r2.result) AS quickest
						FROM results r2					
						INNER JOIN `race` ra2
						ON ra2.id = r2.race_id
						INNER JOIN `runners` p2
						ON r2.runner_id = p2.id
						WHERE r2.result != '00:00:00' 
						AND ra2.distance_id = $distanceId
						$sexQuery
						$dateQuery2
						GROUP BY r2.runner_id
					   ) AS rt
					   ON r1.runner_id = rt.runner_id AND r1.result = rt.quickest
					   $dateQuery1
					   GROUP BY r1.runner_id, r1.result
					   ORDER BY r1.result asc
					   LIMIT 100
					) as rd
					ON r.runner_id = rd.runner_id AND r.result = rd.result 
					INNER JOIN race ra3 ON r.race_id = ra3.id AND ra3.date = rd.earliest
					INNER JOIN runners p ON r.runner_id = p.id
					INNER JOIN events e ON r.event_id = e.id
					ORDER BY r.result asc
					LIMIT 100) Ranking";

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getResultRankings',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		}
		
		public function getMemberResults($runnerId) {

			$sql = $this->jdb->prepare('select
					  e.id as eventId,
					  e.name as eventName,
					  ra.distance_id as distanceId,
					  r.id as id,
					  ra.date as date,
					  r.position as position,
					  r.result as time,
					  r.personal_best as isPersonalBest,
					  r.season_best as isSeasonBest,
					  st.name as standard,
					  r.info as info,
					  r.percentage_grading as percentageGrading
					from
					  runners p,
					  results r 
					LEFT JOIN standard_type st
					  ON r.standard_type_id = st.id
					LEFT JOIN race ra
					  ON ra.id = r.race_id
					LEFT JOIN events e
					  ON ra.event_id = e.id
					where
					  r.runner_id = p.id AND
					  r.runner_id = %d
					ORDER BY date DESC', $runnerId);

			$results = $this->jdb->get_results($sql, OBJECT);
			
			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getMemberResults',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}

			return $results;
		} // end function GetMemberResults
	
		public function getStandardCertificates($runnerId) {

			$sql = $this->jdb->prepare("SELECT st.name, e.name as 'event', d.distance, r.result, DATE_FORMAT( ra.date, '%%M %%e, %%Y' ) as 'date'
								  FROM standard_certificates sc
								  INNER JOIN results r ON sc.result_id = r.id
								  INNER JOIN standard_type st ON r.standard_type_id = st.id
								  INNER JOIN race ra ON ra.id = r.race_id
								  INNER JOIN events e ON e.id = ra.event_id
								  INNER JOIN distance d ON d.id = ra.distance_id
								  where r.runner_id = %d and ra.date > '2010-01-01'
								  order by st.name desc", $runnerId);

			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getStandardCertificates',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
		
		public function getWMAPercentageRankings($sexId = 0, $distanceId = 0, $year = 0, $distinct = false) {	

			// Get the results
			if ($distanceId != 0)
			{
				$distanceQuery1 = " AND ra1.distance_id = $distanceId";
				$distanceQuery2 = " AND ra2.distance_id = $distanceId";
			}
			else
			{
				$distanceQuery2 = "";
				$distanceQuery1 = "";
			}

			if ($sexId != 0)
			{
				$sexQuery0 = " AND p.sex_id = $sexId";
				$sexQuery1 = " AND p2.sex_id = $sexId";
			}
			else
			{
				$sexQuery0 = "";
				$sexQuery1 = "";
			}
			
			if ($year != 0)
			{
				$yearQuery0 = " AND YEAR(ra.date) >= $year AND YEAR(ra.date) < ($year +1)";
				$yearQuery1 = " AND YEAR(ra1.date) >= $year AND YEAR(ra1.date) < ($year +1)";
				$yearQuery2 = " AND YEAR(ra2.date) >= $year AND YEAR(ra2.date) < ($year +1)";
			}
			else
			{
				$yearQuery0 = "";
				$yearQuery1 = "";
				$yearQuery2 = "";
			}
			
			$sql = "SET @cnt := 0;";
			
			$this->jdb->query($sql);		
			
			if ($distinct == false || $distinct == "false")
			{
				$sql = "
					select @cnt := @cnt + 1 as rank, ranking.* from (
						select r.runner_id as runnerId, p.name, e.id as eventId, e.name as event, ra2.date, r.result, r.percentage_grading as percentageGrading
						from results as r
						inner join runners p on p.id = r.runner_id
						inner join race ra2 on ra2.id = r.race_id
						inner join events e on e.id = ra2.event_id
						where r.percentage_grading > 0
						$sexQuery0
						$distanceQuery2
						$yearQuery2
						order by r.percentage_grading desc
						limit 500) ranking";
			} 
			else
			{
				$sql = "
					SELECT @cnt := @cnt + 1 AS rank, Ranking.* FROM (
						SELECT r.runner_id as runnerId, p.Name, e.id as eventId, e.Name as event, ra.date, r.result, r.percentage_grading AS percentageGrading
						FROM results AS r
						JOIN (
						  SELECT r1.runner_id, r1.result, MIN(ra1.date) AS earliest
						  FROM results AS r1
						  JOIN (
							SELECT r2.runner_id, MAX(r2.percentage_grading) AS highest
							FROM results r2
							INNER JOIN race ra2
							ON r2.race_id = ra2.id						
							INNER JOIN `runners` p2
							ON r2.runner_id = p2.id
							WHERE r2.percentage_grading > 0
							$distanceQuery2
							$sexQuery1
							$yearQuery2
							GROUP BY r2.runner_id
						   ) AS rt
						   ON r1.runner_id = rt.runner_id AND r1.percentage_grading = rt.highest
						   INNER JOIN race ra1 ON r1.race_id = ra1.id 
						   $distanceQuery1
						   $yearQuery1
						   GROUP BY r1.runner_id, r1.result
						   ORDER BY r1.percentage_grading desc
						   LIMIT 100
						) as rd
						ON r.runner_id = rd.runner_id AND r.result = rd.result
						INNER JOIN race ra ON r.race_id = ra.id AND ra.date = rd.earliest
						INNER JOIN events e ON ra.event_id = e.id
						INNER JOIN runners p ON r.runner_id = p.id
						ORDER BY r.percentage_grading desc
						LIMIT 100) Ranking";
			}
						
			$results = $this->jdb->get_results($sql, OBJECT);

			
			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getWMAPercentageRankings',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
		
		public function getAveragePercentageRankings($sexId, $year = 0, $numberOfRaces = 5) {

			$yearQuery = "AND YEAR(ra.date) = $year";
			if ($year == 0) {			
				$yearQuery = "";
			}
			
			$sql = "set @cnt := 0, @runnerId := 0, @rank := 0;";
			
			$this->jdb->query($sql);		
			
			$sql = "select @rank := @rank + 1 AS rank, Results.* FROM (
					select runner_id as runnerId, name, ROUND(avg(ranktopX.percentage_grading),2) as topXAvg from (
					select * from (
					select @cnt := if (@runnerId = ranking.runner_id, @cnt + 1, 1) as rank, @runnerId := ranking.runner_id, ranking.* from (
										
										select r.runner_id, p.name, e.id, e.name as event, r.racedate, r.result, r.percentage_grading
										from results as r
										inner join runners p on p.id = r.runner_id
										inner join race ra on ra.id = r.race_id
										inner join events e on e.id = ra.event_id
										where r.percentage_grading > 0
										AND p.sex_id = $sexId					
										$yearQuery
										order by r.runner_id asc, r.percentage_grading desc) ranking			
					) as rank2
					where rank2.rank <= $numberOfRaces	
					) ranktopX
					group by ranktopX.runner_id
					having count(*) = $numberOfRaces
					order by topXAvg desc
					LIMIT 50) Results";
			
						
			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getAveragePerformanceRankings',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
		
		public function getGrandPrixPoints($year, $sexId) {
			$nextYear = $year + 1;
			$sql = "set @raceId := 0, @rank := 101;";
			
			$this->jdb->query($sql);		
			
			$sql = "select @rank := if (@raceId = gpResults.raceId, @rank - 1, 100) AS rank, @raceId := gpResults.raceId as raceId, gpResults.runnerId, gpResults.name, gpResults.eventName, gpResults.description
				FROM (
				  SELECT
                  p.id as runnerId,
                  p.name,
                  ra.id as raceId,
				  e.name as eventName,
				  ra.description
                FROM
                  results r,
                  race ra,
                  runners p,
				  events e
                WHERE                  
                  ra.date >= '$year-03-01' and ra.date < '$nextYear-03-01'
                  AND r.runner_id = p.id                  
                  AND $sexId = p.sex_id
                  AND ra.id = r.race_id
                  AND ra.grand_prix = 1
				  AND e.id = ra.event_id
                  AND year(FROM_DAYS(TO_DAYS(ra.date) - TO_DAYS(p.dob))) >= 16
                ORDER BY ra.date, ra.id, r.position asc, r.result asc) gpResults";
			
						
			$results = $this->jdb->get_results($sql, OBJECT);

			if (!$results)	{			
				return new WP_Error( 'ipswich_jaffa_api_getGrandPrixPoints',
						'Unknown error in reading results from the database', array( 'status' => 500 ) );			
			}
			
			return $results;
		}
	}
}
?>