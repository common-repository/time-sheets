<?php

class time_sheets_mydashboard{
	function show_dashboard() {
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$common = new time_sheets_common();

		if ($user_id == 0) {
			echo "You must be logged in to view this page.";
			return;
		}
		$common->show_datestuff();

		echo "<BR><table border='0' cellspacing='2' cellpadding='0'>
		<tr><td width='50%' valign='top'>";
			$this->search_timesheet();
		echo "</td><td valign='top'>";
		$common->show_clients_on_retainer(1);
		echo "</td></tr>
		</table>";
	}
	function search_timesheet_sc(){
		return $this->search_timesheet(0);
	}
	function search_timesheet($display=1) {
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$common = new time_sheets_common();
		$approval = new time_sheets_queue_approval();
		$options = get_option('time_sheets');
		
		$page = '';

		if ($user_id == 0) {
			$page = $page . "You must be logged in to view this page.";
			return $page;
		}


		$start_date = isset($_GET['start_date']) ? $common->f_date($common->clean_from_db($_GET['start_date'])) : "";
		$end_date = isset($_GET['end_date']) ? $common->f_date($common->clean_from_db($_GET['end_date'])) : "";
		$include_completed = isset($_GET['include_completed']) ? $common->clean_from_db($_GET['include_completed']) : "";

		if (isset($_GET['include_me'])) {
			$include_me = ' checked';
		} else {
			IF (!isset($_GET['submit'])) {
				$include_me = ' checked';
			} else {
				$include_me = '';
			}
		}
		if (isset($_GET['show_all_templates'])) {
			$show_all_templates = ' checked';
		} else {
			$show_all_templates = '';
		}

		if (isset($_GET['filter_by_client'])) {
			$filter_by_client = ' checked';
		} else {
			$filter_by_client = '';
		}

		if (!isset($_GET['start_date'])) {
			$start_date = date('Y-m-d', strtotime("-1 Year"));
			$start_date = $common->f_date($start_date);
			$show_all_templates = ' checked';
		}
		if (!isset($_GET['end_date'])) {
			$end_date = date('Y-m-d', strtotime("+1 Day"));
			$end_date = $common->f_date($end_date);
		}

		$v_start_date = date('Y-m-d', strtotime($start_date));
		$v_end_date = date('Y-m-d', strtotime($end_date));

		$page = $page . '<form method="get" name="ts_search">';
		$page = $page . "<table><tr><td>Enter Range To Search:</td><td>";
		$page = $page . "<input type='date' name='start_date' size='10' value='{$v_start_date}' > to <input type='date' name='end_date' size='10' value='{$v_end_date}' ></td></tr>";

		$page = $page . "<tr><td colspan='2'><input type='checkbox' name='include_me' value='checked' {$include_me}> Include My Timesheets</td></tr>";
		if (isset($options['allow_recurring_timesheets'])) {
			$page = $page . "<tr><td colspan='2'><input type='checkbox' name='show_all_templates' value='checked' {$show_all_templates}> Show All My Templates</td></tr>";
		}
		if ($approval->employee_approver_check('true') <> 0) {
			if (is_admin()) {
				$include_all='';
				if (isset($_GET['include_all'])) {
					$include_all = 'checked';
				}
			}
		} else {
			$include_all = 'disabled';
		}
		$page = $page . "<tr><td colspan='2'><input type='checkbox' name='include_all' value='checked' {$include_all}  onClick='disable_by_include_all()'> Include All Users</td></tr>";


		if ($approval->employee_approver_check()<> 0) {
			if (isset($_GET['include_all_team'])) {
				$include_team = ' checked';
			} else {
				$include_team = '';
			}
			
			$page = $page . "<tr><td colspan='2'><input type='checkbox' name='include_all_team' value='checked' {$include_team} onClick='reset_team_member()'> Include All Team Members</td></tr>
			<tr><td>Select Team Member</td><td>";
			$users = $common->return_approvers_team_list($user_id, 1);
				$page = $page . "<select name='team_member'>
					<option value=''>--None</option>
			";
			foreach ($users as $user) {
				$team_member_match = isset($_GET['team_member']) ? $common->is_match($user->a, $_GET['team_member'], ' selected') : "";
				$page = $page . "<option value='{$user->a}'{$team_member_match}>{$user->display_name}</option>";
			}
			$page = $page . "</select></td></tr>";
		} else {
			$page = $page . "<input type='hidden' name='include_all_team' value='checked'>
			<input type='hidden' name='team_member' value=''>";
		}
		$page = $page . "<tr><td colspan='2'><input type='checkbox' name='include_completed' value='checked' {$include_completed}> Include Completed Timesheets</td></tr>";
		
		
		if (isset($options['hide_client_project'])) {

					#$Clients = $common->return_clients_for_user($timesheet_user_id);
					$Projects = $common->return_projects_for_user($user_id);

					if (sizeof($Projects)==1) {
					 $page = $page .  "<tr><td><input type='hidden' name='filter_by_client value='checked'>
					 <input type='hidden' name='ClientId' value='{$Projects[0]->ClientId}'>
					 <input type='hidden' name='ProjectId' value='{$Projects[0]->ProjectId}'>
					 </td></tr>";
					} else {
						$page = $page . "<tr><td colspan='2'><input type='checkbox' name='filter_by_client' value='checked' {$filter_by_client} onClick='disable_client_and_project()'> Filter by Client and Project</td></tr>";
						$js_projects = $common->draw_clients_and_projects(0, 0, 0, '', (object) $_GET, $clientList);
					}
				} else {
					$page = $page . "<tr><td colspan='2'><input type='checkbox' name='filter_by_client' value='checked' {$filter_by_client} onClick='disable_client_and_project()'> Filter by Client and Project</td></tr>";
					$js_projects = $common->draw_clients_and_projects(0, 0, 0, '', (object) $_GET, $clientList);
				}

		$page = $page . $js_projects[1];

		$page = $page . "<tr><td colspan='2'><input type='submit' name='submit' value='Search' class='button-primary'>";
		$page = $page . "<input type='hidden' name='page' value='search_timesheet'></td></tr>";
		$page = $page . "</table></form>";
		if ($approval->employee_approver_check()<> 0) {
$page = $page . "
<script>
function reset_team_member() {
	if (ts_search.include_all_team.checked==true) {
		ts_search.team_member.disabled=true;
	} else {
		ts_search.team_member.disabled=false;
	}
}

reset_team_member();
</script>
";
		}
$page = $page . "<script>";

$p = $common->client_and_projects_javascript($js_projects[0], 'ts_search', 0, (object) $_GET);
$page = $page . $p;

$page = $page . "

function disable_client_and_project() {
	if (ts_search.filter_by_client.checked==false) {
		ts_search.ClientId.disabled=true;
		ts_search.ProjectId.disabled=true;
	} else {
		ts_search.ClientId.disabled=false;
		ts_search.ProjectId.disabled=false;
	}
}

disable_client_and_project();
resetProject()
</script>";
		if (is_admin()) {
$page = $page . "<script>
	function disable_by_include_all() {
		if (ts_search.include_all.checked==true) {
			ts_search.include_all_team.disabled=true;
			ts_search.team_member.disabled=true;
			ts_search.include_me.disabled=true;
		} else {
			ts_search.include_all_team.disabled=false;
			ts_search.team_member.disabled=false;
			ts_search.include_me.disabled=false;
		}
	}
disable_by_include_all();
</script>";

		}
		$page = $page . $this->run_timesheet_search($start_date, $end_date, $include_me);
		if (!isset($_GET['start_date']) || isset($options['allow_recurring_timesheets'])) {
			if (isset($_GET['show_all_templates'])) {
				$start_date = '0000-01-01';
				$end_date = '9999-12-31';
			}
			$page = $page . $this->run_timesheet_template_search($start_date, $end_date, $include_me);
		}
		if ($display==1) {
			echo $page;
		} else {
			return $page;
		}
	}

