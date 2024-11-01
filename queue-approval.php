<?php
class time_sheets_queue_approval {
	function count_pending_approval() {
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();

		$sql = "select sum(case when (EmbargoPendingProjectClose = 0 or EmbargoPendingProjectClose IS NULL) and tcp.IsRetainer=1 then 1 else 0 end) Retainer, sum(case when (EmbargoPendingProjectClose = 0 or EmbargoPendingProjectClose IS NULL) and tcp.IsRetainer=0 then 1 else 0 end) NotRetainer,
		sum(case when (EmbargoPendingProjectClose = 1) then 1 else 0 end) Embargoed
		from {$wpdb->prefix}timesheet t
		join {$wpdb->prefix}timesheet_approvers_approvies aa ON aa.approvie_user_id = t.user_id
		join {$wpdb->prefix}timesheet_approvers ta on aa.approver_user_id = ta.user_id 
			and (ta.user_id = {$user_id} OR (ta.backup_user_id = {$user_id} and ta.backup_expires_on > now()))
		join {$wpdb->prefix}timesheet_client_projects tcp on t.ProjectId = tcp.ProjectId
		where approved = 0 and week_complete = 1";

		$count = $db->get_row($sql);
		
		return $count;
	}

	function employee_approver_check($includeBackups=true) {
		global $wpdb;
		global $approval_count;
		
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();

		if (!isset($approval_count)) {
			if ($includeBackups=='true') {
				$sql = "select count(*) from {$wpdb->prefix}timesheet_approvers where (user_id = {$user_id}) OR (backup_user_id = {$user_id} and backup_expires_on > now())";
			} else {
				$sql = "select count(*) from {$wpdb->prefix}timesheet_approvers where user_id = {$user_id}";
			}
			
			$count = $db->get_var($sql);
			
			$approval_count = $count;
			
		} else {
			$count = $approval_count;
		}
		
		
		return $count;
	}


	function do_timesheet_approval_processing() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;

		check_admin_referer( 'timesheet_approval_processing');

		$timesheets = $this->return_approval_list();

