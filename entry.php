<?php
class time_sheets_entry {

	function show_nothing() {
		echo "";
	}
	


	function process_timesheets() {
		if (isset($_POST['action'])) {
			$action = $_POST['action'];
		} else {
			if (isset($_GET['action'])) {
				$action = $_GET['action'];
			} else {
				$action = '';
			}
		}
		
		if ($action == "split2" || $action == "approve" || $action == "reject" || $action == 'save') {
			$entry = new time_sheets_entry();
			$time_sheets_main = new time_sheets_main();
			$queue_payroll = new time_sheets_queue_payroll();
			$queue_invoice = new time_sheets_queue_invoice();
			$queue_approval = new time_sheets_queue_approval();

			add_filter('admin_footer_text', array($entry, 'show_nothing'));
			add_filter( 'update_footer', array($entry, 'show_nothing'));

			remove_filter('admin_footer_text', array($time_sheets_main, 'show_footer'));
			remove_filter( 'update_footer', array($time_sheets_main, 'show_footer_version'));
			
			if (isset($_GET['action'])) {
				$action = $_GET['action'];
			} elseif (isset($_POST['action'])) {
				$action = $_POST['action'];
			} else {
				$action = "";
			}
			
			
			if (isset($_GET['page'])) {
				$page = $_GET['page'];
			} elseif (isset($_POST['page'])) {
				$page = $_POST['page'];
			} else {
				$page = "";
			}
			
			if ($action == "approve") {
				if ($page == "payroll_timesheet") {
					$queue_payroll->do_timesheet_payroll();
				}
				if ($page == "invoice_timesheet") {
					$queue_invoice->do_timesheet_invoicing();
				}
				if ($page == "approve_timesheet") {
					$queue_approval->do_timesheet_approval_processing();
				}
			} elseif ($action == "split2") {
				if ($page == "approve_timesheet") {
					$queue_approval->do_timesheet_split();
				}
			} else {
				if ($page == "payroll_timesheet") {
					$queue_payroll->do_timesheet_rejectpayroll();
				}
				if ($page == "invoice_timesheet") {
					$queue_invoice->do_timesheet_rejectinvoicing();
				}
				#Disable this
				#if ($page == "show_timesheet") {
				#	if ($this->validate_timesheet()=="true") {
				#		$timesheet_id = $this->save_timesheet();
				#		return $timesheet_id;
				#	}
					#$timesheet_id = $this->save_timesheet();
				#}
			}
		}
	}


	function email_on_submission($timesheet_id) {
		$options = get_option('time_sheets');
		$admin_url = get_admin_url();

		if (!isset($options['enable_email'])) {
			return;
		}

		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$user = wp_get_current_user();
		$user_id = $user->ID;

		$sql = "select user_login 
			from {$wpdb->prefix}timesheet t
			join {$wpdb->users} u on u.ID = t.user_id
			where t.timesheet_id = %d";
		$params = array($timesheet_id);
		$user_login = $db->get_var($sql, $params);

		$sql = "select user_email, display_name
		from {$wpdb->users} u
		join {$wpdb->prefix}timesheet_approvers_approvies aa on u.ID = aa.approver_user_id
			and aa.approvie_user_id = {$user_id}";

		$users = $db->get_results($sql);

		$sql = "select display_name
		from {$wpdb->users} u
		join {$wpdb->prefix}timesheet t on u.ID = t.user_id
		where t.timesheet_id = {$timesheet_id}";

		$display_user = $db->get_var($sql);
		$http = !empty($_SERVER['HTTPS']) ? "https" :  "http";



		$subject = "There are time sheet(s) pending approval.";
		$body = "A time sheet has been entered by {$display_user} and is pending approval.

It can be approved from the <a href='{$admin_url}admin.php?page=approve_timesheet'>approval menu</a>.";

		foreach ($users as $user) {
			$common->send_email ($user->user_email, $subject, $body);
		}

	}

	function enter_timesheet_sc() {
		#enable this
		if (isset($_POST['action']) && $_POST['action'] == 'save') {
		#	if ($this->validate_timesheet()=="true") {
		#		$timesheet_id = $this->save_timesheet();
		#		
		#	}
		} else {
			$timesheet_id = (isset($_GET['timesheet_id']) ? intval($_GET['timesheet_id']) : "");
		}
		#global $wpdb;
		#if ($_POST['submit'] == 'Save Timesheet') {
		#	$db = new time_sheets_db();

		#	$user_id = get_current_user_id();

		#	if ($_POST['timesheet_id']) {
		#		$timesheet_id = $_POST['timesheet_id'];
		#	} else {
#
		#		$sql = "select max(timesheet_id) 
		#		from {$wpdb->prefix}timesheet
		#		where user_id={$user_id}";
#
		#		$timesheet_id = $db->get_var($sql);
		#	}
		#}
		#echo $timesheet_id;
		$page = $this->show_timesheet($timesheet_id, 0);
		return $page;
	}
	
	function enter_timesheet() {
		#enable this
		#if ($_POST['action'] == 'save') {
		#	if ($this->validate_timesheet()=="true") {
		#		$timesheet_id = $this->save_timesheet();
		#	}
		#}
		#echo "test1;";
		
		
		global $wpdb;
		
		$db = new time_sheets_db();
		
		$user = wp_get_current_user();
		$user_id = $user->ID;
		
		if ($_POST['submit'] == 'Save Timesheet' && isset($timesheet_id)) {
			if ($_POST['timesheet_id']) {
				$timesheet_id = $_POST['timesheet_id'];
			} else {

				$sql = "select max(timesheet_id) 
				from {$wpdb->prefix}timesheet
				where user_id={$user_id}";

				$timesheet_id = $db->get_var($sql);
			}
	}

		$this->show_timesheet($timesheet_id);
	}


