<?php
class time_sheets_common {
	
	function archive_records ($source_table, $archive_table, $search_column_name, $search_value, $search_symbol = '=', $command = 'UPDATED') {
		global $wpdb;
		$db = new time_sheets_db();
		
		$user = wp_get_current_user();
		$user_id = $user->ID;
		
		$sql = "select COLUMN_NAME, ORDINAL_POSITION from information_schema.columns where table_name = '{$wpdb->prefix}{$source_table}' order by ORDINAL_POSITION";
		$columns = $db->get_results($sql);
		
		$column_list = "";
		
		if ($columns) {
			foreach ($columns as $column) {
				$column_list =  $column_list . ", " .  $column->COLUMN_NAME;
			}
		}
		
		$sql = "insert into {$wpdb->prefix}{$archive_table}
		select {$user_id}, now(), '{$command}' {$column_list} 
		from {$wpdb->prefix}{$source_table}
		where {$search_column_name} {$search_symbol} {$search_value}";
		
		$db->query($sql);
		
		//if (!isset($results)) {
		//	$page = "<div class='notice notice-info'><p>Unable to archive the record(s) {$search_value} from the table {$source_table}. Please give this information to your administrator. </p></div>";
		//} else {
		//	$page = '';
		//}
		
		//return $page;
		
	}
	
	function get_employee_hourly_rate ($user_id, $project_id) {
		global $wpdb;
		$db = new time_sheets_db();
		
		$sql = "select hourly_rate
		from {$wpdb->prefix}timesheet_project_employee_titles p
		join {$wpdb->prefix}timesheet_employee_title_join e on p.title_id = e.title_id
		where e.user_id = {$user_id} and p.project_id = {$project_id}";
		$rate = $db->get_row($sql);
		
		return $rate->hourly_rate;
	}
	function get_timesheets_total_hours ($timesheet_id, $regular=TRUE, $overtime=TRUE, $non_billable=FALSE) {
		global $wpdb;
		$db = new time_sheets_db();
		
		$sql = "select * from {$wpdb->prefix}timesheet where timesheet_id = %d";
		$params = array($timesheet_id);
		$timesheet = $db->get_row($sql, $params);
		
		$total_hours = 0;
		
		if (isset($regular)) {
			$total_hours = $total_hours+$timesheet->monday_hours+$timesheet->tuesday_hours+$timesheet->wednesday_hours+$timesheet->thursday_hours+$timesheet->friday_hours+$timesheet->saturday_hours+$timesheet->sunday_hours;
		}
		
		if (isset($overtime)) {
			$total_hours + $total_hours+$timesheet->monday_ot+$timesheet->tuesday_ot+$timesheet->wednesday_ot+$timesheet->thursday_ot+$timesheet->friday_ot+$timesheet->saturday_ot+$timesheet->sunday_ot;
		}
		
		if (isset($non_billable)) {
				$total_hours + $total_hours+$timesheet->monday_nb+$timesheet->tuesday_nb+$timesheet->wednesday_nb+$timesheet->thursday_nb+$timesheet->friday_nb+$timesheet->saturday_nb+$timesheet->sunday_nb;
		}
		
		return $total_hours;
	}
	