	function mark_timesheets_week_complete ($timesheet_id) {

		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		$entry = new time_sheets_entry();

		
		$sql = "select ProjectId, total_hours from {$wpdb->prefix}timesheet where timesheet_id=%d";
		$params=array(intval($timesheet_id));

		$timesheet = $db->get_row($sql, $params);


		$sql = "update {$wpdb->prefix}timesheet
				set marked_complete_by=$user_id,
					marked_complete_date = CURDATE(),
					week_complete = 1
				where timesheet_id=%d";
		$params=array(intval($timesheet_id));

		$db->query($sql, $params);



		$sql = "update {$wpdb->prefix}timesheet_client_projects
				set HoursUsed=HoursUsed+$timesheet->total_hours
				where ProjectId=%d";
		$params=array($timesheet->ProjectId);

		$db->query($sql, $params);
				
		$entry->set_project_inactive_on_date($timesheet->ProjectId);

		$sql = "delete from {$wpdb->prefix}timesheet_reject_message_queue
			where timesheet_id = %d";
		$params=array(intval($timesheet_id));
		$db->query($sql, $params);

	#Archive the change
		
		$common->archive_records ('timesheet', 'timesheet_archive', "timesheet_id", intval($timesheet_id), '=', 'UPDATED');

		$entry->email_on_submission($timesheet_id);
	}

