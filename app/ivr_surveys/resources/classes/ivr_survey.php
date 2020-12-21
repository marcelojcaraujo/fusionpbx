<?php
/*
 FusionPBX
 Version: MPL 1.1

 The contents of this file are subject to the Mozilla Public License Version
 1.1 (the "License"); you may not use this file except in compliance with
 the License. You may obtain a copy of the License at
 http://www.mozilla.org/MPL/

 Software distributed under the License is distributed on an "AS IS" basis,
 WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 for the specific language governing rights and limitations under the
 License.

 The Original Code is FusionPBX

 The Initial Developer of the Original Code is
 Mark J Crane <markjcrane@fusionpbx.com>
 Portions created by the Initial Developer are Copyright (C) 2008-2019
 the Initial Developer. All Rights Reserved.

 Contributor(s):
 Mark J Crane <markjcrane@fusionpbx.com>
*/

//define the ivr_survey class
if (!class_exists('ivr_survey')) {
	class ivr_survey {

		/**
		 * declare public variables
		 */
		public $domain_uuid;
		public $ivr_survey_uuid;

		/**
		 * declare private variables
		 */
		private $app_name;
		private $app_uuid;
		private $permission_prefix;
		private $list_page;
		private $table;
		private $uuid_prefix;
		private $toggle_field;
		private $toggle_values;

		/**
		 * called when the object is created
		 */
		public function __construct() {

			//assign private variables
			$this->app_name = 'ivr_surveys';
			$this->app_uuid = 'a5788e9b-58bc-bd1b-df59-fff5d51253ac';
			$this->list_page = 'ivr_surveys.php';

		}

		/**
		 * called when there are no references to a particular object
		 * unset the variables used in the class
		 */
		public function __destruct() {
			foreach ($this as $key => $value) {
				unset($this->$key);
			}
		}

		public function find() {
			$sql = "select * from v_ivr_surveys ";
			$sql .= "where domain_uuid = :domain_uuid ";
			if (isset($this->ivr_survey_uuid)) {
				$sql .= "and ivr_survey_uuid = :ivr_survey_uuid ";
				$parameters['ivr_survey_uuid'] = $this->ivr_survey_uuid;
			}
			if (isset($this->order_by)) {
				$sql .= $this->order_by;
			}
			$parameters['domain_uuid'] = $this->domain_uuid;
			$database = new database;
			return $database->select($sql, $parameters, 'all');
		}

		/**
		 * delete records
		 */
		public function delete($records) {
			//assign private variables
				$this->permission_prefix = 'ivr_survey_';
				$this->table = 'ivr_surveys';
				$this->uuid_prefix = 'ivr_survey_';

			if (permission_exists($this->permission_prefix.'delete')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//delete multiple records
					if (is_array($records) && @sizeof($records) != 0) {

						//filter out unchecked ivr surveys, build where clause for below
							foreach ($records as $record) {
								if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$uuids[] = "'".$record['uuid']."'";
								}
							}

						//get necessary ivr survey details
							if (is_array($uuids) && @sizeof($uuids) != 0) {
								$sql = "select ".$this->uuid_prefix."uuid as uuid, dialplan_uuid, ivr_survey_context from v_".$this->table." ";
								$sql .= "where (domain_uuid = :domain_uuid) ";
								$sql .= "and ".$this->uuid_prefix."uuid in (".implode(', ', $uuids).") ";
								$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
								$database = new database;
								$rows = $database->select($sql, $parameters, 'all');
								if (is_array($rows) && @sizeof($rows) != 0) {
									foreach ($rows as $row) {
										$ivr_surveys[$row['uuid']]['dialplan_uuid'] = $row['dialplan_uuid'];
										$ivr_survey_contexts[] = $row['ivr_survey_context'];
									}
								}
								unset($sql, $parameters, $rows, $row);
							}

						//build the delete array
							$x = 0;
							foreach ($ivr_surveys as $ivr_survey_uuid => $ivr_survey) {
								$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $ivr_survey_uuid;
								$array['ivr_survey_options'][$x]['ivr_survey_uuid'] = $ivr_survey_uuid;
								$array['dialplans'][$x]['dialplan_uuid'] = $ivr_survey['dialplan_uuid'];
								$x++;
							}

						//delete the checked rows
							if (is_array($array) && @sizeof($array) != 0) {

								//grant temporary permissions
									$p = new permissions;
									$p->add('ivr_survey_option_delete', 'temp');
									$p->add('dialplan_delete', 'temp');

								//execute delete
									$database = new database;
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->delete($array);
									unset($array);

								//revoke temporary permissions
									$p->delete('ivr_survey_option_delete', 'temp');
									$p->delete('dialplan_delete', 'temp');

								//clear the cache
									if (is_array($ivr_survey_contexts) && @sizeof($ivr_survey_contexts) != 0) {
										$ivr_survey_contexts = array_unique($ivr_survey_contexts);
										$cache = new cache;
										foreach ($ivr_survey_contexts as $ivr_survey_context) {
											$cache->delete("dialplan:".$ivr_survey_context);
										}
									}

								//set message
									message::add($text['message-delete']);
							}
							unset($records, $ivr_surveys);
					}
			}
		}

		public function delete_options($records) {
			//assign private variables
				$this->permission_prefix = 'ivr_survey_option_';
				$this->table = 'ivr_survey_options';
				$this->uuid_prefix = 'ivr_survey_option_';

			if (permission_exists($this->permission_prefix.'delete')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//delete multiple records
					if (is_array($records) && @sizeof($records) != 0) {

						//filter out unchecked ivr survey options, build delete array
							$x = 0;
							foreach ($records as $record) {
								if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $record['uuid'];
									$array[$this->table][$x]['ivr_survey_uuid'] = $this->ivr_survey_uuid;
									$x++;
								}
							}

						//get ivr survey context
							if (is_array($array) && @sizeof($array) != 0 && is_uuid($this->ivr_survey_uuid)) {
								$sql = "select ivr_survey_context from v_ivr_surveys ";
								$sql .= "where (domain_uuid = :domain_uuid) ";
								$sql .= "and ivr_survey_uuid = :ivr_survey_uuid ";
								$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
								$parameters['ivr_survey_uuid'] = $this->ivr_survey_uuid;
								$database = new database;
								$ivr_survey_context = $database->select($sql, $parameters, 'column');
								unset($sql, $parameters);
							}

						//delete the checked rows
							if (is_array($array) && @sizeof($array) != 0) {

								//execute delete
									$database = new database;
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->delete($array);
									unset($array);

								//clear the cache
									if ($ivr_survey_context != '') {
										$cache = new cache;
										$cache->delete("dialplan:".$ivr_survey_context);
									}

							}
							unset($records);
					}
			}
		}

		/**
		 * toggle records
		 */
		public function toggle($records) {
			//assign private variables
				$this->permission_prefix = 'ivr_survey_';
				$this->table = 'ivr_surveys';
				$this->uuid_prefix = 'ivr_survey_';
				$this->toggle_field = 'ivr_survey_enabled';
				$this->toggle_values = ['true','false'];

			if (permission_exists($this->permission_prefix.'edit')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//toggle the checked records
					if (is_array($records) && @sizeof($records) != 0) {

						//get current toggle state
							foreach($records as $x => $record) {
								if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$uuids[] = "'".$record['uuid']."'";
								}
							}
							if (is_array($uuids) && @sizeof($uuids) != 0) {
								$sql = "select ".$this->uuid_prefix."uuid as uuid, ".$this->toggle_field." as toggle, dialplan_uuid from v_".$this->table." ";
								$sql .= "where domain_uuid = :domain_uuid ";
								$sql .= "and ".$this->uuid_prefix."uuid in (".implode(', ', $uuids).") ";
								$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
								$database = new database;
								$rows = $database->select($sql, $parameters, 'all');
								if (is_array($rows) && @sizeof($rows) != 0) {
									foreach ($rows as $row) {
										$ivr_surveys[$row['uuid']]['state'] = $row['toggle'];
										$ivr_surveys[$row['uuid']]['dialplan_uuid'] = $row['dialplan_uuid'];
									}
								}
								unset($sql, $parameters, $rows, $row);
							}

						//build update array
							$x = 0;
							foreach ($ivr_surveys as $uuid => $ivr_survey) {
								$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $uuid;
								$array[$this->table][$x][$this->toggle_field] = $ivr_survey['state'] == $this->toggle_values[0] ? $this->toggle_values[1] : $this->toggle_values[0];
								$array['dialplans'][$x]['dialplan_uuid'] = $ivr_survey['dialplan_uuid'];
								$array['dialplans'][$x]['dialplan_enabled'] = $ivr_survey['state'] == $this->toggle_values[0] ? $this->toggle_values[1] : $this->toggle_values[0];
								$x++;
							}

						//save the changes
							if (is_array($array) && @sizeof($array) != 0) {

								//grant temporary permissions
									$p = new permissions;
									$p->add('dialplan_edit', 'temp');

								//save the array
									$database = new database;
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->save($array);
									unset($array);

								//revoke temporary permissions
									$p->delete('dialplan_edit', 'temp');

								//clear the cache
									$cache = new cache;
									$cache->delete("dialplan:".$_SESSION['domain_name']);
									foreach ($ivr_surveys as $ivr_survey_uuid => $ivr_survey) {
										$cache->delete("configuration:ivr_survey.conf:".$ivr_survey_uuid);
									}

								//set message
									message::add($text['message-toggle']);
							}
							unset($records, $states);
					}

			}
		}

		/**
		 * copy records
		 */
		public function copy($records) {
			//assign private variables
				$this->permission_prefix = 'ivr_survey_';
				$this->table = 'ivr_surveys';
				$this->uuid_prefix = 'ivr_survey_';

			if (permission_exists($this->permission_prefix.'add')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//copy the checked records
					if (is_array($records) && @sizeof($records) != 0) {

						//get checked records
							foreach($records as $x => $record) {
								if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$uuids[] = "'".$record['uuid']."'";
								}
							}

						//create insert array from existing data
							if (is_array($uuids) && @sizeof($uuids) != 0) {

								//primary table
									$sql = "select * from v_".$this->table." ";
									$sql .= "where domain_uuid = :domain_uuid ";
									$sql .= "and ".$this->uuid_prefix."uuid in (".implode(', ', $uuids).") ";
									$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
									$database = new database;
									$rows = $database->select($sql, $parameters, 'all');
									if (is_array($rows) && @sizeof($rows) != 0) {
										$y = $z = 0;
										foreach ($rows as $x => $row) {
											$new_ivr_survey_uuid = uuid();
											$new_dialplan_uuid = uuid();

											//copy data
												$array[$this->table][$x] = $row;

											//overwrite
												$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $new_ivr_survey_uuid;
												$array[$this->table][$x]['dialplan_uuid'] = $new_dialplan_uuid;
												$array[$this->table][$x]['ivr_survey_description'] = trim($row['ivr_survey_description'].' ('.$text['label-copy'].')');

											//ivr survey options sub table
												$sql_2 = "select * from v_ivr_survey_options where ivr_survey_uuid = :ivr_survey_uuid";
												$parameters_2['ivr_survey_uuid'] = $row['ivr_survey_uuid'];
												$database = new database;
												$rows_2 = $database->select($sql_2, $parameters_2, 'all');
												if (is_array($rows_2) && @sizeof($rows_2) != 0) {
													foreach ($rows_2 as $row_2) {

														//copy data
															$array['ivr_survey_options'][$y] = $row_2;

														//overwrite
															$array['ivr_survey_options'][$y]['ivr_survey_option_uuid'] = uuid();
															$array['ivr_survey_options'][$y]['ivr_survey_uuid'] = $new_ivr_survey_uuid;

														//increment
															$y++;

													}
												}
												unset($sql_2, $parameters_2, $rows_2, $row_2);

											//ivr survey dialplan record
												$sql_3 = "select * from v_dialplans where dialplan_uuid = :dialplan_uuid";
												$parameters_3['dialplan_uuid'] = $row['dialplan_uuid'];
												$database = new database;
												$dialplan = $database->select($sql_3, $parameters_3, 'row');
												if (is_array($dialplan) && @sizeof($dialplan) != 0) {

													//copy data
														$array['dialplans'][$z] = $dialplan;

													//overwrite
														$array['dialplans'][$z]['dialplan_uuid'] = $new_dialplan_uuid;
														$dialplan_xml = $dialplan['dialplan_xml'];
														$dialplan_xml = str_replace($row['ivr_survey_uuid'], $new_ivr_survey_uuid, $dialplan_xml); //replace source ivr_survey_uuid with new
														$dialplan_xml = str_replace($dialplan['dialplan_uuid'], $new_dialplan_uuid, $dialplan_xml); //replace source dialplan_uuid with new
														$array['dialplans'][$z]['dialplan_xml'] = $dialplan_xml;
														$array['dialplans'][$z]['dialplan_description'] = trim($dialplan['dialplan_description'].' ('.$text['label-copy'].')');

													//increment
														$z++;
												}
												unset($sql_3, $parameters_3, $dialplan);

										}
									}
									unset($sql, $parameters, $rows, $row);
							}

						//save the changes and set the message
							if (is_array($array) && @sizeof($array) != 0) {

								//grant temporary permissions
									$p = new permissions;
									$p->add('ivr_survey_option_add', 'temp');
									$p->add('dialplan_add', 'temp');

								//save the array
									$database = new database;
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->save($array);
									unset($array);

								//revoke temporary permissions
									$p = new permissions;
									$p->delete('ivr_survey_option_add', 'temp');
									$p->delete('dialplan_add', 'temp');

								//clear the cache
									$cache = new cache;
									$cache->delete("dialplan:".$_SESSION['domain_name']);

								//set message
									message::add($text['message-copy']);

							}
							unset($records);
					}

			}
		}

	}
}

?>