		if ($timesheets) {
			foreach ($timesheets as $timesheet) {
				$objname = "sheet_{$timesheet->timesheet_id}";

				$value = $_GET[$objname];

				if ($value == "approve") {
					$this->do_timesheet_approval($timesheet->timesheet_id);
				}
				if ($value == "reject") {
					$this->do_timesheet_rejectapproval($timesheet->timesheet_id);
				}
			}
		}
		echo "<div class='notice notice-success is-dismissible'><p>Timesheet(s) updated.</p></div>";
	}

	function email_on_approval($timesheet_id) {
		$options = get_option('time_sheets');
		$common = new time_sheets_common();

		if (!isset($options['enable_email'])) {
			return;
		}

		global $wpdb;
		$db = new time_sheets_db();

		$sql = "select display_name 
			from {$wpdb->prefix}timesheet t
			join {$wpdb->users} u on u.ID = t.user_id
			where t.timesheet_id = %d";
		$params = array($timesheet_id);
		$user_login = $db->get_var($sql, $params);

		$sql = "select user_email
		from {$wpdb->users} u
		join {$wpdb->prefix}timesheet_invoicers a on u.ID = a.user_id";

		$users = $db->get_results($sql);

		$subject = "There are time sheet(s)  pending invoicing.";
		$body = "A time sheet has been entered by {$user_login} which is approved and needs to be invoiced to the client..

It can be viewed and invoiced from the <a href='http://$_SERVER[HTTP_HOST]/wp-admin/admin.php?page=invoice_timesheet'>invoicing menu</a>.";

		foreach ($users as $user) {
			$common->send_email ($user->user_email, $subject, $body);
		}
	}

	function do_timesheet_split() {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();

		$sql = "SELECT GROUP_CONCAT(distinct COLUMN_NAME) columns 
			FROM `INFORMATION_SCHEMA`.`COLUMNS` 
			WHERE `TABLE_NAME`='{$wpdb->prefix}timesheet'
				and COLUMN_NAME not in ('timesheet_id', 'monday_hours', 'tuesday_hours', 'wednesday_hours',
					'thursday_hours', 'friday_hours', 'saturday_hours', 'sunday_hours', 'total_hours',
					'monday_desc', 'tuesday_desc', 'wednesday_desc', 'thursday_desc', 
					'friday_desc', 'saturday_desc', 'sunday_desc', 'per_diem_days', 'hotel_charges',
					'rental_car_charges', 'tolls', 'other_expenses', 'other_expenses_notes', 'mileage', 
					'flight_cost')";
		$cols = $db->get_var($sql);

		if ($_GET['new_start_date'] ==1) {
			$cols = "{$cols}, tuesday_hours, wednesday_hours, thursday_hours, friday_hours, saturday_hours, sunday_hours, tuesday_desc, wednesday_desc, thursday_desc, friday_desc, saturday_desc, sunday_desc";
			$update_cols = "tuesday_hours = 0, wednesday_hours=0, thursday_hours=0, friday_hours=0, saturday_hours=0, sunday_hours=0, tuesday_desc='', wednesday_desc='', thursday_desc='', friday_desc='', saturday_desc='', sunday_desc=''";
		}

		if ($_GET['new_start_date'] ==2) {
			$cols = "{$cols}, wednesday_hours, thursday_hours, friday_hours, saturday_hours, sunday_hours, wednesday_desc, thursday_desc, friday_desc, saturday_desc, sunday_desc";
			$update_cols = "wednesday_hours=0, thursday_hours=0, friday_hours=0, saturday_hours=0, sunday_hours=0, wednesday_desc='', thursday_desc='', friday_desc='', saturday_desc='', sunday_desc=''";
		}

		if ($_GET['new_start_date'] ==3) {
			$cols = "{$cols}, thursday_hours, friday_hours, saturday_hours, sunday_hours, thursday_desc, friday_desc, saturday_desc, sunday_desc";
			$update_cols = "thursday_hours=0, friday_hours=0, saturday_hours=0, sunday_hours=0, thursday_desc='', friday_desc='', saturday_desc='', sunday_desc=''";
		}

		if ($_GET['new_start_date'] ==4) {
			$cols = "{$cols}, friday_hours, saturday_hours, sunday_hours, friday_desc, saturday_desc, sunday_desc";
			$update_cols = "friday_hours=0, saturday_hours=0, sunday_hours=0, friday_desc='', saturday_desc='', sunday_desc=''";
		}

		if ($_GET['new_start_date'] ==5) {
			$cols = "{$cols}, saturday_hours, sunday_hours, saturday_desc, sunday_desc";
			$update_cols = "saturday_hours=0, sunday_hours=0, saturday_desc='', sunday_desc=''";
		}

		if ($_GET['new_start_date'] ==6) {
			$cols = "{$cols}, sunday_hours, sunday_desc";
			$update_cols = "sunday_hours=0, sunday_desc=''";
		}

		$sql = "INSERT INTO {$wpdb->prefix}timesheet
			({$cols})
			SELECT {$cols} FROM {$wpdb->prefix}timesheet WHERE timesheet_id = %d";
		$parms = array(intval($_GET['timesheet_id']));
		$db->query($sql, $parms);
		
		$sql = "(select max(timesheet_id) timesheet_id from {$wpdb->prefix}timesheet)";
		$new_timesheet_id = $db->get_row($sql);
		
		$common->archive_records ('timesheet', 'timesheet_archive', "timesheet_id", $new_timesheet_id->timesheet_id, '=', 'INSERTED');
		
		$sql = "UPDATE {$wpdb->prefix}timesheet set {$update_cols} WHERE timesheet_id = %d";
		$parms = array(intval($_GET['timesheet_id']));
		$db->query($sql, $parms);
		
		$common->archive_records ('timesheet', 'timesheet_archive', "timesheet_id", intval($_GET['timesheet_id']), '=', 'UPDATED');

		echo '<div class="notice notice-success is-dismissible"><p>Timesheet has been split.</p></div>';

	}

	function split_timesheet(){
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		
		$sql = "select start_date from {$wpdb->prefix}timesheet where timesheet_id = %d";
		$parms = array(intval($_GET['timesheet_id']));

		$start_date = $db->get_var($sql, $parms);
		echo "<form name='splittimesheet' method='get'><br>Date selected below and later will be moved to a new timesheet.  Expenses will be left on the origional timesheet.<br>
		<input type='hidden' name='timesheet_id' value='{$common->intval($_GET['timesheet_id'])}'>
		<input type='hidden' name='page' value='approve_timesheet'>
		<input type='hidden' name='action' value='split2'>
		<input type='hidden' name='subaction' value='finish'>
		<table><tr><td>Select date to split timesheet:</td>
			<td><select name='new_start_date'>";

		$i = 1;
		$n = "";

		while ($i <= 7)
		{
			echo "<option value='{$i}'>{$common->add_days_to_date($start_date, $i, $n)}</option>";
			$i++;
		}

		echo "</select></td></tr><tr><td colspan='2'><input type='submit' name='submit' value='Split Timesheet' class='button-primary'></td></tr></table></form>";
	}

	function approve_timesheet(){
		$common = new time_sheets_common();
		$entry = new time_sheets_entry();
		$options = get_option('time_sheets');

		if (isset($_GET['action']) && $_GET['action']=='preapprove') {
			$entry->show_timesheet();
		} elseif (isset($_GET['action']) && $_GET['action']=='split') {
			$this->split_timesheet();
		} else {
			echo "<br><form name='show_filter'>";
			echo "<input type='hidden' name='page' value='approve_timesheet'>";
			echo "<table>";
			
			$show_retainers = isset($_GET['show_retainers']) ? " checked" : "";
			$hide_nonretainers = isset($_GET['hide_nonretainers']) ? " checked" : "";
			if (!isset($options['remove_embargo'])) {
				$show_embargoed = isset($_GET['show_embargoed']) ? " checked" : "";
				echo "<tr><td><input type='checkbox' onclick='changeCheckBoxes()' name='show_embargoed' value='checked' {$show_embargoed}> Show Embargoed Entries</td><tr>";
			}
			echo "<tr><td><input type='checkbox' name='show_retainers' value='checked' {$show_retainers}> Show Retainers</td></tr>";
			echo "<tr><td><input type='checkbox' name='hide_nonretainers' value='checked' {$hide_nonretainers}> Hide Non-Retainers</td></tr>";

			echo "<tr><td><input type='submit' name='submit' value='Filter List' class='button-primary'></tr><td></table></form>";

			echo "<form name='approve_timesheets'>";
			#if (count($timesheets) >= 15) {
				echo "<input type='submit' name='submit2' value='Record Approvals' class='button-primary'>";
			#}

			echo "<br><table border='0'><tr><td valign='top'>";
			$this->show_approval_list();
			echo "</td><td valign='top'>";
			$common->show_clients_on_retainer();
			echo "</td></tr>";
			
			?>
			<script>
				function changeCheckBoxes(){
					if (show_filter.show_embargoed.checked==true) {
						show_filter.show_retainers.checked=false;
						//show_filter.show_retainers.disabled=true;
						show_filter.hide_nonretainers.checked=false;
						//show_filter.hide_nonretainers.disabled=true;
					} else {
						//show_filter.show_retainers.disabled=false;
						//show_filter.hide_nonretainers.disabled=false;
					}
					show_filter.show_retainers.disabled=show_filter.show_embargoed.checked;
					show_filter.hide_nonretainers.disabled=show_filter.show_embargoed.checked;
				} 
				changeCheckBoxes();
</script>
			<?php
		}
	}
	

	function do_timesheet_rejectapproval($timesheet_id, $comment='') {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$entry = new time_sheets_entry();

		$user = wp_get_current_user();
		$user_id = $user->ID;

		
		$sql = "select total_hours, ProjectId, start_date from {$wpdb->prefix}timesheet where timesheet_id = %d";
		$params=array($timesheet_id);
		$timesheet = $db->get_row($sql, $params);

		$sql = "update {$wpdb->prefix}timesheet
				set marked_complete_by=NULL,
					marked_complete_date = NULL,
					week_complete=0,
					approved_by=NULL,
					approved_date = NULL,
					approved=0
				where timesheet_id=%d";
		$params=array($timesheet_id);
		$db->query($sql, $params);
		
		$common->archive_records ('timesheet', 'timesheet_archive', "timesheet_id", $timesheet_id, '=', 'UPDATED');
		
		$sql = "update {$wpdb->prefix}timesheet_client_projects
			set HoursUsed = HoursUsed - {$timesheet->total_hours}
			WHERE ProjectId = {$timesheet->ProjectId}";
		$db->query($sql);
		
		$sql = "select retainer_id from {$wpdb->prefix}timesheet_client_projects where ProjectId = {$timesheet->ProjectId}";
		$project = $db->get_row($sql);
		
		$total_hours = $common->get_timesheets_total_hours($timesheet_id);
		$start_date = $common->get_retainer_interval_startdate ($project->retainer_id, $timesheet->start_date, $timesheet->ProjectId);
		$rate = $common->get_employee_hourly_rate($user_id, $timesheet->ProjectId);
		$timesheet_rate = $rate * $total_hours;
		
		$sql = "update {$wpdb->prefix}timesheet_project_retainer_money_usage
			set used_amount = used_amount-{$timesheet_rate}
			where project_id = {$timesheet->ProjectId} and frequency_start_date = '{$start_date}'";
		$db->query($sql);
		
		//Archiving happens later on in this function to avoid duplicate archive records.

		$sql = "insert into {$wpdb->prefix}timesheet_reject_message_queue
			(timesheet_id, rejected_by, rejected_on, email_reminder_on, notify_approved_on)
			values
			(%d, $user_id, now(), DATE_ADD(now(), INTERVAL 3 DAY), DATE_ADD(now(), INTERVAL 7 DAY))";

		$db->query($sql,$params);

		$common->archive_records ('timesheet_reject_message_queue', 'timesheet_reject_message_queue_archive', "timesheet_id", $timesheet_id, '=', 'UPDATED');

		$subject = "Your timesheet(s) need to be reviewed and updated";
		$body = "Timesheet {$timesheet_id} needs to be reviewed and updated.<br>";
		
		if ($comment) {
			$body = $body . $comment;
		}

		$sql = "select user_email
			from {$wpdb->users} u
			join {$wpdb->prefix}timesheet t ON u.ID = t.user_id
			where t.timesheet_id = %d";

		$email = $db->get_var($sql, $params);
		
		$sql = "select t.ProjectId, tcp.close_on_completion from {$wpdb->prefix}timesheet t
		join {$wpdb->prefix}timesheet_client_projects tcp on t.ProjectId = tcp.ProjectId
		WHERE timesheet_id = %d";
		$params=array($timesheet_id);
		$project = $db->get_row($sql, $params);
				
		if ($project->close_on_completion==1) {
			$sql = "UPDATE {$wpdb->prefix}timesheet_client_projects
				SET Active = 1
				WHERE ProjectId = %d";
			$params = array($project->ProjectId);
			$db->query($sql, $params);
			
		}
		
		$common->archive_records ('timesheet_client_projects', 'timesheet_client_projects_archive', "ProjectId", $timesheet->ProjectId, '=', 'UPDATED');
		

		$common->send_email ($email, $subject, $body);
	}


	function return_approval_list() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');

		$sql = "select t.timesheet_id, u.user_login, t.start_date, c.ClientName client_name, cp.ProjectName project_name, EmbargoPendingProjectClose, cp.notes, u.display_name,