	function run_timesheet_search_query($start_date, $end_date, $include_me, $timesheet_table) {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();

		$sql = "select t.*, c.ClientName client_name, cp.ProjectName, u.display_name, 
				monday_nb+tuesday_nb+wednesday_nb+thursday_nb+friday_nb+saturday_nb+sunday_nb as total_nb
			from {$timesheet_table} t
			JOIN {$wpdb->prefix}timesheet_clients c ON t.ClientId = c.ClientId
			JOIN {$wpdb->prefix}timesheet_client_projects cp on t.ProjectId=cp.ProjectId
			join {$wpdb->users} u on t.user_id = u.ID
			where t.start_date between %s and %s";
			if (!isset($_GET['include_completed'])) {
				$sql = "{$sql} and week_complete = 0";
			}
			if (!isset($_GET['include_all'])) {
				if ($include_me != '') {
					$sql = $sql." and (t.user_id={$user_id} /*b1*/";
				}
			}else {
				$sql = $sql." and (1=1";
			}
				if (isset($_GET['include_all_team'])) {
					IF ($include_me == '') {
						$sql = $sql." and (";
					} else {
						$sql = $sql." or ";
					}
					$sql = $sql." t.user_id in (select approvie_user_id from {$wpdb->prefix}timesheet_approvers_approvies where approver_user_id = {$user_id})  )";
				} elseif (isset($_GET['team_member'])) {
					IF ($include_me == '') {
						$sql = $sql." and (";
					} else {
						$sql = $sql." or ";
					}
					$sql = $sql." t.user_id = {$common->intval($_GET['team_member'])} /*b2*/ )";
				} else {
					$sql = $sql." )";
				}
			

			if (isset($_GET['filter_by_client'])) {
				if ($_GET['ClientId']) {
					$sql = $sql." and t.ClientId = {$common->intval($_GET['ClientId'])} ";
				}
				if ($_GET['ProjectId']) {
					$sql = $sql." and t.ProjectId = {$common->intval($_GET['ProjectId'])} ";
				}
			}

			$sql = "{$sql} 
			order by t.start_date desc, c.ClientName asc, cp.ProjectName asc
			";
		
		$params = array($common->mysql_date($start_date), $common->mysql_date($end_date));

		$timesheets = $db->get_results($sql, $params);

		return $timesheets;

	}