	function get_retainer_interval_startdate ($retainer_id, $start_date, $project_id) {
		global $wpdb;
		$db = new time_sheets_db();
		$interval = 1;
		
		$sql = "select * from {$wpdb->prefix}timesheet_custom_retainer_frequency where retainer_id = {$retainer_id}";
		$retainer = $db->get_row($sql);
		
		if ($retainer_id > 3) { //retainer is custom, so do some weird stuff to make it work.
			$interval = $retainer->interval_value;
			
			if ($retainer->w_m_q_interval=='m') {
				$retainer_id = 1;
			} elseif ($retainer->w_m_q_interval=='w') {
				$retainer_id = 2;
			} elseif (($retainer->w_m_q_interval=='q')) {
				$retainer_id = 3;
			} elseif (($retainer->w_m_q_interval=='a')) {
				$retainer_id = 4;
			}
		}
		
		if (isset($retainer) && $retainer->interval_value==-1) {
			$sql = "select * from {$wpdb->prefix}timesheet_client_projects where ProjectId = %d";
			$params = array($project_id);
			$project = $db->get_row($sql, $params);
			$start_date = $project->create_date;
		} else {
		
			if ($retainer_id==1) { //monthly
				$start_date = date('Y-m-01', strtotime($start_date));
			} elseif ($retainer_id==2) { //weekly
				//nothing to do here
			} elseif ($retainer_id==3) { //Quarterly

				$month = date('m',strtotime($start_date));
				if ($month==1 || $month==2 || $month==3) {
					$month = "01";
				} elseif ($month==4 || $month==5 || $month==6) {
					$month = "04";
				} elseif ($month==7 || $month==8 || $month==9) {
					$month = "07";
				} elseif ($month==10 || $month==11 || $month==12) {
					$month = "10";
				}
				$start_date = date("Y-{$month}-01");
			} elseif ($retainer_id==4) {
				$start_date = date('Y-01-01');
			}
		}
		return $start_date;
	}	
	function return_clients_for_user ($user_id) {
		global $wpdb;
		$db = new time_sheets_db();
		
		$sql = "select ClientName, tc.ClientId
					from {$wpdb->prefix}timesheet_clients tc
					join {$wpdb->prefix}timesheet_clients_users tcu ON tc.ClientId = tcu.ClientId
					WHERE tcu.user_id = $user_id";
		$params = array($user_id);
		
		$clients = $db->get_results($sql, $params);
		
		return $clients;
	}
	
	function return_projects_for_user ($user_id) {
		global $wpdb;
		$db = new time_sheets_db();
		
		$sql = "select distinct ClientName, tc.ClientId, tcp.ProjectId, tcp.ProjectName
					from {$wpdb->prefix}timesheet_clients tc
					join {$wpdb->prefix}timesheet_clients_users tcu ON tc.ClientId = tcu.ClientId
					join {$wpdb->prefix}timesheet_client_projects tcp on tc.ClientId = tcp.ClientId
						 and tcp.Active=1
					WHERE tc.Active = 1";
		if ($user_id!="-1") {
			$sql = $sql . " AND tcu.user_id = $user_id";

			$params = array($user_id);

			$clients = $db->get_results($sql, $params);
		} else {
			$clients = $db->get_results($sql);
		}
		return $clients;
	}
	
