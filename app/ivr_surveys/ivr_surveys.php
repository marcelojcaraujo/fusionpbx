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
	Portions created by the Initial Developer are Copyright (C) 2008 - 2019
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J. Crane <markjcrane@fusionpbx.com>
*/

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (permission_exists('ivr_survey_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get posted data
	if (is_array($_POST['ivr_surveys'])) {
		$action = $_POST['action'];
		$search = $_POST['search'];
		$ivr_surveys = $_POST['ivr_surveys'];
	}

//process the http post data by action
	if ($action != '' && is_array($ivr_surveys) && @sizeof($ivr_surveys) != 0) {
		switch ($action) {
			case 'copy':
				if (permission_exists('ivr_survey_add')) {
					$obj = new ivr_survey;
					$obj->copy($ivr_surveys);
				}
				break;
			case 'toggle':
				if (permission_exists('ivr_survey_edit')) {
					$obj = new ivr_survey;
					$obj->toggle($ivr_surveys);
				}
				break;
			case 'delete':
				if (permission_exists('ivr_survey_delete')) {
					$obj = new ivr_survey;
					$obj->delete($ivr_surveys);
				}
				break;
		}

		header('Location: ivr_surveys.php'.($search != '' ? '?search='.urlencode($search) : null));
		exit;
	}

//get order and order by
	$order_by = $_GET["order_by"];
	$order = $_GET["order"];

//add the search term
	$search = strtolower($_GET["search"]);
	if (strlen($search) > 0) {
		$sql_search = "and (";
		$sql_search .= "lower(ivr_survey_name) like :search ";
		$sql_search .= "or lower(ivr_survey_extension) like :search ";
		$sql_search .= "or lower(ivr_survey_enabled) like :search ";
		$sql_search .= "or lower(ivr_survey_description) like :search ";
		$sql_search .= ")";
		$parameters['search'] = '%'.$search.'%';
	}


//prepare to page the results
	$sql = "select count(*) from v_ivr_surveys ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$parameters['domain_uuid'] = $_SESSION["domain_uuid"];
	$database = new database;
	$total_ivr_surveys = $database->select($sql, $parameters, 'column');
	$num_rows = $total_ivr_surveys;

//prepare to page the results
	if ($sql_search) {
		$sql .= $sql_search;
		$database = new database;
		$num_rows = $database->select($sql, $parameters, 'column');
	}

//prepare to page the results
	$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
	$param = "&search=".$search;
	$page = is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = str_replace('count(*)', '*', $sql);
	$sql .= order_by($order_by, $order, 'ivr_survey_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$database = new database;
	$ivr_surveys = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//additional includes
	$document['title'] = $text['title-ivr_surveys'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-ivr_surveys']." (".$num_rows.")</b></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('ivr_survey_add') && (!is_numeric($_SESSION['limit']['ivr_surveys']['numeric']) || $total_ivr_surveys < $_SESSION['limit']['ivr_surveys']['numeric'])) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add'],'id'=>'btn_add','link'=>'ivr_survey_edit.php']);
	}
	if (permission_exists('ivr_survey_add') && $ivr_surveys && (!is_numeric($_SESSION['limit']['ivr_surveys']['numeric']) || $total_ivr_surveys < $_SESSION['limit']['ivr_surveys']['numeric'])) {
		echo button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$_SESSION['theme']['button_icon_copy'],'name'=>'btn_copy','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	if (permission_exists('ivr_survey_edit') && $ivr_surveys) {
		echo button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$_SESSION['theme']['button_icon_toggle'],'name'=>'btn_toggle','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	if (permission_exists('ivr_survey_delete') && $ivr_surveys) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo 		"<form id='form_search' class='inline' method='get'>\n";
	echo 		"<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown='list_search_reset();'>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$_SESSION['theme']['button_icon_search'],'type'=>'submit','id'=>'btn_search','style'=>($search != '' ? 'display: none;' : null)]);
	echo button::create(['label'=>$text['button-reset'],'icon'=>$_SESSION['theme']['button_icon_reset'],'type'=>'button','id'=>'btn_reset','link'=>'ivr_surveys.php','style'=>($search == '' ? 'display: none;' : null)]);
	if ($paging_controls_mini != '') {
		echo 	"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>";
	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('ivr_survey_add') && $ivr_surveys) {
		echo modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('ivr_survey_edit') && $ivr_surveys) {
		echo modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('ivr_survey_delete') && $ivr_surveys) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo $text['description-ivr_survey']."\n";
	echo "<br /><br />\n";

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	if (permission_exists('ivr_survey_add') || permission_exists('ivr_survey_edit') || permission_exists('ivr_survey_delete')) {
		echo "	<th class='checkbox'>\n";
		echo "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle();' ".($ivr_surveys ?: "style='visibility: hidden;'").">\n";
		echo "	</th>\n";
	}
	echo th_order_by('ivr_survey_name', $text['label-name'], $order_by, $order);
	echo th_order_by('ivr_survey_extension', $text['label-extension'], $order_by, $order);
	echo th_order_by('ivr_survey_enabled', $text['label-enabled'], $order_by, $order, null, "class='center'");
	echo th_order_by('ivr_survey_description', $text['label-description'], $order_by, $order, null, "class='hide-sm-dn'");
	if (permission_exists('ivr_survey_edit') && $_SESSION['theme']['list_row_edit_button']['boolean'] == 'true') {
		echo "	<td class='action-button'>&nbsp;</td>\n";
	}
	echo "</tr>\n";

	if (is_array($ivr_surveys) && @sizeof($ivr_surveys) != 0) {
		$x = 0;
		foreach($ivr_surveys as $row) {
			if (permission_exists('ivr_survey_edit')) {
				$list_row_url = "ivr_survey_edit.php?id=".urlencode($row['ivr_survey_uuid']);
			}
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			if (permission_exists('ivr_survey_add') || permission_exists('ivr_survey_edit') || permission_exists('ivr_survey_delete')) {
				echo "	<td class='checkbox'>\n";
				echo "		<input type='checkbox' name='ivr_surveys[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				echo "		<input type='hidden' name='ivr_surveys[$x][uuid]' value='".escape($row['ivr_survey_uuid'])."' />\n";
				echo "	</td>\n";
			}
			echo "	<td>";
			if (permission_exists('ivr_survey_edit')) {
				echo "<a href='".$list_row_url."' title=\"".$text['button-edit']."\">".escape($row['ivr_survey_name'])."</a>";
			}
			else {
				echo escape($row['ivr_survey_name']);
			}
			echo "	</td>\n";
			echo "	<td>".escape($row['ivr_survey_extension'])."&nbsp;</td>\n";
			if (permission_exists('ivr_survey_edit')) {
				echo "	<td class='no-link center'>";
				echo button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['ivr_survey_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_".$x."'); list_action_set('toggle'); list_form_submit('form_list')"]);
			}
			else {
				echo "	<td class='center'>";
				echo $text['label-'.$row['ivr_survey_enabled']];
			}
			echo "	</td>\n";
			echo "	<td class='description overflow hide-sm-dn'>".escape($row['ivr_survey_description'])."&nbsp;</td>\n";
			if (permission_exists('ivr_survey_edit') && $_SESSION['theme']['list_row_edit_button']['boolean'] == 'true') {
				echo "	<td class='action-button'>";
				echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$_SESSION['theme']['button_icon_edit'],'link'=>$list_row_url]);
				echo "	</td>\n";
			}
			echo "</tr>\n";
			$x++;
		}
	}
	unset($ivr_surveys);

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";

	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>