	function update_bulk_loop($start_date, $end_date, $include_me) {

		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();

		$ids = '';

		$timesheets = $this->run_timesheet_search_query($start_date, $end_date, $include_me, "{$wpdb->prefix}timesheet");

		if ($timesheets) {
			foreach ($timesheets as $timesheet) {

				if (isset($_GET['week_complete_' . $timesheet->timesheet_id])) {
					
					$this->mark_timesheets_week_complete($timesheet->timesheet_id);

					$ids = $ids . $timesheet->timesheet_id . ', ';

				} 
			}


			$ids = substr($ids,0,strlen($ids)-2);

			if ($ids=="") {
				return "<div class='notice notice-warning is-dismissible'><p>No timesheets were selected to be closed.</p></div>";
			} else {
				return "<div class='notice notice-success is-dismissible'><p>Timesheet(s) {$ids} have been marked complete.</p></div>";
			}

		}
	}
	
	function use_template($template_id) {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		
		$start_date = date('Y-m-d');
		
		$int_start_date = strtotime($start_date);
		if (date('N', strtotime($int_start_date) != 1)) {
			$int_start_date = strtotime('monday this week', $int_start_date);
		}
		$start_date = date('Y-m-d', $int_start_date);

		$sql = "insert into {$wpdb->prefix}timesheet
		(user_id, start_date, entered_date, client_name, project_name, monday_hours, tuesday_hours, wednesday_hours, thursday_hours, friday_hours, saturday_hours, sunday_hours, total_hours, monday_desc, tuesday_desc, wednesday_desc, thursday_desc, friday_desc, saturday_desc, sunday_desc, per_diem_days, hotel_charges, rental_car_charges, tolls, other_expenses, other_expenses_notes, week_complete, marked_complete_by, marked_complete_date, approved, approved_by, approved_date, invoiced, invoiced_by, invoiced_date, invoiceid, ClientId, mileage, EmbargoPendingProjectClose, project_complete, ProjectId, payrolled, payrolled_on, payrolled_by, flight_cost, isPerDiem, perdiem_city, taxi, monday_ot, tuesday_ot, wednesday_ot, thursday_ot, friday_ot, saturday_ot, sunday_ot, monday_nb, tuesday_nb, wednesday_nb, thursday_nb, friday_nb, saturday_nb, sunday_nb)
		select user_id, '{$start_date}', now(), client_name, project_name, monday_hours, tuesday_hours, wednesday_hours, thursday_hours, friday_hours, saturday_hours, sunday_hours, total_hours, monday_desc, tuesday_desc, wednesday_desc, thursday_desc, friday_desc, saturday_desc, sunday_desc, per_diem_days, hotel_charges, rental_car_charges, tolls, other_expenses, other_expenses_notes, week_complete, marked_complete_by, marked_complete_date, approved, approved_by, approved_date, invoiced, invoiced_by, invoiced_date, invoiceid, ClientId, mileage, EmbargoPendingProjectClose, project_complete, ProjectId, payrolled, payrolled_on, payrolled_by, flight_cost, isPerDiem, perdiem_city, taxi, monday_ot, tuesday_ot, wednesday_ot, thursday_ot, friday_ot, saturday_ot, sunday_ot, monday_nb, tuesday_nb, wednesday_nb, thursday_nb, friday_nb, saturday_nb, sunday_nb
		from {$wpdb->prefix}timesheet_scheduled
		where timesheet_id = %d";
		
		$param = array($template_id);
		
		$db->query($sql, $param);
		
		$sql = "select max(timesheet_id) timesheet_id from {$wpdb->prefix}timesheet";
		$new_timesheet = $db->get_row($sql);
		
		$common->archive_records ('timesheet', 'timesheet_archive', "timesheet_id", $new_timesheet->timesheet_id, '=', 'UPDATED');
	}
	