date_add(t.start_date, INTERVAL CASE WHEN monday_hours != 0 then 0 when tuesday_hours != 0 then 1 when wednesday_hours != 0 then 2 when thursday_hours != 0 then 3 when friday_hours != 0 then 4 when saturday_hours != 0 then 5 when sunday_hours != 0 then 6 else 0 end DAY) first_billed_day, total_hours, (hotel_charges+rental_car_charges+tolls+other_expenses+taxi) as expenses, date_add(t.start_date, INTERVAL CASE  WHEN sunday_hours != 0 then 6 when saturday_hours != 0 then 5 when friday_hours != 0 then 4 when thursday_hours != 0 then 3 when wednesday_hours != 0 then 2 when tuesday_hours != 0 then 1 when monday_hours != 0 then 0 else 0 end DAY) last_day_billed
		from {$wpdb->prefix}timesheet t
		JOIN {$wpdb->prefix}timesheet_clients c ON t.ClientId = c.ClientId
		JOIN {$wpdb->prefix}timesheet_client_projects cp ON t.ProjectId = cp.ProjectId
		join {$wpdb->users} u on t.user_id = u.ID
		join {$wpdb->prefix}timesheet_approvers_approvies aa ON aa.approvie_user_id = u.ID
		join {$wpdb->prefix}timesheet_approvers ta on aa.approver_user_id = ta.user_id 
			and (ta.user_id = {$user_id} OR (ta.backup_user_id = {$user_id} and ta.backup_expires_on > now()))
		where t.week_complete = 1 and approved = 0 ";

		if (isset($_GET['show_embargoed'])) {
			$sql = "$sql and (EmbargoPendingProjectClose = 1)";
		} else {
			$sql = "$sql and (EmbargoPendingProjectClose = 0 or EmbargoPendingProjectClose is null)";
			$IsRetainer = "-1";
			if (isset($_GET['show_retainers'])) {
				$IsRetainer = $IsRetainer . ", 1";
			}
			if (!isset($_GET['hide_nonretainers'])) {
				$IsRetainer = $IsRetainer . ", 0";
			}
				$sql = $sql . " and cp.ProjectId in (select ProjectId from {$wpdb->prefix}timesheet_client_projects where IsRetainer in ({$IsRetainer}))";
		}
		$sql = $sql . "order by t.start_date";

		$timesheets = $db->get_results($sql);

		return $timesheets;
	}


	function do_timesheet_approval($timesheet_id){
		global $wpdb;
		$db = new time_sheets_db();
		$options = get_option('time_sheets');
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$entry = new time_sheets_entry();
		$invoice = new time_sheets_queue_invoice();
		$common = new time_sheets_common();

		$sql = "select p.*
			from {$wpdb->prefix}timesheet_client_projects p
			join {$wpdb->prefix}timesheet t on t.ProjectId = p.ProjectId
			where t.timesheet_id = %d";
		$params = array($timesheet_id);
		$project = $db->get_row($sql, $params);

		$sql = "update {$wpdb->prefix}timesheet
			set approved = 1,
				approved_by = $user_id,
				approved_date = CURDATE()
			where timesheet_id = %d";
		$params = array($timesheet_id);
		$db->query($sql, $params);

		if ($project->flat_rate==1) {
			$invoice->do_timesheet_invoicing_one_timesheet($timesheet_id, 1, $user_id, 0);
		}

		if ($options['queue_order']=='parallel') {
			$payrolled = $invoice->should_be_payrolled($timesheet_id);
			$sql = "update {$wpdb->prefix}timesheet
					set payrolled = {$payrolled}
				where timesheet_id = %d";
			$params = array($timesheet_id);
			$db->query($sql, $params);			
		}
		
		$common->archive_records ('timesheet', 'timesheet_archive', "timesheet_id", $timesheet_id, '=', 'UPDATED');

		$this->email_on_approval($timesheet_id);

		$entry->check_overages($timesheet_id);
	}

	function show_approval_list() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$common = new time_sheets_common();
		
		$options = get_option('time_sheets');
		$currency_char = $options['currency_char'];

		$timesheets = $this->return_approval_list();		

		echo "<script type='text/javascript'>

			function approve_all() {
				";

				foreach ($timesheets as $timesheet) {
					echo "document.getElementById('approve_{$timesheet->timesheet_id}').checked = true;";
				}
				echo "
			}
			
			function reject_all() {
				";

				foreach ($timesheets as $timesheet) {
					echo "document.getElementById('reject_{$timesheet->timesheet_id}').checked = true;";
				}
				echo "
			}
			
			function hold_all() {
				";

				foreach ($timesheets as $timesheet) {
					echo "document.getElementById('hold_{$timesheet->timesheet_id}').checked = true;";
				}
				echo "
			}
		</script>
		";
		
		if (isset($_GET['show_embargoed'])) {
			 $embargod_parm = "&show_embargoed=1";
		} else {
			$embargod_parm = "";
		}
		$retainers_parm = "";
		if (isset($_GET['show_retainers'])) {
			$retainers_parm = "&show_retainers=checked";
		}
		if (isset($_GET['hide_nonretainers'])) {
			$retainers_parm = $retainers_parm . "&hide_nonretainers=checked";
		}

		if ($timesheets) {
			$total_hours = 0;
			$total_expenses = 0;
			

			echo "<table border='1' cellspacing='0' width='100%'><tr><td>Timesheet</td><td>View</td><td><a href='#' onclick='approve_all()'>Approve</a></td><td><a href='#' onclick='reject_all()'>Reject</a></td><td><a href='#' onclick='hold_all()'>Hold</a></td><td>Split</td>";
			if (isset($_GET['show_embargoed'])) {
				echo "<td>Embargoed</td>";
			}
			echo "<td>User</td><td>Start Date</td><td>First Billed Date</td><td>Last Billed Date</td><td>Client</td><td>Project Name</td><td>Hours</td><td>Expenses</td></tr>";
			foreach ($timesheets as $timesheet) {
				echo "<tr><td align='center'>{$timesheet->timesheet_id}</td>
					<td><a href='./admin.php?page=approve_timesheet&timesheet_id={$timesheet->timesheet_id}&action=preapprove' align='center'><img src='". plugins_url( 'view.png' , __FILE__) ."' width='15' height='15'></a></td>
					<td align='center'><input type='radio' id='approve_{$timesheet->timesheet_id}' name='sheet_{$timesheet->timesheet_id}' value='approve'></td><td align='center'><input type='radio' id='reject_{$timesheet->timesheet_id}' name='sheet_{$timesheet->timesheet_id}' value='reject'></td>
					<td align='center'><input type='radio' id='hold_{$timesheet->timesheet_id}' name='sheet_{$timesheet->timesheet_id}' value='hold' checked></td>
					<td align='center'><a href='./admin.php?page=approve_timesheet&timesheet_id={$timesheet->timesheet_id}&action=split{$embargod_parm}{$retainers_parm}'><img src='". plugins_url( 'split.png' , __FILE__) ."' width='15' height='15'></a></td>";
				if (isset($_GET['show_embargoed'])) {
					echo "<td align='center'>";
					if ($timesheet->EmbargoPendingProjectClose==1) {
						echo "<img src='". plugins_url( 'check.png' , __FILE__) ."' width='15' height='15'>";
					} else {
						echo "<img src='". plugins_url( 'x.png' , __FILE__) ."' width='15' height='15'>";
					}
					echo "</td>";
				}
				$total_hours = $total_hours + $timesheet->total_hours;
				if (is_numeric($timesheet->expenses)) {
					$total_expenses = $total_expenses + $timesheet->expenses;
				}
				
				echo "<td>{$common->replace(' ', '&nbsp;', $timesheet->display_name)}</td><td>{$common->f_date($timesheet->start_date)}</td><td>{$common->f_date($timesheet->first_billed_day)}</td><td>{$common->f_date($timesheet->last_day_billed)}</td><td>{$common->clean_from_db($timesheet->client_name)}</td><td>{$common->clean_from_db($timesheet->project_name)}</td><td>{$timesheet->total_hours}</td><td>";
				if ($timesheet->expenses) {
					echo $currency_char . $timesheet->expenses . "</td></tr>";
				}
				
			}
			if (isset($_GET['show_embargoed'])) {
				echo "<TR><TD colspan='13'>&nbsp;</td>";
			} else {
				echo "<TR><TD colspan='12'>&nbsp;</td>";
			}
			$total_hours = number_format($total_hours, 2);
			if (is_numeric($timesheet->expenses)) {
				$total_expenses = $currency_char . number_format($total_expenses, 2);
			} else {
				$total_expenses = "";
			}
			echo "<td>{$total_hours}</td><TD>{$total_expenses}</TD></TR>";
			echo "</table><br><input type='submit' name='submit' value='Record Approvals' class='button-primary'><input type='hidden' name='page' value='approve_timesheet'>";
			if (isset($_GET['show_retainers'])) {
				echo "<input type='hidden' name='show_retainers' value='{$common->esc_textarea($_GET['show_retainers'])}'>";
			}
			if (isset($_GET['hide_nonretainers'])) {
				echo "<input type='hidden' name='hide_nonretainers' value='{$common->esc_textarea($_GET['hide_nonretainers'])}'>";
			}
			if (isset($_GET['show_embargoed'])) {
				echo "<input type='hidden' name='show_embargoed' value='{$common->esc_textarea($_GET['show_embargoed'])}'>";	
			}

			wp_nonce_field( 'timesheet_approval_processing' );
			
			echo "<input type='hidden' name='action' value='approve'></form>";
		} else {
			echo "<div class='notice notice-info is-dismissible'><p>No time sheets to approve.</p></div>";
		}
	}
}
