<?php
class time_sheets_cron {
	
	function delete_expired_employee_title_overrides() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$db = new time_sheets_db();
		
		$sql = "delete from {$wpdb->prefix}timesheet_customer_project_employee_title_override where expiration_date <= now()";
		$db->query($sql);
	}
	
	function queue_use_templates() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$db = new time_sheets_db();
		$myDash = new time_sheets_mydashboard();
		$common = new time_sheets_common();
		
		$options = get_option('time_sheets');
		
		if (!isset($options['allow_recurring_timesheets'])) {
			return 0;
		}
			
		
		$sql = "select timesheet_id, expires_on, delete_after_expiration, next_execution, frequency from {$wpdb->prefix}timesheet_scheduled where next_execution <= now()";
		
		$templates = $db->get_results($sql);
		
		if ($templates) {
			foreach ($templates as $template) {
				#Use the template
				$myDash->use_template($template->timesheet_id);
				
				if ($template->frequency=='w') {
					$sql = "update {$wpdb->prefix}timesheet_scheduled set next_execution = date_add(next_execution, INTERVAL 7 DAY) where timesheet_id = {$template->timesheet_id}";
				} elseif ($template->frequency == 'm') {
					$sql = "update {$wpdb->prefix}timesheet_scheduled set next_execution = date_add(next_execution, INTERVAL 1 MONTH) where timesheet_id = {$template->timesheet_id}";
				} elseif ($template->frequency == 'q') {
					$sql = "update {$wpdb->prefix}timesheet_scheduled set next_execution = date_add(next_execution, INTERVAL 1 QUARTER) where timesheet_id = {$template->timesheet_id}";
				} elseif ($template->frequency == 'a') {
					$sql = "update {$wpdb->prefix}timesheet_scheduled set next_execution = date_add(next_execution, INTERVAL 1 YEAR) where timesheet_id = {$template->timesheet_id}";
				}
				
				$db->query($sql);
				
				$common->archive_records ('timesheet_scheduled', 'timesheet_scheduled_archive', "timesheet_id", $template->timesheet_id, '=', 'UPDATED');
				
				if (($template->expires_on < date('Y-m-d')) && ($template->delete_after_expiration == 1)) {
					echo "<BR>{$template->timesheet_id} wants to be deleted";
					$myDash->delete_template($template->timesheet_id);
				}
			}
		}
	}

	function queue_process_client_projects_changequeue() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$options = get_option('time_sheets');
		
		$sql = "update {$wpdb->prefix}timesheet_client_projects tcp
			inner join {$wpdb->prefix}timesheet_client_projects_changequeue tcpc on tcp.ProjectId = tcpc.ProjectId
			set tcp.ProjectName = tcpc.ProjectName,
				tcp.IsRetainer = tcpc.IsRetainer,
				tcp.MaxHours = tcpc.MaxHours,
				tcp.Active = tcpc.Active,
				tcp.notes = tcpc.notes,
				tcp.BillOnProjectCompletion = tcpc.BillOnProjectCompletion,
				tcp.flat_rate = tcpc.flat_rate,
				tcp.po_number = tcpc.po_number,
				tcp.sales_person_id = tcpc.sales_person_id,
				tcp.technical_sales_person_id = tcpc.technical_sales_person_id,
				tcp.close_on_completion = tcpc.close_on_completion,
				tcp.retainer_id = tcpc.retainer_id,
				tcp.hours_included = tcpc.hours_included,
				tcp.hourly_rate = tcpc.hourly_rate,
				tcp.max_monthly_bucket = tcpc.max_monthly_bucket
			where tcpc.process_date <= date(now()) and tcpc.is_deleted = 0 and is_processed = 0";

		$db->query($sql);
		
		$common->archive_records ('timesheet_client_projects', 'timesheet_client_projects_archive', "ProjectId", "(select ProjectId from {$wpdb->prefix}timesheet_client_projects_changequeue tcpc  where tcpc.process_date <= date(now()) and tcpc.is_deleted = 0 and is_processed = 0)", 'IN', 'UPDATED');
		
		echo "Updated projects<br>";
		
		if (isset($options['allow_money_based_retainers'])) {
			$sql = "update {$wpdb->prefix}timesheet_project_employee_titles pet
			inner join {$wpdb->prefix}timesheet_client_projects_changequeue tcpc on pet.project_id = tcpc.ProjectId
			inner join {$wpdb->prefix}timesheet_project_employee_titles_changequeue petc on petc.queue_id = tcpc.queue_id
					and pet.title_id = petc.title_id
				set pet.hourly_rate = petc.hourly_rate
			where tcpc.process_date <= date(now()) and tcpc.is_deleted = 0 and is_processed = 0";
			$db->query($sql);
			
			echo "Updated project title rates<br>";
		}

		$common->archive_records ('timesheet_client_projects_changequeue', 'timesheet_client_projects_changequeue_archive', "process_date <= date(now()) and is_deleted = 0 and is_processed", "0", '=', 'QUEUED');
		
		
		echo "Updated archive<br>";

		$sql = "update {$wpdb->prefix}timesheet_client_projects_changequeue tcpc
			set is_processed = 1
			where tcpc.process_date <= date(now()) and tcpc.is_deleted = 0 and tcpc.is_processed = 0";
		$db->query($sql);
		
		$common->archive_records ('timesheet_client_projects_changequeue', 'timesheet_client_projects_changequeue_archive', "process_date <= date(now()) and is_deleted = 0 and is_processed", "0", '=', 'UPDATED');

		echo "Updated queue<br>";


	}
	
	function queue_process_scheduled_recurring_changes() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$user = wp_get_current_user();
		$user_id = $user->ID;

		//process updates
		$sql = "update {$wpdb->prefix}timesheet_recurring_invoices_monthly b
			inner join {$wpdb->prefix}timesheet_recurring_invoices_monthly_schedule a on b.client_id = a.client_id
			set b.MonthlyHours = a.MonthlyHours,
				b.HourlyRate = a.HourlyRate,
				b.Notes = a.Notes,
				b.BillOnProjectCompletion = a.BillOnProjectCompletion,
				b.reqular_interval = a.regular_interval
			where date(a.active_date) = date(now())";
		
		$db->query($sql);
		
		//Archive the updated
		$sql = "insert into {$wpdb->prefix}timesheet_recurring_invoices_monthly_archive
		(created_by, created_on, client_id, action, MonthlyHours, HourlyRate, Notes, BillOnProjectCompletion, reqular_interval)
		select {$user_id}, now(), client_id, 'CRON UPD', MonthlyHours, HourlyRate, Notes, BillOnProjectCompletion, regular_interval
		from {$wpdb->prefix}timesheet_recurring_invoices_monthly_schedule a
		where client_id in (select client_id from {$wpdb->prefix}timesheet_recurring_invoices_monthly)
			and date(a.active_date) = date(now())
			and client_id not in (select client_id from {$wpdb->prefix}timesheet_recurring_invoices_monthly_archive where date(created_on) = date(now()) and created_by = {$user_id})";
		
		$db->query($sql);
		
		//Archive the inserts, but need to archive before the actual inserts
		$sql = "insert into {$wpdb->prefix}timesheet_recurring_invoices_monthly_archive
		(created_by, created_on, client_id, action, MonthlyHours, HourlyRate, Notes, BillOnProjectCompletion, reqular_interval)
		select {$user_id}, now(), client_id, 'CRON INS', MonthlyHours, HourlyRate, Notes, BillOnProjectCompletion, regular_interval
		from {$wpdb->prefix}timesheet_recurring_invoices_monthly_schedule a
		where client_id not in (select client_id from {$wpdb->prefix}timesheet_recurring_invoices_monthly)
			and date(a.active_date) = date(now())";
		
		$db->query($sql);
		
		//Add records for new recurring billings
		$sql = "insert into {$wpdb->prefix}timesheet_recurring_invoices_monthly
		(client_id, MonthlyHours, HourlyRate, Notes, BillOnProjectCompletion, reqular_interval)
		select client_id, MonthlyHours, HourlyRate, Notes, BillOnProjectCompletion, regular_interval
		from {$wpdb->prefix}timesheet_recurring_invoices_monthly_schedule a
		where client_id not in (select client_id from {$wpdb->prefix}timesheet_recurring_invoices_monthly)
			and date(a.active_date) = date(now())";
		
		
		$db->query($sql);
	}

	function queue_rejected_reminders() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$http = !empty($_SERVER['HTTPS']) ? "https" :  "http";

		$sql = "select u.user_email, t.timesheet_id, r.display_name rejected_by, u.display_name owner_name, r.user_email rejected_by_email
			from {$wpdb->prefix}timesheet_reject_message_queue trmq
			join {$wpdb->prefix}timesheet t on trmq.timesheet_id = t.timesheet_id
			join {$wpdb->users} r on trmq.rejected_by = r.ID
			join {$wpdb->users} u on t.user_id = u.ID
			where trmq.email_reminder_on <= now()";

		$timesheets = $db->get_results($sql);

		if ($timesheets) {
			foreach ($timesheets as $timesheet) {
				$subject = "Reminder to update a time sheet and resubmit it";
				$body = "The time sheet <a href='" . $http . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?page=show_timesheet&source=email&time_sheet_id=" . $timesheet->timesheet_id . "'>" . $timesheet->timesheet_id . "</a> (owned by {$timesheet->owner_name}) was rejected by {$timesheet->rejected_by} and needs to be updated and marked as 'Week Complete'.<P>";

				$common->send_email($timesheet->user_email, $subject, $body);
				$common->send_email($timesheet->rejected_by_email, $subject, $body);

				$sql = "update {$wpdb->prefix}timesheet_reject_message_queue
						set email_reminder_on = date_add(now(), interval 3 day)
					where timesheet_id = {$timesheet->timesheet_id}";
				$db->query($sql);

				$sql = "insert into {$wpdb->prefix}timesheet_reject_message_queue_archive
					(created_by, created_on, action, timesheet_id, rejected_by, rejected_on, email_reminder_on, notify_approved_on)
					select $user->ID, now(), 'UPDATED', timesheet_id, rejected_by, rejected_on, email_reminder_on, notify_approved_on
					FROM {$wpdb->prefix}timesheet_reject_message_queue
					WHERE timesheet_id= {$timesheet->timesheet_id}";
				$db->query($sql);

			}
		} else {
			echo "Nothing to do.<BR>";
		}

	}

	function queue_rejected_admin_messages() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$http = !empty($_SERVER['HTTPS']) ? "https" :  "http";

		$sql = "select u.user_email, t.timesheet_id, r.display_name rejected_by, u.display_name owner_name, r.user_email rejected_by_email, u.user_email
			from {$wpdb->prefix}timesheet_reject_message_queue trmq
			join {$wpdb->prefix}timesheet t on trmq.timesheet_id = t.timesheet_id
			join {$wpdb->users} r on trmq.rejected_by = r.ID
			join {$wpdb->users} u on t.user_id = u.ID
			where trmq.notify_approved_on <= now()";

		$timesheets = $db->get_results($sql);

		if (isset($timesheets)) {
			foreach ($timesheets as $timesheet) {
				$subject = "Time sheet has not been updated after reminder";
				$body = "The time sheet <a href='" . $http . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?page=show_timesheet&source=email&time_sheet_id=" . $timesheet->timesheet_id . "'>" . $timesheet->timesheet_id . "</a> (owned by <a href='mailto:" . $timesheet->user_email . "'>{$timesheet->owner_name}</a>) was rejected by {$timesheet->rejected_by} and has not been corrected and resubmitted.  The owner of the timesheet needs to be contacted.<P>";

				$common->send_email($timesheet->rejected_by_email, $subject, $body);

				$sql = "update {$wpdb->prefix}timesheet_reject_message_queue
						set notify_approved_on = date_add(now(), interval 7 day)
					where timesheet_id = {$timesheet->timesheet_id}";
				$db->query($sql);

				$sql = "insert into {$wpdb->prefix}timesheet_reject_message_queue_archive
					(created_by, created_on, action, timesheet_id, rejected_by, rejected_on, email_reminder_on, notify_approved_on)
					select $user->ID, now(), 'UPDATED', timesheet_id, rejected_by, rejected_on, email_reminder_on, notify_approved_on
					FROM {$wpdb->prefix}timesheet_reject_message_queue
					WHERE timesheet_id= {$timesheet->timesheet_id}";
				$db->query($sql);

			}
		} else {
			echo "Nothing to do.<BR>";
		}


	}

	function disable_projects() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$db = new time_sheets_db();
		
		$sql = "UPDATE {$wpdb->prefix}timesheet_client_projects 
			set Active = 0
			WHERE ProjectId IN (SELECT project_id FROM {$wpdb->prefix}timesheet_client_project_autoclose WHERE close_date < now())";
		var_dump($sql);
		$db->query($sql);
		echo "<BR>";
		$sql = "DELETE FROM {$wpdb->prefix}timesheet_client_project_autoclose
		WHERE close_date < now()";
		var_dump($sql);
		$db->query($sql);
	}
	
	function email_retainers_due() {
		//echo "starting email_retainers_due<br>";
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$options = get_option('time_sheets');
		$entry = new time_sheets_entry();
		$db = new time_sheets_db();
		$common = new time_sheets_common();

		$daysinmonth = date('t');
		$today = date('d');

		if ($today != $daysinmonth) {
			echo "This procedure has exited. It only runs on the last day of the month.";
			return;
		}

		if (isset($options['update_retainer_hours'])) {
			//echo "Fixing retainer hours avaialble.<br>";
			$sql = "update {$wpdb->prefix}timesheet_client_projects tcp
	set tcp.MaxHours = tcp.HoursUsed + (select MonthlyHours from {$wpdb->prefix}timesheet_recurring_invoices_monthly rim where tcp.ClientId = rim.client_id)
where tcp.IsRetainer = 1
order by tcp.ProjectId";

			var_dump($sql);

			$db->query($sql);
			
			$common->archive_records('timesheet_client_projects', 'timesheet_client_projects_archive', 'IsRetainer', '1');
			

		}

		if (isset($options['email_retainer_due'])) {
			$sql = "select DISTINCT u.user_email
				from {$wpdb->prefix}timesheet t
				join {$wpdb->users} u on t.user_id = u.ID
				join {$wpdb->prefix}timesheet_client_projects tcp on t.ProjectId = tcp.ProjectId
					and tcp.IsRetainer = 1
				WHERE start_date > DATE_ADD(now(), INTERVAL -60 DAY)";

			$timesheets = $db->get_results($sql);

			if ($timesheets) {
				$subject = "Retainers for this month are due";
				$body = "Timesheets for your retainer clients are due today for the prior month. Please submit them by tonight so invoices can be processed in the morning for last month.";

				foreach ($timesheets as $timesheet) {
					$common->send_email ($timesheet->user_email, $subject, $body);
				}
			}

			$sql = "SELECT DISTINCT u.user_email
				FROM {$wpdb->users} u
				JOIN {$wpdb->prefix}timesheet_approvers ta on u.ID = ta.user_id
				UNION
				SELECT DISTINCT u.user_email
				FROM {$wpdb->users} u
				JOIN {$wpdb->prefix}timesheet_invoicers ti on u.ID = ti.user_id";

			$users = $db->get_results($sql);

			if ($users) {
				$subject = "Monthly retainer time sheets will be ready soon";
				$body = "Monthly time sheets should be ready for processing tomorrow morning. This is your reminder to process them."; 
				foreach ($users as $user) {
					$common->send_email($user->user_email, $subject, $body);
				}
			}

		}
	}
	
	function truncate_email_queue_archive() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		
		$db = new time_sheets_db();
		
		$sql = "truncate table {$wpdb->prefix}timesheet_emailqueue_archive";
		
		$db->query($sql);
		
	}

	function email_late_timesheets() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$options = get_option('time_sheets');

		if (date('w') != $options['day_of_week_timesheet_reminders']) {
			return;
		}


		$entry = new time_sheets_entry();
		$common = new time_sheets_common();
		$db = new time_sheets_db();

		if ($options['email_late_timesheets']) {
			$sql = "select t.timesheet_id, u.user_email, DATE_ADD(start_date, INTERVAL 14 DAY)
				from {$wpdb->prefix}timesheet t
				join {$wpdb->users} u on t.user_id = u.ID
				where start_date > DATE_ADD(now(), INTERVAL -14 DAY)
					and week_complete = 0";
			$timesheets = $db->get_results($sql);

			if ($timesheets) {
				foreach ($timesheets as $timesheet) {
					$subject = "Timesheet pending completion";
					$body = "Timesheet <a href='http://$_SERVER[HTTP_HOST]/wp-admin/admin.php?timesheet_id={$timesheet->timesheet_id}&page=enter_timesheet'>{$timesheet->timesheet_id}</a> has not been marked as completed.<BR>";

					$common->send_email ($timesheet->user_email, $subject, $body);
				}
			}
		}
	}

	function process_email($all = NULL) {
		global $wpdb;
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$options = get_option('time_sheets');
		
		$db = new time_sheets_db();
		if ($all) {
			$sql = "select max(entered_on) entered_on from {$wpdb->prefix}timesheet_emailqueue";
		} else {
			$sql = "select max(entered_on) entered_on from {$wpdb->prefix}timesheet_emailqueue where entered_on not between date_add(now(),interval -5 minute) and now()";
		}
		$anything = $db->get_var ($sql);


		if (is_null($anything)) {
			echo "nothing to do";
			return;
		}

		if ($anything) {
			$sql = "select send_to, send_from_email, send_from_name, subject, count(*) ct
				from {$wpdb->prefix}timesheet_emailqueue
				group by send_to, send_from_email, send_from_name, subject";

			$groups = $db->get_results($sql);

			foreach ($groups as $group) {

				$sql = "select email_id, message_body
					from {$wpdb->prefix}timesheet_emailqueue
					where send_to = %s and send_from_email = %s and send_from_name = %s and subject = %s
					order by email_id";
				$parms = array($group->send_to, $group->send_from_email, $group->send_from_name, $group->subject);

				$rows = $db->get_results($sql, $parms);

				$message_body = "";
				$ids = "-1";

				foreach ($rows as $row) {
					$message_body = "{$message_body}<br>{$row->message_body}";
					$ids = "{$ids}, {$row->email_id}";
				}

				$header[] = "From: {$group->send_from_name} <{$group->send_from_email}>";
				$header[] = 'content-type: text/html';
				$message_body = $message_body . "<p><p>";

				$success = wp_mail($group->send_to, $group->subject, $message_body, $header);

				if ($success==false) {
					echo "<div if='message' class='error'><p>Error sending email to {$group->send_to}.</p></div>";
				} else {
					if (isset($options['show_email_notice'])) {
						echo "<div if='message' class='updated'><p>Email sent to {$group->send_to}.</p></div>";
					}
				}

				$sql = "insert into {$wpdb->prefix}timesheet_emailqueue_archive
					SELECT *, now()
					from {$wpdb->prefix}timesheet_emailqueue 
					where email_id in ({$ids})";
				$db->query($sql);
				
				
				$sql = "delete from {$wpdb->prefix}timesheet_emailqueue where email_id in ({$ids})";
				$db->query($sql);
			}
		}

		#$header[] = "From: {$options['email_name']} <{$options['email_from']}>";
		#$header[] = 'content-type: text/html';

		#$success = wp_mail($to, $subject, $body, $header);

		#if ($success==false) {
		#	echo "<div if='message' class='error'><p>Error sending email to {$to}</p></div>";
		#} else {
		#	if ($options['show_email_notice']) {
		#		echo "<div if='message' class='updated'><p>Email sent to {$to}</p></div>";
		#	}
		#}
	}

	function add_cron(){
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
    		return;
		}

		$options = get_option('time_sheets');
		
		if (!has_action('time_sheets_monthly_cron') || !has_action('time_sheets_email_check') ) {
			add_settings_error('action_not_enabled', 'error_action_not_enabled', 'The cron is not setup correctly.  Try deactivating and reactivating the plugin.', 'error');
		}

		if ( ! wp_next_scheduled( 'truncate_email_queue_archive') ) {
			$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			if ($time < current_time('timestamp')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			}
			wp_schedule_event($time, 'monthly', 'truncate_email_queue_archive');
		}
		
		if ( ! wp_next_scheduled( 'email_retainers_due') ) {
			$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			if ($time < current_time('timestamp')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			}
			wp_schedule_event($time, 'daily', 'email_retainers_due');
		}

		if ( ! wp_next_scheduled( 'time_sheets_monthly_cron') ) {
			$time = $this->time_to_wptime(strtotime("tomorrow 3am"));
			if ($time < current_time('timestamp')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 3am"));
			}
			wp_schedule_event($time, 'daily', 'time_sheets_monthly_cron');
		}

		if (! wp_next_scheduled('time_sheets_email_check') ) {
			$time = time();
			$time = $time+60;
			wp_schedule_event($time, 'minutes_5', 'time_sheets_email_check');
		}

		if (! wp_next_scheduled('time_sheets_email_late_timesheets') ) {
			$time = $this->time_to_wptime(strtotime("tomorrow 6pm"));
			if ($time < current_time('timestamp')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 6pm"));
			}
			wp_schedule_event($time, 'daily', 'time_sheets_email_late_timesheets');
		}

		
		if (! wp_next_scheduled('time_sheets_disable_projects') ) {
			$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			if ($time < current_time('timestamp')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			}
			wp_schedule_event($time, 'daily', 'time_sheets_disable_projects');
		}

		if (! wp_next_scheduled('queue_rejected_reminders') ) {
			$time = $this->time_to_wptime(strtotime("tomorrow 6pm"));
			if ($time < current_time('timestamp')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 6pm"));
			}
			wp_schedule_event($time, 'daily', 'queue_rejected_reminders');
		}

		if (! wp_next_scheduled('queue_rejected_admin_messages') ) {
			$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			if ($time < current_time('timestamp')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			}
			wp_schedule_event($time, 'daily', 'queue_rejected_admin_messages');
		}
		
		if (! wp_next_scheduled('queue_process_client_projects_changequeue')) {
			$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			if ($time < current_time('timestamp')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
			}
			wp_schedule_event($time, 'daily', 'queue_process_client_projects_changequeue');
		}

		if (isset($options['allow_recurring_timesheets'])) { //Ignore if the feature is disabled

			if (! wp_next_scheduled('queue_use_templates')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
				if ($time < current_time('timestamp')) {
					$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
				}
				wp_schedule_event($time, 'daily', 'queue_use_templates');
			}
		}
		if (isset($options['allow_money_based_retainers'])) { //Ignore if the feature is disabled

			if (! wp_next_scheduled('delete_expired_employee_title_overrides')) {
				$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
				if ($time < current_time('timestamp')) {
					$time = $this->time_to_wptime(strtotime("tomorrow 1am"));
				}
				wp_schedule_event($time, 'daily', 'delete_expired_employee_title_overrides');
			}
		}
		
		

	}

	function time_to_wptime($time) {
		$wp_time = current_time('timestamp');
		$php_time = time();
		$diff = $php_time-$wp_time;
		$time = $time+$diff;
		return $time;
	}

	function remove_cron() {
		
		
		$timestamp = wp_next_scheduled( 'truncate_email_queue_archive');
		wp_unschedule_event( $timestamp, 'truncate_email_queue_archive');


		$timestamp = wp_next_scheduled( 'email_retainers_due');
		wp_unschedule_event( $timestamp, 'email_retainers_due');

		$timestamp = wp_next_scheduled( 'time_sheets_monthly_cron');
		wp_unschedule_event( $timestamp, 'time_sheets_monthly_cron');

		$timestamp = wp_next_scheduled('time_sheets_email_check');
		wp_unschedule_event($timestamp, 'time_sheets_email_check');

		$timestamp = wp_next_scheduled('time_sheets_email_late_timesheets');
		wp_unschedule_event($timestamp, 'time_sheets_email_late_timesheets');
		
		$timestamp = wp_next_scheduled('time_sheets_disable_projects');
		wp_unschedule_event($timestamp, 'time_sheets_disable_projects');

		$timestamp = wp_next_scheduled('queue_rejected_reminders');
		wp_unschedule_event($timestamp, 'queue_rejected_reminders');

		$timestamp = wp_next_scheduled('queue_rejected_admin_messages');
		wp_unschedule_event($timestamp, 'queue_rejected_admin_messages');

		$timestamp = wp_next_scheduled('queue_process_client_projects_changequeue');
		wp_unschedule_event($timestamp, 'queue_process_client_projects_changequeue');

		
		$timestamp = wp_next_scheduled('queue_process_scheduled_recurring_changes');
		wp_unschedule_event($timestamp, 'queue_process_scheduled_recurring_changes');
		
		$timestamp = wp_next_scheduled('queue_use_templates');
		wp_unschedule_event($timestamp, 'queue_use_templates');
		
		$timestamp = wp_next_scheduled('delete_expired_employee_title_overrides');
		wp_unschedule_event($timestamp, 'delete_expired_employee_title_overrides');
	}

	function InsertRecurringInvoices() {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$db = new time_sheets_db();


		$currentTime = getdate();
		
		var_dump($currentTime);
		echo "<BR>";
		echo "wday ".$currentTime['wday']."<BR>";
		echo "mday ".$currentTime['mday']."<BR>";
		echo "mon ".$currentTime['mon']."<BR>";
		
		#Weekly Invoices sent on Monday
		$sql = "select * 
			from {$wpdb->prefix}timesheet_custom_retainer_frequency
			where w_m_q_interval = 'w' and interval_value = {$currentTime['wday']}  and date_add(last_trigger_date, INTERVAL 7 DAY) < now() and active = 1";
		$retainers = $db->get_results($sql);

		if ($retainers) {
			foreach ($retainers as $retainer) {
				$this->InsertMonthlyInvoices($retainer->retainer_name, $retainer->retainer_id);


				$sql = "update {$wpdb->prefix}timesheet_custom_retainer_frequency
					set last_trigger_date = now()
					where retainer_id = {$retainer->retainer_id}";
				$db->query($sql);
			}
		}

		if ($currentTime['wday']==1)
		{
			echo "Weekly timesheets<br>";
			$this->InsertMonthlyInvoices('Weekly', 2);
		}
		
		#Monthly Invoices sent on the first of the month
		if ($currentTime['mday']==1)
		{

			$sql = "select * 
			from {$wpdb->prefix}timesheet_custom_retainer_frequency where w_m_q_interval = 'm' and date_add(last_trigger_date, INTERVAL interval_value MONTH) < now() and active = 1";
			$retainers = $db->get_results($sql);


			if ($retainers) {
				foreach ($retainers as $retainer) {
					echo "{$retainer->retainer_name}<br>";

					$this->InsertMonthlyInvoices($retainer->retainer_name, $retainer->retainer_id);

					$sql = "update {$wpdb->prefix}timesheet_custom_retainer_frequency
						set last_trigger_date = now()
					where retainer_id = {$retainer->retainer_id}";
					$db->query($sql);

				}
			}


			echo "Monthly timesheets<br>";
			$this->InsertMonthlyInvoices('Monthly', 1);
		}
		
		#Quarterly Invoices sent on the first of the month, the first month of the quarter
		if ($currentTime['mday']==1 )
		{

			$sql = "select * 
			from {$wpdb->prefix}timesheet_custom_retainer_frequency
			where w_m_q_interval = 'q' and date_add(last_trigger_date, INTERVAL interval_value*3 MONTH) < now() and active = 1";
			$retainers = $db->get_results($sql);

			if ($retainers) {
				foreach ($retainers as $retainer) {
					echo "{$retainer->retainer_name}<br>";

					$this->InsertMonthlyInvoices($retainer->retainer_name, $retainer->retainer_id);

					$sql = "update {$wpdb->prefix}timesheet_custom_retainer_frequency
						set last_trigger_date = now()
					where retainer_id = {$retainer->retainer_id}";
					$db->query($sql);

				}
			}


			if ($currentTime['mon']==1 || $currentTime['mon']==4 || $currentTime['mon']==7 ||$currentTime['mon']==10 ) {
				echo "Quartly timesheets";
				$this->InsertMonthlyInvoices('Quarterly', 3);
			}
		}


		
	}
	
	function InsertMonthlyInvoices($IntervalName, $IntervalTypeId) {
		global $wpdb;
		
		if ( defined( 'TIMESHEETS_SKIP_CRON' ) && true ) {
			echo "Timesheet Cron is disabled";
			return;
		}
		
		$db = new time_sheets_db();
		$entry = new time_sheets_entry();
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		$user_id = $options['cron_user'];

		$currentTime = getdate();

		$sql = "select count(*) rows1 from {$wpdb->prefix}timesheet";
		$rows_before = $db->get_row($sql);
					
		$sql = "insert into {$wpdb->prefix}timesheet (user_id, start_date, entered_date, ProjectId, monday_hours, tuesday_hours, wednesday_hours, thursday_hours, friday_hours, saturday_hours, sunday_hours, total_hours, other_expenses_notes, week_complete, marked_complete_by, marked_complete_date, ClientId, Approved, Approved_by, approved_date, payrolled, payrolled_on, payrolled_by)
SELECT $user_id, CURDATE(), CURDATE(), b.ProjectId, 0, 0, 0, 0, 0, 0, 0, 0, 'Bill Client for {$IntervalName} Retainer and zero out needed retainer hours.', 1, 1, CURDATE(), b.ClientId, 1, 1, CURDATE(), 1, CURDATE(), 1
FROM {$wpdb->prefix}timesheet_client_projects b 
where IsRetainer = 1
and b.retainer_id={$IntervalTypeId}
and b.Active = 1";
		$db->query($sql);
		
		$sql = "select count(*) rows1 from {$wpdb->prefix}timesheet";
		$rows_after = $db->get_row($sql);
		
		$rows = $rows_after->rows1 - $rows_before->rows1;

		echo $rows . " records inserted.<p>";

		#if ($rows->records !== 0 || $rows->records !== -1) {
		if ($rows != 0) {

			$sql = "select user_email
			from {$wpdb->users} u
			join {$wpdb->prefix}timesheet_approvers a on u.ID = a.user_id";

			$users = $db->get_results($sql);

			$subject = "{$IntervalName} retainers have been entered into the timesheet system.";
			$body = "{$IntervalName} retainers have been entered into the timesheet system and need to be processed.  These retainer hour's need to be zeroed out as well.  
<P>
	There were {$rows} invoices entered.
<P>
	The retainers can be viewed and invoiced from the <a href='http://$_SERVER[HTTP_HOST]/wp-admin/admin.php?page=invoice_timesheet'>invoicing menu</a>.";
			if ($options['enable_email']) {
				foreach ($users as $user) {
					$common->send_email ($user->user_email, $subject, $body);
				}
			}
		}
	}
}