	function return_blank_if_blank($string) {
		if ($string) {
			$string = '';
		}
		return $string;
	}
	function draw_clients_and_projects ($client_id, $project_id, $timesheet_id, $disable_object, $timesheet, &$clientList) {
		//Draws the client and project drop downs. Returns JSON document
		//For use by the Javascript function.

		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();
		$queue_approval = new time_sheets_queue_approval();
		$options = get_option('time_sheets');

		$page = '';
		
		if (!isset($client_id)) {
			$client_id = 0;
		}

		if (!isset($project_id)) {
			$project_id = 0;
		}

		$clients = $db->get_results("select ClientName, tc.ClientId, r.BillOnProjectCompletion
					from {$wpdb->prefix}timesheet_clients tc
					join {$wpdb->prefix}timesheet_clients_users tcu ON tc.ClientId = tcu.ClientId
					join {$wpdb->prefix}timesheet_client_projects tcp on tc.ClientId = tcp.ClientId
						 and tcp.Active=1 
					left outer join {$wpdb->prefix}timesheet_recurring_invoices_monthly r on tc.ClientId = r.client_id
					WHERE tcu.user_id = $user_id
					union
					select ClientName, tc1.ClientId, r.BillOnProjectCompletion
					from {$wpdb->prefix}timesheet_clients tc1
					left outer join {$wpdb->prefix}timesheet_recurring_invoices_monthly r on tc1.ClientId = r.client_id
					where tc1.ClientId = $client_id
					union
					select ClientName, tc2.ClientId, r.BillOnProjectCompletion
					from {$wpdb->prefix}timesheet_clients tc2
					left outer join {$wpdb->prefix}timesheet_recurring_invoices_monthly r on tc2.ClientId = r.client_id
					join {$wpdb->prefix}timesheet t on tc2.ClientId = t.ClientId
					where t.timesheet_id = $timesheet_id
					order by ClientName");


		$sql = "select tcp.ClientId, ProjectId, ProjectName, BillOnProjectCompletion from {$wpdb->prefix}timesheet_client_projects tcp join {$wpdb->prefix}timesheet_clients_users tcu on tcp.ClientId = tcu.ClientId where tcu.user_id = $user_id and tcp.Active=1 
union
select ClientId, ProjectId, ProjectName, BillOnProjectCompletion from {$wpdb->prefix}timesheet_client_projects where ProjectId = {$project_id}
order by ProjectName
";

		$projects = $db->get_results($sql);

			foreach ($projects as $project) {
				$project->ProjectName = $this->clean_from_db($project->ProjectName);
				$clean_projects[] = $project;
			}


			$js_projects[0] = (isset($clean_projects)) ? json_encode($clean_projects) : json_encode('');

		if (isset($options['hide_client_project']) && count($clients)==1 && count($projects)==1) {
			$client = $clients[0];
			$project = $projects[0];
			echo "<tr><td><input type='hidden' name='ClientId' value='{$client->ClientId}'>
			<input type='hidden' name='ProjectId' value='{$project->ProjectId}'></td></tr>";
			$clientList = "{$clientList}\"{$client->ClientId}\", ";
			$clientList = "[$clientList \"-1\"]";
		} else {

			$page = $page .  "<tr><td>Client Name:</td>";

			if ($clients) {
				$page = $page . "<td><select name='ClientId' onChange='clientChange()'{$disable_object}><option value='-2'>Select Client</option>";
				$clientList = '';
				foreach ($clients as $client) {
					$page = $page . "<option value='{$client->ClientId}'";
					if ($timesheet_id != 0 && ($client->ClientId==$timesheet->ClientId)) {
						$page = $page . " selected";
						}
					$page = $page . ">{$this->clean_from_db($client->ClientName)}</option>";
					if ($client->BillOnProjectCompletion=="1") {
						$clientList = "{$clientList}\"{$client->ClientId}\", ";
					}
				}
				if ($clientList) {
					$clientList = "[$clientList \"-1\"]";
				} else {
					$clientList = "[\"-1\"]";
				}
				$page = $page . "</select>";
			} else {
				$page = $page . "<td>New Client must be added.";
			}

			$admin_url = get_admin_url();

			if ($queue_approval->employee_approver_check()!=0 && $disable_object=="") {
				$page = $page . "&nbsp;<a href='{$admin_url}admin.php?page=timesheet_manage_clients&menu=New+Client'>Add a Client</a>";
			}
			if (isset($options['enable_requesting_access'])) {
				$page = $page . "&nbsp;<a href='{$admin_url}admin.php?page=show_timesheet&subpage=request_client_access'>Request Access to a Client</a>";
			}


			$page = $page . "</td></tr>";

			$page = $page . "<tr><td>Project Name:</td><td><Select name='ProjectId'{$disable_object} onChange='ProjectChange()'></select>";
			if ($queue_approval->employee_approver_check()!=0 && $disable_object=="") {
				$page = $page . "<a href='{$admin_url}admin.php?page=timesheet_manage_clients&menu=New+Project'>Add a Project</a>";
			}

			$page = $page . "</td></tr>";
		}

		$js_projects[1] = $page;
		
		return $js_projects;

	}

	function client_and_projects_javascript($js_projects, $html_formname, $billing_on_form, $timesheet) {
	$page = '';
		
$page = $page . "

function clientChange() {
	if ({$html_formname}.ClientId.value==-2) {
		alert ('Invalid Client Selected');
	}
	resetProject();
	";
	if ($billing_on_form==1) {
		$page = $page . "isProjectBilled();
		isClientProjectBilled();";
	}
	$page = $page . "
}


function resetProject(){
	var projectlist = {$js_projects};
	{$html_formname}.ProjectId.options.length = 0;

	var numberOfProjects = projectlist.length;
	//alert(numberOfProjects);
	for (var i = 0; i < numberOfProjects; i++) {
		project = projectlist[i];
		if (project['ClientId']=={$html_formname}.ClientId.value) {
			var opt = document.createElement('option');
			opt.value = project['ProjectId'];
			opt.innerHTML = project['ProjectName'];
			{$html_formname}.ProjectId.appendChild(opt);
		}
	  //alert(project['ProjectName']);
	}";

	if (isset($timesheet->ProjectId)) {
		$page = $page . "
	{$html_formname}.ProjectId.value={$timesheet->ProjectId};";
	}
	$page = $page . "
}";
		
	return $page;
	}
	
	function return_employee_list() {
		global $wpdb;
		$db = new time_sheets_db();

		$sql = "select u.user_login, u.id, u.display_name
		from {$wpdb->users} u 
		join {$wpdb->prefix}usermeta um on u.id = um.user_id 
		where um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}'
		order by u.display_name";

		$users = $db->get_results($sql);

		return $users;

	}