	function use_templates($start_date, $end_date, $include_me) {

		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();

		$ids = '';

		$timesheets = $this->run_timesheet_search_query($start_date, $end_date, $include_me, "{$wpdb->prefix}timesheet_scheduled");

		if ($timesheets) {
			foreach ($timesheets as $timesheet) {

				if (isset($_GET['template_' . $timesheet->timesheet_id])) {
					
					$this->use_template($timesheet->timesheet_id);

					$ids = $ids . $timesheet->timesheet_id . ', ';

				} 
			}


			$ids = substr($ids,0,strlen($ids)-2);

			if ($ids=="") {
				return "<div class='notice notice-warning is-dismissible'><p>No templates were selected to be used.</p></div>";
			} else {
				return "<div class='notice notice-success is-dismissible'><p>Template(s) {$ids} have been used.</p></div>";
			}

		}
	}

	function delete_template($template_id) {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		
		$common->archive_records ('timesheet_scheduled', 'timesheet_scheduled_archive', "timesheet_id", $template_id, '=', 'DELETED');
		
		$sql = "delete from {$wpdb->prefix}timesheet_scheduled
		where timesheet_id = %d";
		
		$param = array($template_id);
		
		$db->query($sql, $param);
	}

	function delete_templates($start_date, $end_date, $include_me) {

		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();

		$ids = '';

		$timesheets = $this->run_timesheet_search_query($start_date, $end_date, $include_me, "{$wpdb->prefix}timesheet_scheduled");

		if ($timesheets) {
			foreach ($timesheets as $timesheet) {

				if (isset($_GET['template_' . $timesheet->timesheet_id])) {
					
					$this->delete_template($timesheet->timesheet_id);

					$ids = $ids . $timesheet->timesheet_id . ', ';

				} 
			}


			$ids = substr($ids,0,strlen($ids)-2);

			if ($ids=="") {
				return "<div class='notice notice-warning is-dismissible'><p>No templates were selected to be deleted.</p></div>";
			} else {
				return "<div class='notice notice-success is-dismissible'><p>Template(s) {$ids} have been deleted.</p></div>";
			}

		}
	}