	function check_overages($timesheet_id, $send=0) {
		global $wpdb;
		
		$options = get_option('time_sheets');
		
		$project_trigger_percent = (isset($options['project_trigger_percent'])) ? $options['project_trigger_percent'] : 80;
		$email_address_on_project_over = (isset($options['email_address_on_project_over'])) ? $options['email_address_on_project_over'] : 'noreply@nothing';
		
		if (!is_numeric($project_trigger_percent)) {
			$project_trigger_percent = 100;
		}
		
	
		$db = new time_sheets_db();
		$common = new time_sheets_common();

		$sql = "select a.MaxHours, a.HoursUsed, a.ProjectName, tc.ClientName, tc.ClientId, a.IsRetainer, trim.MonthlyHours, b.week_complete, a.max_monthly_bucket
		from {$wpdb->prefix}timesheet_client_projects a
		join {$wpdb->prefix}timesheet b on a.ProjectId = b.ProjectId
		join {$wpdb->prefix}timesheet_clients tc on a.ClientId = tc.ClientId
		left outer join {$wpdb->prefix}timesheet_recurring_invoices_monthly trim on a.ClientId = trim.client_id
		 where timesheet_id=%d";
		$params=array($timesheet_id);
		$project = $db->get_row($sql, $params);
		
		$send = 1;
		
		if (isset($project) ) {
			$MaxHours = $project->MaxHours;
			$HoursUsed = $project->HoursUsed;
			$RealHoursUsed = $project->HoursUsed;
			
			if ($project->IsRetainer==1) {
				$LastMonthMaxHours = $project->MaxHours-$project->MonthlyHours;
				$HoursUsed = $project->HoursUsed - $LastMonthMaxHours;
				$MaxHours = $project->MonthlyHours;

			}
			
			
			if ($project->week_complete==1) {
				$send=0;
			}
		
		
			if ($send==1 && isset($options['enable_email']) && $email_address_on_project_over <> "" && $project->IsRetainer==0 && $project->MaxHours<>0) {
				$Sales = "The sales team has been notified that this project may require additional hours.";
			} else {
				$Sales = "Contact the Sales team is assistance is needed.";
			}

			if ($HoursUsed > ($project_trigger_percent/100)*$MaxHours && $MaxHours && !isset($project->max_monthly_bucket)) {
				echo "<div class='notice notice-warning'><p>The project '{$project->ProjectName}' for '{$project->ClientName}' has used {$project_trigger_percent}% or more of its hours ({$RealHoursUsed} hours used). Be sure that the client approves of hours over the project max of <B>{$project->MaxHours}</b> hours. {$Sales}</p></div>";
			
				if ($send==1 && $email_address_on_project_over <> "" && $project->IsRetainer==0 && $project->MaxHours<>0) {
					$common->send_email($options['email_address_on_project_over'], "Project '{$project->ProjectName}' for '{$project->ClientName}' has used {$project_trigger_percent}% of its hours", "The project '{$project->ProjectName}' for '{$project->ClientName}' has used {$project_trigger_percent}% of its hours.
	<P>
	If the project is not wrapping up, work with the team member working on this project to ensure any needed approvals from the client are received and/or a new SOW is written.
	<P>
	If the project is wrapping up, any post project work should be prepped.
	<P>
	(Sent by the time sheet system)
	<P><P>
	-----------------------------------------------------------
	<P>
	");
				}
			}
		}
	}

	function validate_timesheet(&$output='') {
		$passed = true;
		
		$output = '';

		if (!isset($_POST['start_date'])) {
			$output = $output . '<div class="notice notice-error is-dismissible"><p>The week start date is required.</p></div>';
			$passed="false";
		}

		if (!isset($_POST['ClientId']) || (isset($_POST['ClientId']) && $_POST['ClientId'] == -2)) {
			$output = $output .  '<div class="notice notice-error is-dismissible"><p>The client name is required.</p></div>';
			$passed="false";
		}

		if (!isset($_POST['ProjectId'])) {
			$output = $output . '<div class="notice notice-error is-dismissible"><p>A project is required.</p></div>';
			$passed="false";
		}

		return $passed;
	}

	function int_to_weekday($week_starts, $days_to_inc) {
		$value = $week_starts+$days_to_inc;
		if ($value > 6) {
			$value = $value-7;
		}

		if ($value==0) {
			$weekday = 'Sunday';
		}elseif ($value==1) {
			$weekday = 'Monday';
		}elseif ($value==2) {
			$weekday = 'Tuesday';
		}elseif ($value==3) {
			$weekday = 'Wednesday';
		}elseif ($value==4) {
			$weekday = 'Thursday';
		}elseif ($value==5) {
			$weekday = 'Friday';
		}elseif ($value==6) {
			$weekday = 'Saturday';
		}

		return $weekday;
	}

	function request_client_access_save($client_id) {
		global $wpdb;
		
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');


		$sql = "select ClientName from {$wpdb->prefix}timesheet_clients where ClientId = %d";
		$params = array($client_id);
		$client = $db->get_row($sql, $params);

		$sql = "select uuid() as uuid";
		$uuid = $db->get_row($sql);

		$sql = "insert into {$wpdb->prefix}timesheet_client_users_approval_queue
			(request_id, request_created, user_id, client_id, status)
			values
			(%s, now(), %d, %d, 1)";
		$params = array($uuid->uuid, $user_id, $client_id);
		$db->query($sql, $params);

		$sql = "insert into {$wpdb->prefix}timesheet_client_users_approval_queue_log
			(request_id, log_created, status, user_id)
			values
			(%s, now(), 1, %d)";
		$params = array($uuid->uuid, $user_id);
		$db->query($sql, $params);

		if (!isset($options['enable_requesting_access_self_approve'])) {
			$http = !empty($_SERVER['HTTPS']) ? "https" :  "http";
			$subject = "User {$user->display_name} is requesting access to a client";
			$body = "The user '{$user->display_name}' is requesting access to the client '{$client->ClientName}'. <a href='{$http}://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}?page=show_timesheet&subpage=approve_request&request_id={$uuid->uuid}'>Approve Request</a>  <a href='{$http}://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}?page=show_timesheet&subpage=deny_request&request_id={$uuid->uuid}'>Deny Request</a>.";

			$sql = "select user_email 
				from {$wpdb->prefix}users u
				join {$wpdb->prefix}timesheet_manage_client_users mcu on u.ID = mcu.user_id";
			$users = $db->get_results($sql);

			if ($users) {
				foreach ($users as $user) {
					$common->send_email($user->user_email, $subject, $body );
				}
			}

			return "<div class='notice notice-success'><p>Access to the requested client has been submitted. You will be contacted with the status of the request. The request ID is {$uuid->uuid}.</p></div>";

		} else {
			$access = $this->request_client_access_approve($uuid->uuid);

			return "<div class='notice notice-success'><p>Access to the requested client has been completed</p></div>";
		}
		


	}

	function request_client_access_approve($request_id) {
		global $wpdb;
		
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$user = wp_get_current_user();
		$user_id = $user->ID;

		$sql = "select * from {$wpdb->prefix}timesheet_client_users_approval_queue where request_id = %s and status = 1";
		$params = array($request_id);
		$request = $db->get_row($sql, $params);

		if ($request) {

			$sql = "select count(*) ct from {$wpdb->prefix}timesheet_clients_users where ClientId = {$request->client_id} and user_id = {$request->user_id}";
			$access_count = $db->get_row($sql);

			if ($access_count->ct == 0) {
				$sql = "insert into {$wpdb->prefix}timesheet_clients_users
					(ClientId, user_id)
					values
					({$request->client_id}, {$request->user_id})";
				$db->query($sql);
	
				$sql = "insert into {$wpdb->prefix}timesheet_client_users_approval_queue_log
						(request_id, log_created, status, user_id)
						values
						({$request->request_id}, now(), 2, {$user_id}";
				$db->query($sql);
	
				$sql = "update {$wpdb->prefix}timesheet_client_users_approval_queue
						set status = 2,
						approved_by = %d,
						approved_on = now()
					where request_id = %s";
				$params = array($user_id, $request->request_id);
				$db->query($sql, $params);

				$sql = "select user_email from {$wpdb->prefix}users where ID = {$request->user_id}";
				$user = $db->get_row($sql);

				$sql = "select ClientName from {$wpdb->prefix}timesheet_clients where ClientId = %d";
				$params = array($request->client_id);
				$client = $db->get_row($sql, $params);
	
				$subject = "Your access request for the client {$client->ClientName} has been approved";
				$body = "Your access request for the client {$client->ClientName} has been approved";

				$common->send_email($user->user_email, $subject, $body);

				return "<div class='notice notice-success'><p>Request {$request->request_id} has been approved and completed.</p></div>";
			} else {

				$sql = "insert into {$wpdb->prefix}timesheet_client_users_approval_queue_log
						(request_id, log_created, status, user_id)
						values
						({$request->request_id}, now(), 3, {$user_id}";
				$db->query($sql);

				$sql = "update {$wpdb->prefix}timesheet_client_users_approval_queue
						set status = 3,
						approved_by = %d,
						approved_on = now()
					where request_id = %s";
				$params = array($user_id, $request->request_id);
				$db->query($sql, $params);

				return "<div class='notice notice-warning'><p>Request {$request->request_id} has been completed. The user already has access to this client.</p></div>";
			}
		} else {
			return "<div class='notice notice-error'><p>This request can not be found, or has been completed.</p></div>";
		}
	}

	function request_client_access_deny($request_id) {
		global $wpdb;
		
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$user = wp_get_current_user();
		$user_id = $user->ID;

		$sql = "select * from {$wpdb->prefix}timesheet_client_users_approval_queue where request_id = %s and status = 1";
		$params = array($request_id);
		$request = $db->get_row($sql, $params);

		if ($request) {

			$sql = "insert into {$wpdb->prefix}timesheet_client_users_approval_queue_log
					(request_id, log_created, status, user_id)
					values
					({$request->request_id}, now(), 3, {$user_id}";
			$db->query($sql);

			$sql = "update {$wpdb->prefix}timesheet_client_users_approval_queue
					set status = 3,
					approved_by = %d,
					approved_on = now()
				where request_id = %s";
			$params = array($user_id, $request->request_id);
			$db->query($sql, $params);

			$sql = "select user_email from {$wpdb->prefix}users where ID = {$request->user_id}";
			$user = $db->row($sql);

			$sql = "select ClientName from {$wpdb->prefix}timesheet_clients where ClientId = %d";
			$params = array($request->client_id);
			$client = $db->get_row($sql, $params);
	
			$subject = "Your access request for the client {$client->ClientName} has been denied";
			$body = "Your access request for the client {$client->ClientName} has been denied. Please contact the management team for more information.";

			$common->send_email($user->user_email, $subject, $body);

	
			return "<div class='notice notice-error'><p>Request {$request->request_id} has been canceled.</p></div>";
		} else {
			return "<div class='notice notice-error'><p>This request can not be found, or has been completed.</p></div>";

		}
	}


	function request_client_access() {
		global $wpdb;
		
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;


		$page = '';

		if (isset($_GET['clientid'])) {
			$page = $page . $this->request_client_access_save(intval($_GET['clientid']));
		}


		$sql = "select distinct ClientName, c.ClientId 
			from {$wpdb->prefix}timesheet_clients c
			join {$wpdb->prefix}timesheet_client_projects cp on c.ClientId = cp.ClientId
				and cp.Active = 1
			where c.ClientId not in (select ClientId from {$wpdb->prefix}timesheet_clients_users where user_id = {$user_id})
			order by ClientName";

		$clients = $db->get_results($sql);

		$page = $page . "<form name='timesheet'><p>Select a client from the list which you need access to: ";

		if ($clients) {
			$page = $page . "<select name='clientid'>";
			foreach ($clients as $client) {
				$page = $page . "<option value='{$client->ClientId}'>{$client->ClientName}</option>";
			}
			$page = $page . "</select><p><input type='submit' name='submit' value='Submit Request' class='button-primary'>";
		} else {
			$page = $page . "<p>There are no clients entered.";
		}

		$page = $page . "<input type='hidden' name='page' value='show_timesheet'>
				 <input type='hidden' name='subpage' value='request_client_access'></form>";

		return $page;
	}

	function show_timesheet($timesheet_id=0, $show=1) {
		global $wpdb;
		#global $timesheet_id;
		
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		//$main = new time_sheets_main();
		$settings = new time_sheets_settings();
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		$allow_overtime = isset($options['allow_overtime']) ? $options['allow_overtime'] : "";
		$currency_char = isset($options['currency_char']) ? $options['currency_char'] : "";
		$show_overtime_when_empty = isset($options['show_overtime_when_empty']) ? 1 : 0;
		
		$enable_non_billable_time = isset($options['enable_non_billable_time']) ? 1 : 0;
		$show_non_billable_when_empty = isset($options['show_non_billable_when_empty']) ? 1 : 0;
		
		$page = '';

		if (isset($_GET['subpage'])) {
			if ($_GET['subpage'] == 'request_client_access') {
				$page = $page . $this->request_client_access();
			}
			if ($_GET['subpage'] == 'approve_request') {
				$page = $page . $this->request_client_access_approve($_GET['request_id']);
			}
			if ($_GET['subpage'] == 'deny_request') {
				$page = $page . $this->request_client_access_deny($_GET['request_id']);
			}

			if ($show==1) {
				echo $page;
				return;
			} else {
				return $page;
			}
		}
		
 		if (isset($_GET['is_template']) || isset($_POST['is_template'])) {
			$table_name = "{$wpdb->prefix}timesheet_scheduled";
			$is_template = True;
		} else {
			$table_name = "{$wpdb->prefix}timesheet";
			$is_template = False;
		}
	
		$common->show_datestuff();

		$queue_approval = new time_sheets_queue_approval();
		$queue_invoice = new time_sheets_queue_invoice();
		$queue_payroll = new time_sheets_queue_payroll();

		$folder = plugins_url();


		
		if ($user_id == 0) {
			$page = $page .  "<div class='notice notice-error'><p>You must be logged in to view this page.</p></div>";
			return $page;
		}
		
		if (isset($_POST['action']) && $_POST['action'] == 'save') {
			if ($this->validate_timesheet($page1)=="true") {
				$timesheet_id = $this->save_timesheet();
				
			} else {
				$page = $page . $page1;
				$timesheet_id = -1;
			}
		}
		
		if (!$timesheet_id || $timesheet_id == 0 || $timesheet_id == "") {
			
			if (isset($_POST['timesheet_id']) || isset($_GET['timesheet_id'])  || isset($_GET['time_sheet_id']) ) {
				if (isset($_POST['timesheet_id'])) {
					$timesheet_id = intval($_POST['timesheet_id']);
				} elseif (isset($_GET['timesheet_id'])) {
					$timesheet_id = intval($_GET['timesheet_id']);
				} elseif (isset($_GET['time_sheet_id'])) {
					$timesheet_id = intval($_GET['time_sheet_id']);
				}
			} else {
				if (isset($_POST['submit']) && $_POST['submit'] == 'Save Timesheet') {
					$sql = "select max(timesheet_id) 
					from {$table_name}
					where user_id={$user_id}";

					$timesheet_id = $db->get_var($sql);
				}
			}
		}

		if (!$is_template) {
			$page = $page . $this->check_overages(intval($timesheet_id), 1);
		}

		if (isset($_POST['action']) && ($_POST['action']=="comment_to_timesheet")) {
			
			$timesheet_id_int = intval($_POST['timesheet_id']);
			$comment =  $common->esc_textarea($_POST['comment']);
			$timesheet_action = intval($_POST['timesheet_action']);
			
			check_admin_referer('timesheet_edit_timesheet');
			
			if (!$comment && $timesheet_rejected=1) {
				$page = $page . "<div class='notice notice-error'>Time sheets can not be rejected without a comment.</div>";
			} else {			
				$this->save_audit_comment($timesheet_id, $comment, $timesheet_action);
			}
		}
		
		$sql = "select t.*, u.* ,tcp.*, 
			cs.display_name cs_display_name,
			cps.display_name cps_display_name, 
			tcs.display_name tcs_display_name, 
			tcps.display_name tcps_display_name";
		if (isset($options['allow_money_based_retainers'])) {
			$sql = $sql . ", et.name as 'TitleName', et2.name as 'TitleNameOverride'";	
		}
			
		$sql = $sql . " 
		from {$table_name} t 
		join {$wpdb->users} u on t.user_id = u.ID 
		left outer join {$wpdb->prefix}timesheet_client_projects tcp on t.ProjectId = tcp.ProjectId 
		
		JOIN {$wpdb->prefix}timesheet_client_projects cp ON t.ProjectId = cp.ProjectId
		JOIN {$wpdb->prefix}timesheet_clients c ON cp.ClientId = c.ClientId
		left outer join {$wpdb->users} cs on c.sales_person_id = cs.ID
		left outer join {$wpdb->users} cps on cp.sales_person_id = cps.ID
		left outer join {$wpdb->users} tcs on c.technical_sales_person_id = tcs.ID
		left outer join {$wpdb->users} tcps on cp.technical_sales_person_id = tcps.ID
		";
		if (isset($options['allow_money_based_retainers']) ){
			$sql = $sql . "left outer join {$wpdb->prefix}timesheet_employee_title_join etj on u.ID = etj.user_id
			left outer join {$wpdb->prefix}timesheet_customer_project_employee_title_override peto on peto.user_id = u.ID
				and peto.project_id = t.ProjectId
			left outer join {$wpdb->prefix}timesheet_employee_titles et on etj.title_id = et.title_id
			left outer join {$wpdb->prefix}timesheet_employee_titles et2 on peto.title_id = et2.title_id";
		}
		
		$sql = $sql . "
		where timesheet_id=%d";
		$params = array($timesheet_id);
		

		if ($timesheet_id) {
			$timesheet = $db->get_row($sql, $params);
			$timesheet_user = $timesheet->display_name;
			$timesheet_user_id = $timesheet->user_id;
			$timesheet_id_int = $timesheet_id;

			if ((!$queue_approval->employee_approver_check('true') && !$queue_invoice->employee_invoicer_check() && !$queue_payroll->employee_payroll_check() ) && $timesheet_user_id <> $user_id) {
				$page = $page .  "Only supervisors can view other employees time sheets.";
				return;
			}
		} else {
			$timesheet = (object) $_POST;
			$timesheet_user_id = $user_id;

			$timesheet_id_int = 0;
		}
		
		

		$page = $page .  "<br><H2>Weekly Time Sheet";
		if ($is_template) {
			$page = $page . " Template";
		}
		$page = $page .  "</H2>";

		$disable_object="";
		if (isset($timesheet->week_complete) && ($timesheet->week_complete=='1')) {
				$disable_object= " disabled";
		}
	
		$page = $page .  "<form name='timesheet' method='POST' autocomplete='off' onsubmit='submit.disabled=true;is_template.disabled=false'>";
		//class='ws-validate' 
		
		$nonce = wp_create_nonce('timesheet_edit_timesheet'); 
		$page = $page . "<input type='hidden' name='_wpnonce' value='{$nonce}'>";

		

		
		if ($timesheet_id_int == 0) {
			$defaultDate = get_user_option('timesheet_defaultdate', $user_id);
			if (!$defaultDate) {
				$defaultDate='Monday Last Week';
			}
			$start_date = date('Y-m-d', strtotime($defaultDate));
			$clientid = -1;
		} else {
			$start_date = date("Y-m-d", strtotime($timesheet->start_date));
			$clientid = $timesheet->ClientId;
		}
		$week_starts = (isset($options['week_starts'])) ? $options['week_starts'] : 0;
		if (date('w', strtotime($start_date)) !=  $week_starts) {
			$page = $page .  "<div class='notice notice-warning is-dismissible'><p>This time sheet does not start on a {$this->int_to_weekday($options['week_starts'], 0)}. Please change the date to a {$this->int_to_weekday($options['week_starts'], 0)}.</p></div>";
		}

		$page = $page .  "<table>";
		if (isset($timesheet_user)) {
			$page = $page .  "<TR><TD>" . (($is_template == true)?"Template":"Timesheet") . " Number:</TD><TD>{$timesheet_id}</TD></TR>";
			$page = $page .  "<TR><TD>" . (($is_template == true)?"Template":"Timesheet") . " For:</TD><TD>{$timesheet_user}</TD></TR>";
			if (isset($options['allow_money_based_retainers']) && (isset($timesheet->week_complete) && ($timesheet->week_complete=='1'))){
				$page = $page . "<TR><TD>Employee Title";
				if (isset($timesheet->TitleNameOverride)) {
					$page = $page . " for Project";	
				}
				$page = $page . ":</TD><TD>";
				if (isset($timesheet->TitleNameOverride)) {
						$page = $page . $common->clean_from_db($timesheet->TitleNameOverride);
					} else {
						$page = $page . $common->clean_from_db($timesheet->TitleName);
				}
				$page = $page . "</TD></TR>";
			}
		}
		if ((isset($timesheet->invoiceid)) && ($timesheet->invoiceid != 0)) {
			$page = $page . "<TR><TD>Invoice ID:</TD><TD>{$timesheet->invoiceid}</TD></TR>";
		}
		$page = $page . "<tr><td>Week Start Date:</td><td>
		<input type='date' name='start_date' value='{$start_date}'{$disable_object} onChange='setupDates()' />
		</td></tr>";
		//<div class='form-row show-inputbtns'> data-date-inline-picker='false' data-date-open-on-focus='true' </div>

		if ($timesheet_id_int == 0) {
			$ProjectId = -1;
			$clientid = 0;
		} else {
			$ProjectId = $timesheet->ProjectId;
			$clientid = $timesheet->ClientId;
		}
		
		if (isset($options['hide_client_project'])) {
		
			#$Clients = $common->return_clients_for_user($timesheet_user_id);
			$Projects = $common->return_projects_for_user($timesheet_user_id);
			
			if (sizeof($Projects)==1) {
			 $page = $page .  "<tr><td><input type='hidden' name='ClientId' value='{$Projects[0]->ClientId}'>
			 <input type='hidden' name='ProjectId' value='{$Projects[0]->ProjectId}'>
			 </td></tr>";
			} else {
				$js_projects = $common->draw_clients_and_projects($clientid, $ProjectId, $timesheet_id_int, $disable_object, $timesheet, $clientList);
			}
		} else {
			$js_projects = $common->draw_clients_and_projects($clientid, $ProjectId, $timesheet_id_int, $disable_object, $timesheet, $clientList);
		}
		$page = $page . $js_projects[1];

		
		if (isset($timesheet->notes)) {
			$page = $page .  "<tr><td>Project Notes:</td><td><textarea rows='4' cols='50' >{$common->esc_textarea($timesheet->notes)}</textarea></td></tr>";
		}
		if (isset($timesheet->MaxHours) && $timesheet->MaxHours <> 0) {
			$page = $page . "<tr><td>Maximum Project Hours:</td><td>{$timesheet->MaxHours}</td></tr>";
		}
		if (isset($options['sales_override']) && $options['sales_override']=='project') {
			if (isset($timesheet->cps_display_name)) {
				$page = $page . "<tr><td>Sales Person:</td><td>{$timesheet->cps_display_name}</td></tr>";
			}
			if (isset($timesheet->tcps_display_name)) {
				$page = $page . "<tr><td>Technical Sales Person:</td><td>{$timesheet->tcps_display_name}</td></tr>";
			}
		} else {
			if (isset($timesheet->cs_display_name)) {
				$page = $page . "<tr><td>Sales Person:</td><td>{$timesheet->cs_display_name}</td></tr>";
			}
			if (isset($timesheet->tcs_display_name)) {
				$page = $page . "<tr><td>Technical Sales Person:</td><td>{$timesheet->tcs_display_name}</td></tr>";
			}
		}
		$page = $page .  "</table>";
		$week_starts = (isset($options['week_starts'])) ? $options['week_starts'] : 0;
		$page = $page .  "<table><tr align='center'><td></td><td>{$this->int_to_weekday($week_starts, 0)}</td><td>{$this->int_to_weekday($week_starts, 1)}</td><td>{$this->int_to_weekday($week_starts, 2)}</td><td>{$this->int_to_weekday($week_starts, 3)}</td><td>{$this->int_to_weekday($week_starts, 4)}</td><td>{$this->int_to_weekday($week_starts, 5)}</td><td>{$this->int_to_weekday($week_starts, 6)}</td></tr>";
		
		$monday_hours = isset($timesheet->monday_hours) ? $timesheet->monday_hours : '0';
		$tuesday_hours = isset($timesheet->tuesday_hours) ? $timesheet->tuesday_hours : '0';
		$wednesday_hours = isset($timesheet->wednesday_hours) ? $timesheet->wednesday_hours : '0';
		$thursday_hours = isset($timesheet->thursday_hours) ? $timesheet->thursday_hours : '0';
		$friday_hours = isset($timesheet->friday_hours) ? $timesheet->friday_hours : '0';
		$saturday_hours = isset($timesheet->saturday_hours) ? $timesheet->saturday_hours : '0';
		$sunday_hours = isset($timesheet->sunday_hours) ? $timesheet->sunday_hours : '0';
		
		$monday_ot = isset($timesheet->monday_ot) ? $timesheet->monday_ot : '0';
		$tuesday_ot = isset($timesheet->tuesday_ot) ? $timesheet->tuesday_ot : '0';
		$wednesday_ot = isset($timesheet->wednesday_ot) ? $timesheet->wednesday_ot : '0';
		$thursday_ot = isset($timesheet->thursday_ot) ? $timesheet->thursday_ot : '0';
		$friday_ot = isset($timesheet->friday_ot) ? $timesheet->friday_ot : '0';
		$saturday_ot = isset($timesheet->saturday_ot) ? $timesheet->saturday_ot : '0';
		$sunday_ot = isset($timesheet->sunday_ot) ? $timesheet->sunday_ot : '0';
		
		$monday_nb = isset($timesheet->monday_nb) ? $timesheet->monday_nb : '0';
		$tuesday_nb = isset($timesheet->tuesday_nb) ? $timesheet->tuesday_nb : '0';
		$wednesday_nb = isset($timesheet->wednesday_nb) ? $timesheet->wednesday_nb : '0';
		$thursday_nb = isset($timesheet->thursday_nb) ? $timesheet->thursday_nb : '0';
		$friday_nb = isset($timesheet->friday_nb) ? $timesheet->friday_nb : '0';
		$saturday_nb = isset($timesheet->saturday_nb) ? $timesheet->saturday_nb : '0';
		$sunday_nb = isset($timesheet->sunday_nb) ? $timesheet->sunday_nb : '0';
		
		$total_ot = $monday_ot+$tuesday_ot+$wednesday_ot+$thursday_ot+$friday_ot+$saturday_ot+$sunday_ot;		
		$total_nb = $monday_nb+$tuesday_nb+$wednesday_nb+$thursday_nb+$friday_nb+$saturday_nb+$sunday_nb;	
		
		$page = $page .  "<tr align='center'><td></td><td><input type='text' name='monday_date' value='' size='5' style='width: 5em' disabled></td>
<td><input type='text' name='tuesday_date' value='' size='5' style='width: 5em' disabled></td>
<td><input type='text' name='wednesday_date' value='' size='5' style='width: 5em' disabled></td>
<td><input type='text' name='thursday_date' value='' size='5' style='width: 5em' disabled></td>
<td><input type='text' name='friday_date' value='' size='5' style='width: 5em' disabled></td>
<td><input type='text' name='saturday_date' value='' size='5' style='width: 5em' disabled></td>
<td><input type='text' name='sunday_date' value='' size='5' style='width: 5em' disabled></td></tr>";
		$page = $page .  "<tr><td>Hours</td><td><input type='number' name='monday_hours' min='0' max='24' step='0.25' style='width: 5em' value='{$monday_hours}'></td>";
		$page = $page .  "<td><input type='number' name='tuesday_hours' min='0' max='24' step='0.25' style='width: 5em' value='{$tuesday_hours}'></td>";
		$page = $page .  "<td><input type='number' name='wednesday_hours' min='0' max='24' step='0.25' style='width: 5em' value='{$wednesday_hours}'></td>";
		$page = $page .  "<td><input type='number' name='thursday_hours' min='0' max='24' step='0.25' style='width: 5em' value='{$thursday_hours}'></td>";
		$page = $page .  "<td><input type='number' name='friday_hours' min='0' max='24' step='0.25' style='width: 5em' value='{$friday_hours}'></td>";
		$page = $page .  "<td><input type='number' name='saturday_hours' min='0' max='24' step='0.25' style='width: 5em' value='{$saturday_hours}'></td>";
		$page = $page .  "<td><input type='number' name='sunday_hours' min='0' max='24' step='0.25' style='width: 5em' value='{$sunday_hours}'></td></tr>";
		$hide_ot = 0;
		$hide_nb = 0;
		
		//if ($show_overtime_when_empty==1 && isset($timesheet->week_complete) && $timesheet->week_complete=='1' && $total_ot==0) {
		//	$hide_ot = 1;
		//}
		
		if (isset($timesheet->week_complete) && $timesheet->week_complete=='1') {  //Week is complete
			if ($show_overtime_when_empty==0 && $total_ot == 0) {
				$hide_ot = 1;
			}
			if ($show_non_billable_when_empty==0 && $total_nb == 0) {
				$hide_nb = 1;
			}
		} else { //Week is not complete
			if ($enable_non_billable_time==0) {
				$hide_nb = 1;
			}
		}
		
		if (($allow_overtime) && ($hide_ot == 0)) {
			$monday_ot_style = ($monday_ot!=0 && $timesheet->week_complete=='1') ? "style='background-color:Tomato;'" : "";
			$tuesday_ot_style = ($tuesday_ot!=0 && $timesheet->week_complete=='1') ? "style='background-color:Tomato;'" : "";
			$wednesday_ot_style = ($wednesday_ot!=0 && $timesheet->week_complete=='1') ? "style='background-color:Tomato;'" : "";
			$thursday_ot_style = ($thursday_ot!=0 && $timesheet->week_complete=='1') ? "style='background-color:Tomato;'" : "";
			$friday_ot_style = ($friday_ot!=0 && $timesheet->week_complete=='1') ? "style='background-color:Tomato;'" : "";
			$saturday_ot_style = ($saturday_ot!=0 && $timesheet->week_complete=='1') ? "style='background-color:Tomato;'" : "";
			$sunday_ot_style = ($sunday_ot!=0 && $timesheet->week_complete=='1') ? "style='background-color:Tomato;'" : "";
 

			$page = $page .  "<tr><td>Overtime</td><td><input type='number' name='monday_ot' min='0' max='24' step='0.25' style='width: 5em' $monday_ot_style value='{$monday_ot}'></td>";
			$page = $page .  "<td><input type='number' name='tuesday_ot' min='0' max='24' step='0.25' style='width: 5em' $tuesday_ot_style value='{$tuesday_ot}'></td>";
			$page = $page .  "<td><input type='number' name='wednesday_ot' min='0' max='24' step='0.25' style='width: 5em' $wednesday_ot_style value='{$wednesday_ot}'></td>";
			$page = $page .  "<td><input type='number' name='thursday_ot' min='0' max='24' step='0.25' style='width: 5em' $thursday_ot_style value='{$thursday_ot}'></td>";
			$page = $page .  "<td><input type='number' name='friday_ot' min='0' max='24' step='0.25' style='width: 5em' $friday_ot_style value='{$friday_ot}'></td>";
			$page = $page .  "<td><input type='number' name='saturday_ot' min='0' max='24' step='0.25' style='width: 5em' $saturday_ot_style value='{$saturday_ot}'></td>";
			$page = $page .  "<td><input type='number' name='sunday_ot' min='0' max='24' step='0.25' style='width: 5em' $sunday_ot_style value='{$sunday_ot}'></td></tr>";
		}
		
		if ($hide_nb == 0) {
			$page = $page .  "<tr><td>Non-Billable Time</td><td><input type='number' name='monday_nb' min='0' max='24' step='0.25' style='width: 5em' value='{$monday_nb}'></td>";
			$page = $page .  "<td><input type='number' name='tuesday_nb' min='0' max='24' step='0.25' style='width: 5em' value='{$tuesday_nb}'></td>";
			$page = $page .  "<td><input type='number' name='wednesday_nb' min='0' max='24' step='0.25' style='width: 5em' value='{$wednesday_nb}'></td>";
			$page = $page .  "<td><input type='number' name='thursday_nb' min='0' max='24' step='0.25' style='width: 5em' value='{$thursday_nb}'></td>";
			$page = $page .  "<td><input type='number' name='friday_nb' min='0'  max='24' step='0.25' style='width: 5em' value='{$friday_nb}'></td>";
			$page = $page .  "<td><input type='number' name='saturday_nb' min='0'  max='24' step='0.25' style='width: 5em'' value='{$saturday_nb}'></td>";
			$page = $page .  "<td><input type='number' name='sunday_nb' min='0' max='24' step='0.25' style='width: 5em' value='{$sunday_nb}'></td></tr>";
			
		}
		
		$page = $page ."</table>";
		
		$monday_desc = isset($timesheet->monday_desc) ? $common->esc_textarea($timesheet->monday_desc) : '';
		$tuesday_desc = isset($timesheet->tuesday_desc) ? $common->esc_textarea($timesheet->tuesday_desc) : '';
		$wednesday_desc = isset($timesheet->wednesday_desc) ? $common->esc_textarea($timesheet->wednesday_desc) : '';
		$thursday_desc = isset($timesheet->thursday_desc) ? $common->esc_textarea($timesheet->thursday_desc) : '';
		$friday_desc = isset($timesheet->friday_desc) ? $common->esc_textarea($timesheet->friday_desc) : '';
		$saturday_desc = isset($timesheet->saturday_desc) ? $common->esc_textarea($timesheet->saturday_desc) : '';
		$sunday_desc = isset($timesheet->sunday_desc) ? $common->esc_textarea($timesheet->sunday_desc) : '';

		if ($settings->user_can_enter_notes($timesheet_user_id, 'display_notes_toggle', 'display_notes_list')==1) {
			$page = $page .  "<BR>Daily Notes";
			$page = $page .  "<table><tr><td>{$this->int_to_weekday($options['week_starts'], 0)}</td><td><textarea name='monday_desc' cols='40' rows='2'>{$monday_desc}</textarea></td></tr>";
			$page = $page .  "<tr><td>{$this->int_to_weekday($options['week_starts'], 1)}</td><td><textarea name='tuesday_desc' cols='40' rows='2'>{$tuesday_desc}</textarea></td></tr>";
			$page = $page .  "<tr><td>{$this->int_to_weekday($options['week_starts'], 2)}</td><td><textarea name='wednesday_desc' cols='40' rows='2'>{$wednesday_desc}</textarea></td></tr>";
			$page = $page .  "<tr><td>{$this->int_to_weekday($options['week_starts'], 3)}</td><td><textarea name='thursday_desc' cols='40' rows='2'>{$thursday_desc}</textarea></td></tr>";
			$page = $page .  "<tr><td>{$this->int_to_weekday($options['week_starts'], 4)}</td><td><textarea name='friday_desc' cols='40' rows='2'>{$friday_desc}</textarea></td></tr>";
			$page = $page .  "<tr><td>{$this->int_to_weekday($options['week_starts'], 5)}</td><td><textarea name='saturday_desc' cols='40' rows='2'>{$saturday_desc}</textarea></td></tr>";
			$page = $page .  "<tr><td>{$this->int_to_weekday($options['week_starts'], 6)}</td><td><textarea name='sunday_desc' cols='40' rows='2'>{$sunday_desc}</textarea></td></tr></table>";
		}

		$per_diem = ($timesheet_id_int!=0 ? $common->is_match($timesheet->isPerDiem, '1', ' selected') : '');
		$actual = ($timesheet_id_int!=0 ? $common->is_match($timesheet->isPerDiem, '0', ' selected') : '');
		
		if ($settings->user_can_enter_notes($timesheet_user_id, 'display_expenses_toggle', 'display_expenses_list')==1) {
			$page = $page .  "Additional Information";
			$page = $page .  "<table border='1' cellspacing='0'><tr><td>
				<select name='isPerDiem' onChange='enablePerDiem()'>
					<option value='1'{$per_diem}>Days of Per Diem</option>
					<option value='0'{$actual}>Actual Food Costs</option>
				</select><br>
			</td><td>Per Diem City</td><td>Flight/Train Costs</td><td>Hotel Charges</td><td>Rental Car Charges</td><td>Taxi/Rideshare</td><td>Tolls</td><td>Mileage<br>({$options['distance_metric']} Driven)</td><td>Other Expenses</td></tr>";
			$per_diem_days = (isset($timesheet->per_diem_days) ? $timesheet->per_diem_days : '');
			$per_diem_city = (isset($timesheet->perdiem_city) ? $timesheet->perdiem_city : '');
			$flight_cost = (isset($timesheet->flight_cost) ? $timesheet->flight_cost : '');
			$hotel_charges = (isset($timesheet->hotel_charges) ? $timesheet->hotel_charges : '');
			$rental_car_charges = (isset($timesheet->rental_car_charges) ? $timesheet->rental_car_charges : '');
			$taxi = (isset($timesheet->taxi) ? $timesheet->taxi : '');
			$tolls = (isset($timesheet->tolls) ? $timesheet->tolls : '');
			$mileage = (isset($timesheet->mileage) ? $timesheet->mileage : '');
			$other_expenses = (isset($timesheet->other_expenses) ? $timesheet->other_expenses : '');
			$other_expenses_notes = (isset($timesheet->other_expenses_notes) ? $timesheet->other_expenses_notes : '');
			
			$page = $page .  "<tr><td align='center'><input type='number' name='per_diem_days 'min='0' step='0.01' style='width: 5em' value='{$per_diem_days}'></td>";
			$page = $page .  "<td align='center'><input type='text' name='perdiem_city' size='15' value='{$per_diem_city}'></td>";
			$page = $page .  "<td align='center'>{$currency_char}<input type='number' name='flight_cost' min='0' step='0.01' style='width: 5em' value='{$flight_cost}'></td>";
			$page = $page .  "<td align='center'>{$currency_char}<input type='number' name='hotel_charges'  min='0' step='0.01' style='width: 5em' value='{$hotel_charges}'></td>";
			$page = $page .  "<td align='center'>{$currency_char}<input type='number' name='rental_car_charges'  min='0' step='0.01' style='width: 5em' value='{$rental_car_charges}'></td>";
			$page = $page . "<td align='center'>{$currency_char}<input type='number' name='taxi'  min='0' step='0.01' style='width: 5em' value='{$taxi}'></td>";
			$page = $page .  "<td align='center'>{$currency_char}<input type='number' name='tolls'  min='0' step='0.01' style='width: 5em'  value='{$tolls}'></td>";
			$page = $page .  "<td align='center'><input type='number' name='mileage'  min='0' step='0.01' style='width: 5em' value='{$mileage}'></td>";
			$page = $page .  "<td align='center'>{$currency_char}<input type='number' name='other_expenses'  min='0' step='0.01' style='width: 5em' value='{$other_expenses}'></td></tr>";
			$page = $page .  "<tr><td valign='top'>Other Expense Notes:</td><td colspan='8'><textarea name='other_expenses_notes' cols='70' rows='8'>{$common->esc_textarea($other_expenses_notes)}</textarea></td></tr></table>";
		}

		$page = $page .  "<table border='0' cellpadding='0' cellspacing='0'><tr><td>Week Complete:</td><td><input type='checkbox' name='week_complete' onclick='weekCompleteChecked()' value='1'";
		if (isset($timesheet->week_complete) && $timesheet->week_complete=='1') {
			$page = $page .  " checked disabled";
		}
		$page = $page .  "></td></tr>";
		if (!isset($options['remove_embargo'])) {
			$page = $page .  "<tr><td>Project Complete:</td><td><input type='checkbox' name='project_complete' value='1'{$disable_object}";
			if (isset($timesheet->project_complete) && $timesheet->project_complete=='1') {
				$page = $page .  " checked";
			}
			$page = $page .  "> (If this is available, all project workers must select this for client to be billed as client is only billed upon project completion.)</td></tr>";
		}


		//Recuring Timesheet
		if (isset($options['allow_recurring_timesheets']) && ($is_template==true || $timesheet_id_int==0)) {
			$page = $page . "<tr><td>Make this a recurring timesheet?</td><td><input type='checkbox' name='is_template' value='1' onclick='switchRecuringFields()'" . (($is_template == true)?" disabled checked":"") . "></td></tr>";
			$page = $page . "<tr><td colspan='2'>
					<table>
						<tr>
							<td>
								Interval
							</td>
							<td>
							<select name='interval' disabled>
							<option value='w'{$common->is_match('w', (($timesheet_id_int==0)?'':$timesheet->frequency), ' selected')}>Weekly</option>
							<option value='m'{$common->is_match('m', (($timesheet_id_int==0)?'':$timesheet->frequency), ' selected')}>Monthly</option>
							<option value='q'{$common->is_match('q', (($timesheet_id_int==0)?'':$timesheet->frequency), ' selected')}>Quarterly</option>
							<option value='a'{$common->is_match('a', (($timesheet_id_int==0)?'':$timesheet->frequency), ' selected')}>Annualy</option>
							</select>
							</td>
						</tr>
						<tr>
							<td>
								Next Execution
							</td>
							<td>
								<input type='date' name='next_execution' value='" . (($timesheet_id_int==0)?'':date("Y-m-d", strtotime($timesheet->next_execution))) . "' disabled>
							</td>
						</tr>

						<tr>
							<td>
								Expires On
							</td>
							<td>
								<input type='date' name='expires' value='" . (($timesheet_id_int==0)?'':date("Y-m-d", strtotime($timesheet->expires_on))) . "' disabled> (optional)
							</td>
						</tr>
						<tr>
							<td colspan='2'>
								<input type='checkbox' name='delete_after_expiration' value='1' disabled ". ((($timesheet_id_int==0)?'':$timesheet->delete_after_expiration==1)?'checked':'')  . "> Delete template after expiration?
								<input type='hidden' name='template' value=''>
							</td>
						</tr>
					</table>
</td></tr>";
		}
		$page = $page .  "</table>";

		//if (!$is_template) {
			if ($timesheet_id_int==0 || $timesheet->week_complete=='0') {
				if (isset($timesheet->user_id)) {
					if ($timesheet->user_id == $user_id) {
						$page = $page .  "<br><input type='submit' value='Save Timesheet' name='submit' class='button-primary'>";
					}
				} else {
					$page = $page .  "<br><input type='submit' value='Save Timesheet' name='submit' class='button-primary'>";
				}
			}
		//}

		$page = $page .  "<input type='hidden' name='action' value='save'>";
		if ($timesheet_id != "") {
			$page = $page .  "<input type='hidden' name='timesheet_id' value='{$timesheet_id}'>";
			if ($is_template == true) {
				$page = $page .  "<input type='hidden' name='template_id' value='{$timesheet_id}'>";
			}
		}
		$page = $page .  "<input type='hidden' name='may_embargo' value='0'>";
		$page = $page .  "</form>";

		if (isset($timesheet->week_complete) && $timesheet->week_complete=='1' && !isset($options['hide_notes'])){ //Comments that are being audited for time sheets that are closed.
			$page = $page .  "<p>Time Sheet Comments<br><form name='timesheet_comments' method='POST' class='ws-validate' autocomplete='off'>";
		
			$nonce = wp_create_nonce('timesheet_edit_timesheet');
			$page = $page . "<input type='hidden' name='_wpnonce' value='{$nonce}'>";

			$page = $page . "<table border=0>";
			$page = $page . "<tr><td>Timesheet Action:</td><td><select name='timesheet_action'>
				<option value='1'>Reject Timesheet</option>
				<option value='2'>Comment & Email Submitter</option>
				<option value='3'>Comment & Do Not Send Email</option>

				</select></td></tr>
				<tr><td valign='top'>Comment:</td><td><textarea name='comment' cols='70' rows='3'></textarea></td></tr></table>";
			
			if ( ($queue_approval->employee_approver_check('true') || $queue_invoice->employee_invoicer_check() || $queue_payroll->employee_payroll_check() ) || ($timesheet_user_id == $user_id && $timesheet->approved==0 && isset($options['allow_users_to_reject']))) {
				if ( ($timesheet->invoiced==0) ) {
					$page = $page .  "<br><input type='submit' value='Save Note' name='submit' class='button-primary'>";
				} else {
					$page = $page .  "<br><div class='notice notice-warning is-dismissible'><p>Comments can not be saved for an already invoiced timesheet.</p></div>";
				}
			}
			$page = $page .  "<input type='hidden' name='action' value='comment_to_timesheet'>";
			$page = $page .  "<input type='hidden' name='timesheet_id' value='{$timesheet->timesheet_id}'>";
			$page = $page .  "<input type='hidden' name='may_embargo' value='0'>";
			$page = $page .  "</form>";
			
		}
		$sql = "select tca.entered_at, tca.comment, tca.timesheet_rejected, u.display_name, timesheet_rejected as timesheet_action
				from {$wpdb->prefix}timesheet_comment_audit tca 
				join {$wpdb->users} u on tca.entered_by = u.ID 
				where timesheet_id=%d
				order by entered_at desc";
		$params = array($timesheet_id);
		$comments = $db->get_results($sql,$params);

		if ($comments) {
			$page = $page . "<br>Entered Notes<br><table border='1' cellspacing='0'><tr><td>Date Entered</td><td>Entered By</td><td>Comment</td><td>Sent Email</td></tr>";
			foreach ($comments as $comment) {
				$page = $page . "<tr><td>{$comment->entered_at}</td><td>{$comment->display_name}</td><td>{$common->esc_textarea($comment->comment)}</td><td align='center'>";
				if ($comment->timesheet_action!=3) {
					$page = $page . "<img src='{$folder}/time-sheets/check.png' height='13' width='13'>";
				} else {
					$page = $page . "<img src='{$folder}/time-sheets/x.png' height='13' width='13'>";	
				}
				$page = $page . "</td></tr>";
			}
			$page = $page . "</table>";
		}

		
		$page = $page .  "


<script type='text/javascript'>

function setupDates() {
	var fake_date = new Date(timesheet.start_date.value);
	var js_start_date = new Date(fake_date.getFullYear(), fake_date.getMonth(), fake_date.getDate());

	//I have no idea why I have to do this, but it's wrong otherwise.
	//I think it's because javascript sucks.

	var n = js_start_date.getTimezoneOffset();
	
	if (n > 0) {
		js_start_date.setDate(js_start_date.getDate() + 1);
		//Need to figure this out, it's an issue with timezones. Until then, it'll just be broken for some people.
	}

	//I also have no idea why I need the +1 after getMonth(), but I do. I hate JavaScript.

	timesheet.monday_date.value = js_start_date.getMonth()+1 + '-' + js_start_date.getDate();
	js_start_date.setDate(js_start_date.getDate() + 1);
	timesheet.tuesday_date.value = js_start_date.getMonth()+1 + '-' + js_start_date.getDate();

	js_start_date.setDate(js_start_date.getDate() + 1);
	timesheet.wednesday_date.value = js_start_date.getMonth()+1 + '-' + js_start_date.getDate();

	js_start_date.setDate(js_start_date.getDate() + 1);
	timesheet.thursday_date.value = js_start_date.getMonth()+1 + '-' + js_start_date.getDate();

	js_start_date.setDate(js_start_date.getDate() + 1);
	timesheet.friday_date.value = js_start_date.getMonth()+1 + '-' + js_start_date.getDate();

	js_start_date.setDate(js_start_date.getDate() + 1);
	timesheet.saturday_date.value = js_start_date.getMonth()+1 + '-' + js_start_date.getDate();

	js_start_date.setDate(js_start_date.getDate() + 1);
	timesheet.sunday_date.value = js_start_date.getMonth()+1 + '-' + js_start_date.getDate();
}

function weekCompleteChecked() {

";
		if (isset($options['allow_recurring_timesheets']) && $timesheet_id_int==0) {
			$page = $page . "			if (timesheet.week_complete.checked == true) {
				timesheet.is_template.disabled = true;
			} else {
				timesheet.is_template.disabled = false;
				switchRecuringFields();
			}
	";
		}

$page = $page . "}

function switchRecuringFields() {
	if (timesheet.is_template.checked == true) {
		timesheet.interval.disabled = false;
		timesheet.next_execution.disabled = false;
		timesheet.expires.disabled = false;
		timesheet.delete_after_expiration.disabled = false;
		timesheet.week_complete.checked = false;
		timesheet.week_complete.disabled = true;
		timesheet.project_complete.disabled = true;
		timesheet.template.value=='true';
	} else {
		timesheet.interval.disabled = true;
		timesheet.next_execution.disabled = true;
		timesheet.expires.disabled = true;
		timesheet.delete_after_expiration.disabled = true;
		
		timesheet.week_complete.disabled = false;
		timesheet.template.value=='';
		ProjectChange()
	}
}
";

$page = $page . $common->client_and_projects_javascript($js_projects[0], 'timesheet', 1, $timesheet);
$projectlist = $js_projects[0];
		
$page = $page .  "
function isProjectBilled() {
";
	if (!isset($options['remove_embargo'])) {
		$page = $page .  "
	//var clients = '';
	var clients = {$clientList};

	if (clients.indexOf(timesheet.ClientId.value) != -1) {
		timesheet.project_complete.disabled = false;
		timesheet.may_embargo.value = 1;
	} else {
		timesheet.project_complete.disabled = true;
		timesheet.may_embargo.value = 0;
		timesheet.project_complete.checked = false;
	}";
	}
	$page = $page .  "
}

function isClientProjectBilled() {
	var projectlist = {$projectlist};

	var numberOfProjects = projectlist.length;
	//alert(numberOfProjects);
	
	for (var i = 0; i < numberOfProjects; i++) {
		project = projectlist[i];
		if (project['ProjectId'] == document.timesheet.elements['ProjectId'].value) { //timesheet.ProjectId.value) {
			//alert ('found project');
";
	if (!isset($options['remove_embargo'])) {
	$page = $page .  "		if (project['BillOnProjectCompletion'] == 1) {
				//alert('project is post billed');
				timesheet.project_complete.disabled = false;
				timesheet.may_embargo.value = 1;
			}";
	}
$page = $page .  "
		}
	}
}

function ProjectChange() {
	isProjectBilled();
	isClientProjectBilled();
}

setupDates()
isProjectBilled()
";
	if (isset($options['hide_client_project']) && isset($clients) && isset($projects) && count($clients)==1 && count($projects)==1) {
		#Nothing to do here as we're hiding those fields
	} else {
		$page = $page .  "resetProject()";
	}
$page = $page .  "
isClientProjectBilled()
";
	if ($settings->user_can_enter_notes($timesheet_user_id, 'display_expenses_toggle', 'display_expenses_list') == 1) {
		$page = $page .  "
function enablePerDiem() {
	if (timesheet.isPerDiem.value==1) {
		timesheet.perdiem_city.disabled=false;
	} else {
		timesheet.perdiem_city.disabled=true;
	}
}

enablePerDiem()";
	}
$page = $page .  "
switchRecuringFields()
</script>";

		if ($show==1) {
			echo $page;
	

		} else {
			return $page;
		}
		
	}
	
	function save_audit_comment($timesheet_id, $comment, $timesheet_action) {
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db;
		$common = new time_sheets_common();
		
		if ($timesheet_action==1) {
			$approval = new time_sheets_queue_approval;
			$approval->do_timesheet_rejectapproval($timesheet_id,$comment);
		}
				
		$sql = "insert into {$wpdb->prefix}timesheet_comment_audit
		(timesheet_id, entered_by, entered_at, comment, timesheet_rejected)
		values
		(%d, %d, now(), %s, %d)";
		$params = array($timesheet_id, $user_id, $comment, $timesheet_action);
		
		$db->query($sql, $params);
		
		if ($timesheet_action != 3) {
			$sql = "select distinct display_name
					from {$wpdb->users} u
					join {$wpdb->prefix}timesheet t on u.ID = t.user_id
					where t.timesheet_id = %d";

			$params = array($timesheet_id);

			$timesheet_user = $db->get_row($sql, $params);
		
			$sql = "select distinct user_email
					from {$wpdb->users} u
					join {$wpdb->prefix}timesheet t on u.ID = t.user_id
					where t.timesheet_id = %d
					union
					select user_email
					from {$wpdb->users} u
					join {$wpdb->prefix}timesheet_invoicers i on u.ID = i.user_id
					union 
					select user_email
					from {$wpdb->users} u
					join {$wpdb->prefix}timesheet_approvers a on u.ID = a.user_id
					";
			$params = array($timesheet_id);

			$to = $db->get_results($sql, $params);
			$http = !empty($_SERVER['HTTPS']) ? "https" :  "http";

			$stat = $timesheet_action==1 ? " rejected this time sheet with the comment: " : " entered the comment: ";

			$subject = "Comment on Time Sheet {$timesheet_id}";
			$body = $user->display_name . $stat . $comment . "<BR>
				For the time sheet <a href='" . $http . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?page=show_timesheet&source=email&time_sheet_id=". $timesheet_id . "'>" . $timesheet_id . "</a> entered by " . $timesheet_user->display_name . ".
				<P><P>
			";

			foreach ($to as $a_to) {
				$common->send_email($a_to->user_email, $subject, $body);
			}
		}
	}

	function save_timesheet() {
		global $wpdb;
		#global $timesheet_id;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		$ot_multiplier = isset($options['ot_multiplier']) ? floatval($options['ot_multiplier']) : 1;
		
		if ((!isset($_POST['is_template']) && !isset($_POST['template_id']))) {
			$table_name = "timesheet";
			$archive_name = "timesheet_archive";
		} else {
			$table_name = "timesheet_scheduled";
			$archive_name = "timesheet_scheduled_archive";
		}
		
		if ($ot_multiplier==0) {
			$ot_multiplier = 1;
		}

		if ($user_id == 0) {
			echo "<div class='notice notice-error'><p>You must be logged in to view this page.</p></div>";
			return -1;
		}

		if (!$_POST['ProjectId']) {
			echo '<div class="notice notice-error is-dismissible"><p>No Project Was Selected. A project must be selected. If no projects are available contact your lead.</p></div>';
			return -1;
		}
		if (isset($_POST['timesheet_id'])) {
			$timesheet_id = intval($_POST['timesheet_id']);
		} else {
			$timesheet_id = '';
		}
		
		$nonce = check_admin_referer('timesheet_edit_timesheet');

		$db = new time_sheets_db();

		$monday_hours = isset($_POST['monday_hours']) ? floatval($_POST['monday_hours']) : 0;
		$tuesday_hours = isset($_POST['tuesday_hours']) ? floatval($_POST['tuesday_hours']) : 0;
		$wednesday_hours = isset($_POST['wednesday_hours']) ? floatval($_POST['wednesday_hours']) : 0;
		$thursday_hours = isset($_POST['thursday_hours']) ? floatval($_POST['thursday_hours']) : 0;
		$friday_hours = isset($_POST['friday_hours']) ? floatval($_POST['friday_hours']) : 0;
		$saturday_hours = isset($_POST['saturday_hours']) ? floatval($_POST['saturday_hours']) : 0;
		$sunday_hours = isset($_POST['sunday_hours']) ? floatval($_POST['sunday_hours']) : 0;
		
		$monday_ot = isset($_POST['monday_ot']) ? floatval($_POST['monday_ot']) : 0;
		$tuesday_ot = isset($_POST['tuesday_ot']) ? floatval($_POST['tuesday_ot']) : 0;
		$wednesday_ot = isset($_POST['wednesday_ot']) ? floatval($_POST['wednesday_ot']) : 0;
		$thursday_ot = isset($_POST['thursday_ot']) ? floatval($_POST['thursday_ot']) : 0;
		$friday_ot = isset($_POST['friday_ot']) ? floatval($_POST['friday_ot']) : 0;
		$saturday_ot = isset($_POST['saturday_ot']) ? floatval($_POST['saturday_ot']) : 0;
		$sunday_ot = isset($_POST['saturday_ot']) ? floatval($_POST['sunday_ot']) : 0;
		
		$monday_nb = isset($_POST['monday_nb']) ? floatval($_POST['monday_nb']) : 0;
		$tuesday_nb = isset($_POST['tuesday_nb']) ? floatval($_POST['tuesday_nb']) : 0;
		$wednesday_nb = isset($_POST['wednesday_nb']) ? floatval($_POST['wednesday_nb']) : 0;
		$thursday_nb = isset($_POST['thursday_nb']) ? floatval($_POST['thursday_nb']) : 0;
		$friday_nb = isset($_POST['friday_nb']) ? floatval($_POST['friday_nb']) : 0;
		$saturday_nb = isset($_POST['saturday_nb']) ? floatval($_POST['saturday_nb']) : 0;
		$sunday_nb = isset($_POST['saturday_nb']) ? floatval($_POST['sunday_nb']) : 0;
		
		$start_date = date("Y-m-d", strtotime($_POST['start_date']));

		if (isset($_POST['timesheet_id'])) {

			$sql = "select user_id from {$wpdb->prefix}{$table_name} where timesheet_id = %d";	
			$parm = array(intval($_POST['timesheet_id']));	
			$timesheet = $db->get_row($sql, $parm);

			if ($timesheet) {
				//This is an update. Make sure the person updating is this person.
				if ($timesheet->user_id != $user_id) {
					echo '<div class="notice notice-error"><p>You are attempting this submit a timesheet for a different user. This is not allowed.</p></div>';
					return -1;
				}
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>You appear to be updating a timesheet which does not exist.</p></div>';
				return -1;
			}
			
			$sql = "select count(*) ct from {$wpdb->prefix}{$table_name} where user_id = %d and ProjectId = %d and start_date = %s and timesheet_id <> %d";
			$params = array($user_id, intval($_POST['ProjectId']), $start_date, intval($_POST['timesheet_id']));
			$record_count = $db->get_var($sql, $params);

			if ($record_count!=0) {
				if (isset($options['error_if_date_already_used'])) {
					echo '<div class="notice notice-error"><p>You already have entered a time sheet for this project for this week. This time sheet was <B>NOT</B> saved.</p></div>';
					$_POST['submit'] == 'blank';
					return -1; 
				} else {
					echo "<div class='notice notice-warning is-dismissible'><p>You already have entered a time sheet for this project for this week.</p></div>";
				}
			}
		} else {
			//Only do for new timesheets
			
			$sql = "select count(*) ct from {$wpdb->prefix}{$table_name} where user_id = %d and ProjectId = %d and start_date = %s";
			$params = array($user_id, intval($_POST['ProjectId']), $start_date);
			$record_count = $db->get_var($sql, $params);

			if ($record_count!=0) {
				if (isset($options['error_if_date_already_used'])) {
					echo '<div class="notice notice-error"><p>You already have entered a time sheet for this project for this week. This time sheet was <B>NOT</B> saved.</p></div>';
					$_POST['submit'] == 'blank';
					return -1; 
				} else {
					echo "<div class='notice notice-warning is-dismissible'><p>You already have entered a time sheet for this project for this week.</p></div>";
				}
			}
		}

		$may_embargo = isset($_POST['may_embargo'])? 1 : 0 ;
		$project_complete = isset($_POST['project_complete'])? 1 : 0 ;
		$week_complete = isset($_POST['week_complete']) ? 1 : 0;
		//if ($_POST['week_complete']==1) {
		//	$week_complete=1;
		//} else {
		//	$week_complete=0;
		//}
		
		$EmbargoPendingProjectClose = $db->get_var("select BillOnProjectCompletion FROM {$wpdb->prefix}timesheet_recurring_invoices_monthly where client_id = %d", array(intval($_POST['ClientId'])));

		$EmbargoPendingProjectClose2 = $db->get_var("select BillOnProjectCompletion FROM {$wpdb->prefix}timesheet_client_projects where ProjectId = %d", array(intval($_POST['ProjectId'])));

		if ($EmbargoPendingProjectClose2==1) {
			$EmbargoPendingProjectClose=1;
		}

		if ($EmbargoPendingProjectClose<>1) {
			$EmbargoPendingProjectClose=0;
		}

		if ($project_complete<>1) {
			$project_complete=0;
		}

		$total_hours = $monday_hours + $tuesday_hours + $wednesday_hours + $thursday_hours + $friday_hours + $saturday_hours + $sunday_hours;
		
		$total_hours = $total_hours + (($monday_ot + $tuesday_ot + $wednesday_ot + $thursday_ot + $friday_ot + $saturday_ot + $sunday_ot)*$ot_multiplier);

		

		if (isset($_POST['timesheet_id'])) { //UPDATE

			$timesheet_id = $_POST['timesheet_id'];

			$sql = "update {$wpdb->prefix}{$table_name} 
					SET start_date=%s,
					ClientId=%d,
					ProjectId=%d,
					monday_hours=%s,
					tuesday_hours=%s,
					wednesday_hours=%s,
					thursday_hours=%s,
					friday_hours=%s,
					saturday_hours=%s,
					sunday_hours=%s,
					total_hours=%s,
					monday_desc=%s,
					tuesday_desc=%s,
					wednesday_desc=%s,
					thursday_desc=%s,
					friday_desc=%s,
					saturday_desc=%s,
					sunday_desc=%s,
					per_diem_days=%s,
					hotel_charges=%s,
					rental_car_charges=%s,
					tolls=%s,
					other_expenses=%s,
					other_expenses_notes=%s,
					week_complete=%d,
					mileage=%d,
					EmbargoPendingProjectClose=%d,
					project_complete=%d,
					flight_cost=%s,
					isPerDiem=%d,
					perdiem_city=%s,
					taxi=%s,
					monday_ot=%s,
					tuesday_ot=%s,
					wednesday_ot=%s,
					thursday_ot=%s,
					friday_ot=%s,
					saturday_ot=%s,
					sunday_ot=%s,
					monday_nb=%s,
					tuesday_nb=%s,
					wednesday_nb=%s,
					thursday_nb=%s,
					friday_nb=%s,
					saturday_nb=%s,
					sunday_nb=%s
				WHERE timesheet_id=%d";

			$params=array($start_date, 
				intval($_POST['ClientId']), 
				intval($_POST['ProjectId']), 
				floatval($monday_hours), 
				floatval($tuesday_hours), 
				floatval($wednesday_hours), 
				floatval($thursday_hours), 
				floatval($friday_hours), 
				floatval($saturday_hours), 
				floatval($sunday_hours), 
				$total_hours, 
				(isset($_POST['monday_desc'])) ? sanitize_textarea_field($_POST['monday_desc']) : "", 
				(isset($_POST['tuesday_desc'])) ? sanitize_textarea_field($_POST['tuesday_desc']) : "", 
				(isset($_POST['wednesday_desc'])) ? sanitize_textarea_field($_POST['wednesday_desc']) : "", 
				(isset($_POST['thursday_desc'])) ? sanitize_textarea_field($_POST['thursday_desc']) : "", 
				(isset($_POST['friday_desc'])) ? sanitize_textarea_field($_POST['friday_desc']) : "", 
				(isset($_POST['saturday_desc'])) ? sanitize_textarea_field($_POST['saturday_desc']) : "", 
				(isset($_POST['sunday_desc'])) ? sanitize_textarea_field($_POST['sunday_desc']) : "", 
				(isset($_POST['per_diem_days'])) ? floatval($_POST['per_diem_days']) : 0, 
				(isset($_POST['hotel_charges'])) ? floatval($_POST['hotel_charges']) : 0, 
				(isset($_POST['rental_car_charges'])) ? floatval($_POST['rental_car_charges']) : 0, 
				(isset($_POST['tolls'])) ? floatval($_POST['tolls']) : 0, 
				(isset($_POST['other_expenses'])) ? floatval($_POST['other_expenses']) : 0, 
				(isset($_POST['other_expenses_notes'])) ? sanitize_textarea_field($_POST['other_expenses_notes']) : "", 
				$week_complete, 
				(isset($_POST['mileage'])) ? floatval($_POST['mileage']) : 0, 
				$EmbargoPendingProjectClose, 
				$project_complete, 
				(isset($_POST['flight_cost'])) ? floatval($_POST['flight_cost']) : 0, 
				(isset($_POST['isPerDiem'])) ? intval($_POST['isPerDiem']) : 0, 
				(isset($_POST['perdiem_city'])) ? sanitize_text_field($_POST['perdiem_city']) : "", 
				(isset($_POST['taxi'])) ? floatval($_POST['taxi']) : 0, 
				floatval($monday_ot), 
				floatval($tuesday_ot), 
				floatval($wednesday_ot), 
				floatval($thursday_ot), 
				floatval($friday_ot), 
				floatval($saturday_ot), 
				floatval($sunday_ot), 
				floatval($monday_nb), 
				floatval($tuesday_nb), 
				floatval($wednesday_nb), 
				floatval($thursday_nb), 
				floatval($friday_nb), 
				floatval($saturday_nb), 
				floatval($sunday_nb), 
				intval($_POST['timesheet_id']));

			$db->query($sql, $params);

			if ($week_complete==1) {
				$sql = "update {$wpdb->prefix}{$table_name} 
				set marked_complete_by=$user_id,
					marked_complete_date = CURDATE()
				where timesheet_id=%d";
				$params=array(intval($_POST['timesheet_id']));

				if (isset($options['allow_recurring_timesheets'])) {
					$this->update_retainer_usage($timesheet_id);
				}
				
				$db->query($sql, $params);

				if ($EmbargoPendingProjectClose==0) {
					$this->email_on_submission(intval($_POST['timesheet_id']));
				}

				$sql = "update {$wpdb->prefix}timesheet_client_projects
					set HoursUsed=HoursUsed+$total_hours
					where ProjectId=%d";
				$params=array(intval($_POST['ProjectId']));

				$db->query($sql, $params);
				
				$this->set_project_inactive_on_date(intval($_POST['ProjectId']));


				$sql = "delete from {$wpdb->prefix}timesheet_reject_message_queue
				where timesheet_id = %d";
				$params=array(intval($_POST['timesheet_id']));
				$db->query($sql, $params);


			}
			
			
		
			echo '<div class="notice notice-success is-dismissible"><p>Timesheet updated.</p></div>';
		} else { //INSERT
			$sql = "insert into {$wpdb->prefix}{$table_name}  (user_id, start_date, entered_date, ClientId, ProjectId, monday_hours, tuesday_hours, wednesday_hours, thursday_hours, friday_hours, saturday_hours, sunday_hours, total_hours, monday_desc, tuesday_desc, wednesday_desc, thursday_desc, friday_desc, saturday_desc, sunday_desc, per_diem_days, hotel_charges, rental_car_charges, tolls, other_expenses, other_expenses_notes, week_complete, approved, invoiced, mileage, EmbargoPendingProjectClose, project_complete, flight_cost, isPerDiem, perdiem_city, taxi, monday_ot, tuesday_ot, wednesday_ot, thursday_ot, friday_ot, saturday_ot, sunday_ot, monday_nb, tuesday_nb, wednesday_nb, thursday_nb, friday_nb, saturday_nb, sunday_nb) values (%d, %s, now(), %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, 0, 0, %d, %d, %d, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)";

			$params=array($user_id, 
				$start_date, 
				intval($_POST['ClientId']), 
				intval($_POST['ProjectId']), 
				floatval($monday_hours), 
				floatval($tuesday_hours), 
				floatval($wednesday_hours), 
				floatval($thursday_hours), 
				floatval($friday_hours), 
				floatval($saturday_hours), 
				floatval($sunday_hours), 
				$total_hours, 
				(isset($_POST['monday_desc'])) ? sanitize_textarea_field($_POST['monday_desc']) : "", 
				(isset($_POST['tuesday_desc'])) ? sanitize_textarea_field($_POST['tuesday_desc']) : "", 
				(isset($_POST['wednesday_desc'])) ? sanitize_textarea_field($_POST['wednesday_desc']) : "", 
				(isset($_POST['thursday_desc'])) ? sanitize_textarea_field($_POST['thursday_desc']) : "", 
				(isset($_POST['friday_desc'])) ? sanitize_textarea_field($_POST['friday_desc']) : "", 
				(isset($_POST['saturday_desc'])) ? sanitize_textarea_field($_POST['saturday_desc']) : "", 
				(isset($_POST['sunday_desc'])) ? sanitize_textarea_field($_POST['sunday_desc']) : "", 
				(isset($_POST['per_diem_days'])) ? floatval($_POST['per_diem_days']) : 0, 
				(isset($_POST['hotel_charges'])) ? floatval($_POST['hotel_charges']) : 0, 
				(isset($_POST['rental_car_charges'])) ? floatval($_POST['rental_car_charges']) : 0, 
				(isset($_POST['tolls'])) ? floatval($_POST['tolls']) : 0, 
				(isset($_POST['other_expenses'])) ? floatval($_POST['other_expenses']) : 0, 
				(isset($_POST['other_expenses_notes'])) ? sanitize_textarea_field($_POST['other_expenses_notes']) : "", 
				$week_complete, 
				(isset($_POST['mileage'])) ? $_POST['mileage'] : 0, 
				$EmbargoPendingProjectClose,  
				$project_complete, 
				(isset($_POST['flight_cost'])) ? floatval($_POST['flight_cost']) : 0, 
				(isset($_POST['isPerDiem'])) ? floatval($_POST['isPerDiem']) : 0, 
				(isset($_POST['perdiem_city'])) ? sanitize_text_field($_POST['perdiem_city']) : "", 
				(isset($_POST['taxi'])) ? floatval($_POST['taxi']) : 0, 
				floatval($monday_ot), 
				floatval($tuesday_ot), 
				floatval($wednesday_ot), 
				floatval($thursday_ot), 
				floatval($friday_ot), 
				floatval($saturday_ot), 
				floatval($sunday_ot), 
				floatval($monday_nb), 
				floatval($tuesday_nb), 
				floatval($wednesday_nb), 
				floatval($thursday_nb), 
				floatval($friday_nb), 
				floatval($saturday_nb), 
				floatval($sunday_nb));

			$db->query($sql, $params);

			$sql = "select max(timesheet_id) 
			from {$wpdb->prefix}{$table_name} 
			where user_id={$user_id}";

			$timesheet_id = $db->get_var($sql);
			
			#$this->check_overages($timesheet_id, 1);

		
			
			if ($week_complete==1 && !isset($_POST['is_template'])) {
				$sql = "update {$wpdb->prefix}timesheet
				set marked_complete_by=$user_id,
					marked_complete_date = CURDATE()
				where timesheet_id=$timesheet_id";

				if (isset($options['allow_recurring_timesheets'])) {
					$this->update_retainer_usage($timesheet_id);
				}
				
				$db->query($sql);
				if ($EmbargoPendingProjectClose==0) {
					$this->email_on_submission($timesheet_id);
				}

				$sql = "update {$wpdb->prefix}timesheet_client_projects
					set HoursUsed=HoursUsed+$total_hours
					where ProjectId=%d";
				$params=array(intval($_POST['ProjectId']));

				$db->query($sql, $params);
				
				$this->set_project_inactive_on_date(intval($_POST['ProjectId']));
			}

			
			
			echo "<div class='notice notice-success is-dismissible'><p>" .  ((isset($_POST['is_template']) || isset($_POST['template_id']) )?'Template':'Timesheet') . " saved.</p></div>";

		}
		
		if (isset($_POST['is_template']) || isset($_POST['template_id']) ) { //If this is a template
			if (isset($_POST['interval'])) {
				if ($_POST['interval'] == 'w') {
					$interval = 'w';
				} elseif ($_POST['interval'] == 'm') {
					$interval = 'm';
				} elseif ($_POST['interval'] == 'q') {
					$interval = 'q';
				} elseif ($_POST['interval'] == 'a') {
					$interval = 'a';
				}
			}

			if (isset($_POST['delete_after_expiration'])) {
				$delete_after_expiration = 1;
			} else {
				$delete_after_expiration = 0;
			}

			$sql = "update {$wpdb->prefix}{$table_name}
							set frequency = %s,
							    next_execution = %s,
								expires_on = %s,
								delete_after_expiration = %d
						where timesheet_id = %d";

			$params = array($interval, $_POST['next_execution'], $_POST['expires'], $delete_after_expiration, $timesheet_id);

			$db->query($sql, $params);
		}
		
		#Archive the change
		if (isset($_POST['timesheet_id'])) { //UPDATE
			$common->archive_records ($table_name, $archive_name, 'timesheet_id', intval($_POST['timesheet_id']));
		} else { //INSERT
			$common->archive_records ($table_name, $archive_name, 'timesheet_id', intval($timesheet_id), '=', 'INSERT');
		}

		if ($may_embargo==1) {
			if ($project_complete==1 && $week_complete==1) {
				$sql = "update {$wpdb->prefix}timesheet
						SET EmbargoPendingProjectClose = 0
					WHERE ProjectId=%d AND EmbargoPendingProjectClose = 1";
				$params = array(intval($_POST['ProjectId']));
				$db->query($sql, $params);
				
				//Archive the change
				$common->archive_records ('timesheet', 'timesheet_archive', 'EmbargoPendingProjectClose = 1 AND ProjectId', intval($_POST['ProjectId']));
				
				
				
				echo "<div class='notice notice-info'><p>Your timesheets for this client have been sent for processing.</p></div>";
				
				$sql = "update {$wpdb->prefix}timesheet_client_projects set Active=0 where ProjectId=%d";
				$params = array(intval($_POST['ProjectId']));
				$db->query($sql, $params);
				
				//Archive the change
				$common->archive_records ('timesheet_client_projects', 'timesheet_client_projects_archive', 'ProjectId', intval($_POST['ProjectId']));
				
				echo "<div class='notice notice-warning is-dismissible'><p>This project has been marked as closed.</p></div>";
					
				$this->email_on_submission(intval($timesheet_id));
			}
		}

		#$sql = "select * from {$wpdb->prefix}timesheet_client_projects where project_id = %d";
		#$parm = array(intval($_POST['ClientId']));
		#$client = $db->get_row($sql, $parm);

		return $timesheet_id;
	}
	
	function update_retainer_usage($timesheet_id) {
		global $wpdb;
		$common = new time_sheets_common();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		
		$page = '';
		
		$db = new time_sheets_db();
		
		$sql = "select * from {$wpdb->prefix}timesheet where timesheet_id = %d";
		$params = array($timesheet_id);
		$timesheet = $db->get_row($sql, $params);
		
		$sql = "select * from {$wpdb->prefix}timesheet_client_projects where ProjectId = {$timesheet->ProjectId}";
		$project = $db->get_row($sql);
		
		if (!isset($project->retainer_id) || $project->retainer_id==0) {
			return 0;
		}
		
		$total_hours = $common->get_timesheets_total_hours($timesheet_id);
		
		$rate = $common->get_employee_hourly_rate($user_id, $timesheet->ProjectId);
				
		$sql = "select * from {$wpdb->prefix}timesheet_client_projects where ProjectId = {$timesheet->ProjectId}";
		$project = $db->get_row($sql);
		
		$retainer_id = $project->retainer_id;
		
		$start_date = $common->get_retainer_interval_startdate ($retainer_id, $timesheet->start_date, $timesheet->ProjectId);
			
		if ($rate) {
			$timesheet_amount = $total_hours*$rate;
			
			$sql = "insert into {$wpdb->prefix}timesheet_project_retainer_money_usage
			(project_id, frequency_start_date, retainer_id, max_frequency_bucket, used_amount)
			values
			({$timesheet->ProjectId}, '{$start_date}', {$project->retainer_id}, {$project->max_monthly_bucket}, {$timesheet_amount})
			ON DUPLICATE KEY UPDATE used_amount=used_amount+{$timesheet_amount}";
			
			$db->query($sql);
			
			$sql = "select * 
			from {$wpdb->prefix}timesheet_project_retainer_money_usage prmu
			join {$wpdb->prefix}timesheet_client_projects tcp ON tcp.ProjectId = prmu.project_id
			join {$wpdb->prefix}timesheet_clients tc on tcp.ClientId = tc.ClientId
			where project_id={$timesheet->ProjectId} and frequency_start_date = '{$start_date}'";
			$retainer = $db->get_row($sql);
			
			if ($retainer) {
				if ($retainer->max_frequency_bucket  < $retainer->used_amount*$options['project_trigger_percent'] ){
					if (isset($options["email_address_on_project_over"])) {
						$common->send_email($options["email_address_on_project_over"], "Project {$retainer->ProjectName} for the client {$retainer->ClientName} is close to over budget", "Project {$retainer->ProjectName} for the client {$retainer->ClientName} has gone over {$options['project_trigger_percent']}% of the budget. 
						<p>
						The client needs to be contacted to resolve the issue.
						<p><p>
						--The Time Sheet System");
						
						echo "<div class='notice notice-warning'><p>Project has gone over {$options['project_trigger_percent']}% allowed amount for this interval. The sales team has been notified.</p></div>";
					} else {
						echo "<div class='notice notice-warning'><p>Project has gone over {$options['project_trigger_percent']}% of the allowed amount for this interval. Please contact your sales team for assistance.</p></div>";
					}
				}
			} else {
				echo "<div class='notice notice-error'><p>Unable to write project usage values for this project. Contact your administrator.</p></div>";
			}
			
		} else {
			echo "<div class='notice notice-warning'><p>No mapping between project and employee created. Contact administrator for assistance.</p></div>";
		}
		
		
	}
	
	function set_project_inactive_on_date($project_id) {
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;

		$db = new time_sheets_db();
		$common = new time_sheets_common();
		
		$sql = "select MaxHours, IsRetainer FROM {$wpdb->prefix}timesheet_client_projects WHERE ProjectId = %d";
		$params = array($project_id);
		$project = $db->get_row($sql, $params);
		
		if ($project->IsRetainer==1) { #The project is a retainer, do not close it
			return;
		}
		
		if ($project->MaxHours==0) { #If the project has no max number of hours, skip it
			return;
		}
		
		$sql = "select sum(total_hours) total_hours FROM {$wpdb->prefix}timesheet WHERE ProjectId = %d and week_complete = 1";
		$params = array($project_id);
		$timesheets = $db->get_row($sql, $params);
		
		if ($timesheets->total_hours < $project->MaxHours) { #The project hasn't run out of hours so there's nothing to do yet
			return;
		}
		
		
		$date = new DateTime();

		// Modify the date it contains
		$date->modify('next tuesday');
		
		$nextTuesday = $date->format('Y-m-d');
		
		$sql = "delete from {$wpdb->prefix}timesheet_client_project_autoclose where project_id = %d";
		$params = array($project_id);
		$db->query($sql, $params);
		
		$sql = "insert into {$wpdb->prefix}timesheet_client_project_autoclose
		(project_id, close_date)
		values
		(%d, %s)";
		$params = array($project_id, $nextTuesday);
		$db->query($sql, $params);
	}

}