	function return_approvers_team_list($approver_id, $members_only=0) {
		global $wpdb;
		$db = new time_sheets_db();

		$sql = "select u.user_login, aa.approvie_user_id a, u.id, u.display_name
		from {$wpdb->users} u 
		join {$wpdb->prefix}usermeta um on u.id = um.user_id 
		";
		if ($members_only==1) {
			$sql=$sql."inner";
		} else {
			$sql=$sql."left outer";
		}
		$sql = $sql." join {$wpdb->prefix}timesheet_approvers_approvies aa ON u.ID = aa.approvie_user_id
			and aa.approver_user_id = %d
		where um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}'
		order by u.display_name";

		$values = array($approver_id);

		$users = $db->get_results($sql, $values);

		return $users;
	}

	function intval($string) {
		return intval($string);
	}

	function esc_textarea($string) {
		$string = stripslashes($string);
		return esc_textarea($string);
	}

	function clean_from_db($string) {
		if ($string==null) {
			return;
		}
		
		$string = str_replace("''", "'", $string);
		$string = esc_textarea($string);
		$string = stripslashes($string);

		return $string;
	}

	function add_days_to_date($idate, $days, $holidays) {
		$date = new DateTime($idate);
		date_add($date, date_interval_create_from_date_string("{$days} days"));
		$leading_color = "";
		$trailing_color = "";

		if ($holidays) {
			foreach ($holidays as $dt) {
				foreach ($dt as $daykey) {
					if (date('m-d',strtotime((string)$daykey))==date_format($date, 'm-d')) {
						$leading_color="<font color='red'>";
						$trailing_color="</font>";
					}
				}
			}
		}
		$date = date_format($date, 'm-d');
		$return = "{$leading_color} {$date} {$trailing_color}";
		return $return;
	}

	function show_clients_on_retainer($mine_only = 0) {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;

		$sql = "select ClientName, im.hours_included MonthlyHours, im.hourly_rate HourlyRate, im.Notes, im.ProjectName
			from {$wpdb->prefix}timesheet_clients tc
			inner join {$wpdb->prefix}timesheet_client_projects im on tc.clientid = im.ClientId";
		if ($mine_only == 1) {
			$sql = $sql." inner join {$wpdb->prefix}timesheet_clients_users tcu on tc.clientid = tcu.clientid and tcu.user_id = {$user_id}";
		}
			$sql = $sql." where im.retainer_id is not null
				and tc.clientid in (select p.ClientId 
							from {$wpdb->prefix}timesheet t
							inner join {$wpdb->prefix}timesheet_client_projects p on t.ProjectId = p.ProjectId
							where t.week_complete = 1 ";
				if (isset($_GET['retainer_type']) && $_GET['retainer_type'] <> 0) {
					$sql = $sql . " and im.retainer_id = " . intval($_GET['retainer_type']);
				}
				if (!isset($_GET['show_retainers'])) {
					$sql = $sql . " and 1=2";
				}
				if ($_GET['page'] == 'approve_timesheet') { #Approval Menu
					$sql = $sql . " and t.approved = 0 and t.invoiced = 0 ";
					if ((isset($_GET['show_retainers'])) && (isset($_GET['hide_nonretainers']))){
						$sql = $sql . " and p.IsRetainer = 1 ";
					} else if ((!isset($_GET['show_retainers'])) && (!isset($_GET['hide_nonretainers']))) {
						$sql = $sql . " and p.IsRetainer = 0 ";
					}
				} else if ($_GET['page'] == 'invoice_timesheet') { #Invoice menu
					$sql = $sql . " and t.approved = 1 and t.invoiced = 0 ";
					if ((isset($_GET['show_retainers'])) && (isset($_GET['hide_nonretainers']))) {
						$sql = $sql . " and p.IsRetainer = 1 ";
					} else if ((!isset($_GET['show_retainers'])) && (!isset($_GET['hide_nonretainers']))) {
						$sql = $sql . " and p.IsRetainer = 0 ";
					}
				} else if ($_GET['page'] == 'search_timesheet') { #My Dashboard
					if (!isset($_GET['include_completed'])) {
						$sql = $sql . " and im.ProjectId in (select ProjectId from {$wpdb->prefix}timesheet t where t.week_complete = 0)";
					}
				}


			$sql = $sql . " )
			order by ClientName";

		$clients = $db->get_results($sql);
		echo "<table>";
		if ($clients) {
			echo "<tr><td><table border='1' cellpadding='0' cellspacing='0' width='50%'><tr><td>Client Name</td><td>Project Name</td><td>Number of Hours</td><td>Hourly Rate on Retainer</td><td Notes</td></tr>";
			foreach ($clients as $client) {
				echo "<tr><td>{$client->ClientName}</td><td>{$client->ProjectName}</td><td align='center'>{$client->MonthlyHours}</td><td align='center'>$ {$client->HourlyRate}</td><td><textarea cols='50' disabled>{$this->esc_textarea($client->Notes)}</textarea></td></tr>";
			}
			echo "</table>";
		} else {
			echo '<tr><td div class="notice notice-info"><p>No retainer notes match filter for active time sheets.</p>';
		}
		echo "</td></tr>";

		

		$sql = "select ClientName, ProjectName, p.notes
			from {$wpdb->prefix}timesheet_client_projects p
			join {$wpdb->prefix}timesheet_clients c on p.ClientId = c.ClientId";
		if ($mine_only == 1) {
			$sql = $sql." inner join {$wpdb->prefix}timesheet_clients_users tcu on p.clientid = tcu.clientid and tcu.user_id = {$user_id}";
		}
			$sql = $sql." 
			where p.notes <> '' 
				 ";
				
				if ($_GET['page'] == 'approve_timesheet') { #Approval Menu
					$sql = $sql . "and p.ProjectId in (select ProjectId from {$wpdb->prefix}timesheet t where t.week_complete = 1
							 and t.approved = 0 and t.invoiced = 0 ";
					if ((isset($_GET['show_retainers'])) && (isset($_GET['hide_nonretainers']))){
						$sql = $sql . " and p.IsRetainer = 1 ";
					} else if ((!isset($_GET['show_retainers'])) && (!isset($_GET['hide_nonretainers']))) {
						$sql = $sql . " and p.IsRetainer = 0 ";
					}
					$sql = $sql . " )";
				} else if ($_GET['page'] == 'invoice_timesheet') { #Invoice menu
						$sql = $sql . "and p.ProjectId in (select ProjectId from {$wpdb->prefix}timesheet t where t.week_complete = 1
						and t.approved = 1 and t.invoiced = 0 ";
					if ((isset($_GET['show_retainers'])) && (isset($_GET['hide_nonretainers']))) {
						$sql = $sql . " and p.IsRetainer = 1 ";
						if (isset($_GET['retainer_type'])) {
							$sql = $sql . " and p.retainer_id = " . intval($_GET['retainer_type']);
						}
					} else if ((!isset($_GET['show_retainers'])) && (!isset($_GET['hide_nonretainers']))) {
						$sql = $sql . " and p.IsRetainer = 0 ";
					}
					$sql = $sql . " )";
				} else if ($_GET['page'] == 'search_timesheet') { #My Dashboard
					if (!isset($_GET['include_completed'])) {
						$sql = $sql . " and ProjectId in (select ProjectId from {$wpdb->prefix}timesheet t where t.week_complete = 0)";
					}
				}

			$sql = $sql . "
			order by ClientName, ProjectName";

		$clients = $db->get_results($sql);

		if ($clients) {
			echo "<tr><td><table border='1' cellpadding='0' cellspacing='0' width='50%'><tr><td>Client Name</td><td>Project Name</td><td>Notes</td></tr>";
			foreach ($clients as $client) {
				echo "<tr><td>{$client->ClientName}</td><td>{$client->ProjectName}</td><td><textarea cols='50' disabled>{$this->esc_textarea($client->notes)}</textarea></td></tr>";
			}
			echo "</table>";
		} else {
			echo '<tr><td class="notice notice-info "><p>No project notes match filter for active time sheets.</p>';
		}
				echo "</td></tr></table>";
	}



	function is_match($v1, $v2, $return, $debug=0) {
		if ($debug==1) {
			var_dump($v1);
			var_dump($v2);
		}
		if (trim($v1)==trim($v2)) {
			return $return;
		} else {
			return "";
		}
	}


	function replace($search, $replace, $value) {
		return str_replace($search, $replace, $value);
	}

	function f_date($date) {
		$options = get_option('time_sheets');
		$user = wp_get_current_user();
		$user_id = $user->ID;

		if ($options['override_date_format'] == 'system_defined') {
			$date_format = get_option('date_format');
		} else {
			$date_format = $options['new_date_format'];

			if ($options['user_specific_date_format']) {
				if (get_user_option('user_date_format', $user_id)) {
					$date_format = get_user_option('user_date_format', $user_id);
				}
			}
		}

		return date_i18n($date_format, strtotime($date));
	}

	function mysql_date($date) {
		return date("Y-m-d", strtotime($date));
	}

	function send_email ($to, $subject, $body) {
		global $wpdb;
		$options = get_option('time_sheets');
		$db = new time_sheets_db();

		if(!isset($options['enable_email'])) {
			return;
		}
		
		$sql = "insert into {$wpdb->prefix}timesheet_emailqueue 
				(send_to, send_from_email, send_from_name, subject, message_body, entered_on)
				values (%s, %s, %s, %s, %s, now())";
		$parms = array($to, $options['email_from'], $options['email_name'], $subject, $body);

		$db->query($sql, $parms);
		
		if (isset($options['show_email_notice'])) {
			echo "<div if='message' class='updated'><p>Email to {$to} queued.</p></div>";
		}

	}

	function remove_br($string) {
		$string = str_replace("\'", "'", $string);
		return nl2br($string);
	}

	function enqueue_js() {

		wp_enqueue_script ("jquery", '', array(), "1.11.0", false);
		wp_enqueue_script ("polyfiller", plugins_url( 'js/minified/polyfiller.js' , __FILE__), array(), false, false);
		

	}

	function show_datestuff() {

		?>
  <style type="text/css">
    .hide-replaced.ws-inputreplace {
    display: none !important;
}
.input-picker .picker-list td > button.othermonth {
    color: #888888;
    background: #fff;
}
.ws-inline-picker.ws-size-2, .ws-inline-picker.ws-size-4 {
    width: 49.6154em;
}
.ws-size-4 .ws-index-0, .ws-size-4 .ws-index-1 {
    border-bottom: 0.07692em solid #eee;
    padding-bottom: 1em;
    margin-bottom: 0.5em;
}
.picker-list.ws-index-2, .picker-list.ws-index-3 {
    margin-top: 3.5em;
}
div.ws-invalid input {
    border-color: #c88;
}
.ws-invalid label {
    color: #933;
}
div.ws-success input {
    border-color: #8c8;
}
form {
    #margin: 10px auto;
    #width: 700px;
    #min-width: 49.6154em;
    #border: 1px solid #000;
    #padding: 10px;
}
.form-row {
    padding: 5px 10px;
    margin: 5px 0;
}
label {
    display: block;
    margin: 3px 0;
}
.form-row input {
    width: 220px;
    padding: 3px 1px;
    border: 1px solid #ccc;
    box-shadow: none;
}
.form-row input[type="checkbox"] {
    width: 15px;
}
.date-display {
    display: inline-block;
    min-width: 200px;
    padding: 5px;
    border: 1px solid #ccc;
    min-height: 1em;
}
.show-inputbtns .input-buttons {
    display: inline-block;
}
  </style>
<!--
webshim.setOptions('forms-ext', {
    replaceUI: 'auto',
    types: 'date',
    date: {
        startView: 2,
        inlinePicker: true,
        classes: 'hide-inputbtns'
    }
});
-->



<script type='text/javascript'>//<![CDATA[

webshim.setOptions('forms', {
    lazyCustomMessages: true
});
//start polyfilling
webshim.polyfill('forms forms-ext');

//only last example using format display
$(function () {
    $('.format-date').each(function () {
        var $display = $('.date-display', this);
        $(this).on('change', function (e) {
            //webshim.format will automatically format date to according to webshim.activeLang or the browsers locale
            var localizedDate = webshim.format.date($.prop(e.target, 'value'));
            $display.html(localizedDate);
        });
    });
});
//]]> 

</script>

<?php

	}
}