	function run_timesheet_template_search ($start_date, $end_date, $include_me) {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		
		$page = '';
		

		$timesheets = $this->run_timesheet_search_query($start_date, $end_date, $include_me, "{$wpdb->prefix}timesheet_scheduled");
		
		if (strpos($_SERVER['REQUEST_URI'], 'admin.php') !== False) {
			$timesheet_url = "./admin.php?page=show_timesheet&";
		} else {
			$timesheet_url = $options['rel_url_to_timesheet'] . '?';
		}
		
		if ($timesheets) {
			$total_hours = 0;
			$total_nb = 0;
			$page = '<form method="get" name="ts_bulk_change_template" onclick="copy_formdata_templates()"><select name="template_bulk_action"><option>Bulk Actions</option><option>Delete Template(s)</option><option>Use Template(s)</option></select>';
			$page = $page . "&nbsp;&nbsp;<input type='submit' name='Submit' value='Apply' class='button action'><p>";
			$page = $page . "<table border='1' cellspacing='0' cellpadding='0'><tr><td align='center'>&nbsp;<input type='checkbox' id='allTemplates' name='allTemplates' onclick='select_all_templates()'></td><td align='center'>Template</td><td align='center'>User</td><td align='center'>Next Execution</td><td align='center'>Client Name</td><td align='center'>Project Name</td><td align='center'>Billable Hours</td><td align='center'>Non-Billable Hours</td></tr>";
			foreach ($timesheets as $timesheet) {
				$page = $page . "<tr>";

				if (($timesheet->week_complete==0) && ($timesheet->user_id==$user_id)) {
					$page = $page . "<td align='center'>&nbsp;<input type='checkbox' id='template_{$timesheet->timesheet_id}' name='template_{$timesheet->timesheet_id}' value='checked' onclick='allTemplates.checked = false;'>&nbsp;</td>"; 

				} else {
					$page = $page . "<td>&nbsp;</td>";
				}
				$page = $page . "<td align='center'><a href='{$timesheet_url}timesheet_id={$timesheet->timesheet_id}&is_template=true'>{$timesheet->timesheet_id}</a></td><td>{$timesheet->display_name}</td>";
				
				$page = $page . "<td>{$common->f_date($timesheet->next_execution)}</td><td>{$common->clean_from_db($timesheet->client_name)}</td><td>{$common->clean_from_db($timesheet->ProjectName)}</td><td align='center'>{$common->clean_from_db($timesheet->total_hours)}</td><td align='center'>{$common->clean_from_db($timesheet->total_nb)}</td></tr>";
				$total_hours = $total_hours + $timesheet->total_hours;
				$total_nb = $total_nb + $timesheet->total_nb;
			}
			$grand_total = $total_hours + $total_nb;
			$total_hours = number_format($total_hours, 2);
			$total_nb = number_format($total_nb, 2);
			$grand_total = number_format($grand_total, 2);
			$page = $page . "<TR><TD colspan='6'>&nbsp;</TD><TD align='center'>{$total_hours}</TD><TD align='center'>{$total_nb}</TD></TR>";
			$page = $page . "<TR><TD colspan='6'>&nbsp;</TD><TD colspan='2' align='center'>{$grand_total}</TD></TR>";
			$page = $page . "</table>";
			
			$page = $page . "<script>
			function copy_formdata_templates() {
									ts_bulk_change_template.start_date.value = ts_search.start_date.value;
									ts_bulk_change_template.end_date.value = ts_search.end_date.value;
									ts_bulk_change_template.team_member.value = ts_search.team_member.value;
									ts_bulk_change_template.ClientId.value = ts_search.ClientId.value;
									ts_bulk_change_template.ProjectId.value = ts_search.ProjectId.value;

									if (ts_search.include_all.checked==true) {
										ts_bulk_change_template.include_all.value = ts_search.include_all.value;
									} 
									
									if (ts_search.show_all_templates.checked==true) {
										ts_bulk_change_template.show_all_templates.value = ts_search.show_all_templates.value;
									} 

									if (ts_search.include_me.checked==true) {
										ts_bulk_change_template.include_me.value = ts_search.include_me.value;
									}

									if (ts_search.include_all.checked==true) {
										ts_bulk_change_template.include_all_team.value = ts_search.include_all_team.value;
									}

									if (ts_search.include_completed.checked==true) {
										ts_bulk_change_template.include_completed.value = ts_search.include_completed.value;
									}

									if (ts_search.filter_by_client.checked==true) {
										ts_bulk_change_template.filter_by_client.value = ts_search.filter_by_client.value;
									}

								}
			function select_all_templates() {
				
				if (document.getElementById('allTemplates').checked == true) {";
					foreach ($timesheets as $timesheet) {
						if (($timesheet->week_complete==0) && ($timesheet->user_id==$user_id)) {
							$page = $page . "document.getElementById('template_{$timesheet->timesheet_id}').checked = true;";
						}
					}

			$page = $page . "} else { ";
					foreach ($timesheets as $timesheet) {
						if (($timesheet->week_complete==0) && ($timesheet->user_id==$user_id)) {
							$page = $page . "document.getElementById('template_{$timesheet->timesheet_id}').checked = false;";
						}
					}

				$page = $page . "}
			}
			</script>";
			
			$page = $page . "<input type='hidden' name='page' value='search_timesheet'>";

			$page = $page . "<input type='hidden' name='start_date' value=''>";
			$page = $page . "<input type='hidden' name='end_date' value=''>";
			$page = $page . "<input type='hidden' name='show_all_templates' value=''>";
			$page = $page . "<input type='hidden' name='include_me' >";
			if (isset($_GET['include_all'])) {
				$page = $page . "<input type='hidden' name='include_all' >";
			}
			if (isset($_GET['include_all_team'])) {
				$page = $page . "<input type='hidden' name='include_all_team' >";
			}
			if (isset($_GET['include_completed'])) {
				$page = $page . "<input type='hidden' name='include_completed' >";
			}
			if (isset($_GET['filter_by_client'])) {
				$page = $page . "<input type='hidden' name='filter_by_client' >";
			}
			

			$page = $page . "<input type='hidden' name='team_member' >";
			$page = $page . "<input type='hidden' name='ClientId' >";
			$page = $page . "<input type='hidden' name='ProjectId' >";
 

			$page = $page . "</form>";

		} else {
			$page = "No time sheet templates found.";
		}
		
		return $page;
	}
	
	function run_timesheet_search($start_date, $end_date, $include_me) {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		$page = '';

		if (isset($_GET['bulk_action']) && $_GET['bulk_action']=='Mark Week Complete') {
			$message = $this->update_bulk_loop($start_date, $end_date, $include_me);
			$page = $page . $message;
		}
		
		//Doing the template work here instead of in the template function so that the bulk actions
		//take effect before the timesheet query is executed.
		if (isset($_GET['template_bulk_action']) && $_GET['template_bulk_action']=='Delete Template(s)') {
			$message = $this->delete_templates($start_date, $end_date, $include_me);
			$page = $page . $message;
		}
		
		if (isset($_GET['template_bulk_action']) && $_GET['template_bulk_action']=='Use Template(s)') {
			$message = $this->use_templates($start_date, $end_date, $include_me);
			$page = $page . $message;
		}
		

		$timesheets = $this->run_timesheet_search_query($start_date, $end_date, $include_me, "{$wpdb->prefix}timesheet");

		if (strpos($_SERVER['REQUEST_URI'], 'admin.php') !== False) {
			$timesheet_url = "./admin.php?page=show_timesheet&";
		} else {
			$timesheet_url = $options['rel_url_to_timesheet'] . '?';
		}

		if ($timesheets) {
			$total_hours = 0;
			$total_nb = 0;
			$page = $page . '<form method="get" name="ts_bulk_change" onclick="copy_formdata()"><select name="bulk_action"><option>Bulk Actions</option><option>Mark Week Complete</option></select>';
			$page = $page . "&nbsp;&nbsp;<input type='submit' name='Submit' value='Apply' class='button action'><p>";
			$page = $page . "<table border='1' cellspacing='0' cellpadding='0'><tr><td align='center'>&nbsp;<input type='checkbox' id='selectall' name='selectall' onclick='approve_all()'></td><td align='center'>Time Sheet</td><td align='center'>User</td><td align='center'>Approved</td><td align='center'>Week Completed</td><td align='center'>Start Date</td><td align='center'>Client Name</td><td align='center'>Project Name</td><td align='center'>Billable Hours</td><td align='center'>Non-Billable Hours</td></tr>";
			foreach ($timesheets as $timesheet) {
				$page = $page . "<tr>";

				if (($timesheet->week_complete==0) && ($timesheet->user_id==$user_id)) {
					$page = $page . "<td align='center'>&nbsp;<input type='checkbox' id='week_complete_{$timesheet->timesheet_id}' name='week_complete_{$timesheet->timesheet_id}' value='checked' onclick='selectall.checked = false;'>&nbsp;</td>";

				} else {
					$page = $page . "<td>&nbsp;</td>";
				}
				$page = $page . "<td align='center'><a href='{$timesheet_url}timesheet_id={$timesheet->timesheet_id}'>{$timesheet->timesheet_id}</a></td><td>{$timesheet->display_name}</td><td align='center'>";
				if ($timesheet->approved==1) {
					$page = $page . "<img src='". plugins_url( 'check.png' , __FILE__) ."' height='15' width='15'>";
				} else {
					$page = $page . "&nbsp;";
				}
				$page = $page . "</td><td align='center'>";
				if ($timesheet->marked_complete_by) {
					$page = $page . "<img src='". plugins_url( 'check.png' , __FILE__) ."' height='15' width='15'>";
				} else {
					$page = $page . "&nbsp;";
				}
				$page = $page . "</td><td>{$common->f_date($timesheet->start_date)}</td><td>{$common->clean_from_db($timesheet->client_name)}</td><td>{$common->clean_from_db($timesheet->ProjectName)}</td><td align='center'>{$common->clean_from_db($timesheet->total_hours)}</td><td align='center'>{$common->clean_from_db($timesheet->total_nb)}</td></tr>";
				$total_hours = $total_hours + $timesheet->total_hours;
				$total_nb = $total_nb + $timesheet->total_nb;
			}
			$grand_total = $total_hours + $total_nb;
			$total_hours = number_format($total_hours, 2);
			$total_nb = number_format($total_nb, 2);
			$grand_total = number_format($grand_total, 2);
			$page = $page . "<TR><TD colspan='8'>&nbsp;</TD><TD align='center'>{$total_hours}</TD><TD align='center'>{$total_nb}</TD></TR>";
			$page = $page . "<TR><TD colspan='8'>&nbsp;</TD><TD colspan='2' align='center'>{$grand_total}</TD></TR>";
			$page = $page . "</table>";
			$page = $page . "<input type='hidden' name='page' value='search_timesheet'>";

			$page = $page . "<input type='hidden' name='start_date' value=''>";
			$page = $page . "<input type='hidden' name='end_date' value=''>";
			$page = $page . "<input type='hidden' name='include_me' >";
			$page = $page . "<input type='hidden' name='show_all_templates' value=''>";
			if (isset($_GET['include_all'])) {
				$page = $page . "<input type='hidden' name='include_all' >";
			}
			if (isset($_GET['include_all_team'])) {
				$page = $page . "<input type='hidden' name='include_all_team' >";
			}
			if (isset($_GET['include_completed'])) {
				$page = $page . "<input type='hidden' name='include_completed' >";
			}
			if (isset($_GET['filter_by_client'])) {
				$page = $page . "<input type='hidden' name='filter_by_client' >";
			}
			

			$page = $page . "<input type='hidden' name='team_member' >";
			$page = $page . "<input type='hidden' name='ClientId' >";
			$page = $page . "<input type='hidden' name='ProjectId' >";
 

			$page = $page . "<br></form>";
			$page = $page . "<script>
								function copy_formdata() {
									ts_bulk_change.start_date.value = ts_search.start_date.value;
									ts_bulk_change.end_date.value = ts_search.end_date.value;
									ts_bulk_change.team_member.value = ts_search.team_member.value;
									ts_bulk_change.ClientId.value = ts_search.ClientId.value;
									ts_bulk_change.ProjectId.value = ts_search.ProjectId.value;

									if (ts_search.include_all.checked==true) {
										ts_bulk_change.include_all.value = ts_search.include_all.value;
									} 
									
									if (ts_search.show_all_templates.checked==true) {
										ts_bulk_change.show_all_templates.value = ts_search.show_all_templates.value;
									} 

									if (ts_search.include_me.checked==true) {
										ts_bulk_change.include_me.value = ts_search.include_me.value;
									}

									if (ts_search.include_all.checked==true) {
										ts_bulk_change.include_all_team.value = ts_search.include_all_team.value;
									}

									if (ts_search.include_completed.checked==true) {
										ts_bulk_change.include_completed.value = ts_search.include_completed.value;
									}

									if (ts_search.filter_by_client.checked==true) {
										ts_bulk_change.filter_by_client.value = ts_search.filter_by_client.value;
									}

								}

			function approve_all() {
				
				if (document.getElementById('selectall').checked == true) {";
					foreach ($timesheets as $timesheet) {
						if (($timesheet->week_complete==0) && ($timesheet->user_id==$user_id)) {
							$page = $page . "document.getElementById('week_complete_{$timesheet->timesheet_id}').checked = true;";
						}
					}

			$page = $page . "} else { ";
					foreach ($timesheets as $timesheet) {
						if (($timesheet->week_complete==0) && ($timesheet->user_id==$user_id)) {
							$page = $page . "document.getElementById('week_complete_{$timesheet->timesheet_id}').checked = false;";
						}
					}

				$page = $page . "}
			}

								</script>";
		} else {
			$page = $page . "<BR>No time sheets found with the above search criteria.";
		}
		
		return $page;
	}

}
