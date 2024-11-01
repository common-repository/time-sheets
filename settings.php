<?php
class time_sheets_settings {
	
	function setup_employee_titles() {
		if (isset($_GET['action'])) {
			if ($_GET['action'] == 'Save Title') {
				$this->save_employee_title();
			}
			
			if ($_GET['action'] == 'delete') {
				$this->delete_employee_title();
			}
		}
		
		if (isset($_POST['action'])) {
			if ($_POST['action'] == "Save Mappings") {
				$this->update_employee_to_title();
			}
		}
		$this->manage_employee_titles();

		$this->match_employee_to_title();
	}
	
	function get_employee_list($project_id = NULL) {
		global $wpdb;
		$db = new time_sheets_db();
		
		$sql = "select ID, display_name, user_login, (case when e.title_id is null then 0 else e.title_id end) as title_id";
		if (isset($project_id)) {
			$sql = $sql . ", (case when eto.title_id is null then 0 else eto.title_id end) as override_title_id, eto.expiration_date";
		}
		$sql = $sql . "
		from {$wpdb->users} u
		left outer join {$wpdb->prefix}timesheet_employee_title_join e on u.ID = e.user_id
		left outer join {$wpdb->prefix}usermeta um on u.id = um.user_id ";
		if (isset($project_id)) {
			$sql = $sql . "	left outer join {$wpdb->prefix}timesheet_customer_project_employee_title_override eto on eto.user_id = u.ID and eto.project_id = %d ";
		}
		$sql = $sql . "
		where (um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}')
		order by u.display_name";
		

		if (isset($project_id)) {
			$params = array($project_id);
			$users = $db->get_results($sql, $params);
		} else {
			$users = $db->get_results($sql);
		}

		return $users;
	}
	
	function match_employee_to_title($show_expiration = NULL, $inputs = NULL) {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		
		$sql = "select * from {$wpdb->prefix}timesheet_employee_titles order by name";
		$titles = $db->get_results($sql);
		
		$users = $this->get_employee_list((isset($inputs)?$inputs['ProjectId']:NULL));
		$nonce = wp_create_nonce('match_employee_to_title'); 
		
		if (!isset($inputs)) {
			$page = "setup_employee_titles";
			$colspan=2;
		} else {
			$page = $inputs['page'] . "&menu={$inputs['menu']}&ClientId={$inputs['ClientId']}&ProjectId={$inputs['ProjectId']}";
			echo "<P>";
			$colspan=3;
		}
		
		echo "<form method='post' action='./admin.php?page=$page'>
		<input type='hidden' name='page' value='$page'>
		<input type='hidden' name='_wpnonce' value='{$nonce}'>
		<table border='1' cellpadding='0' cellspacing='0'><tr><td>Employee</td><td>Job Title</td>";
		if (isset($show_expiration)) {
			echo "<td>Expiration Date</td>";
		}
		
		echo "<TR>";
		if ($users) {
			foreach ($users as $user) {
				echo "<tr><td>{$user->display_name} ({$user->user_login})</td><td>";
				echo "<select name='user_{$user->ID}'>
						<option value=''>--None</option>";
				foreach ($titles as $title) {
					$selected = $common->is_match($user->title_id,$title->title_id,' selected');
					if (isset($show_expiration)) {
						$selected = ($user->override_title_id==$title->title_id?' selected':$selected);						
					}
					echo "<option value='{$title->title_id}'{$selected}>{$title->name}</option>";
				}
				echo "</select></td>";
				if (isset($show_expiration)) {
					echo "<td><input type='date' name='employee_expiration_{$user->ID}' value='{$user->expiration_date}'></td>";
				}
				echo "</tr>";
			}
		}

		echo "<tr><td colspan='{$colspan}'><input type='submit' value='Save Mappings' name='action' class='button-primary'></td></tr>";
		echo "</table>";

		echo "</form>";
		
	}
	
	function update_employee_to_title() {
		global $wpdb;
		$db = new time_sheets_db();
		
		$nonce = check_admin_referer('match_employee_to_title');
		
		$users = $this->get_employee_list();
		
		if ($users) {
			foreach ($users as $user) {
				if (isset($_POST["user_{$user->ID}"]) && $_POST["user_{$user->ID}"] != '') {
					$sql = "insert into {$wpdb->prefix}timesheet_employee_title_join
					(user_id, title_id)
					values
					(%d, %d)
					on duplicate key update title_id=%d";
					$params = array($user->ID, intval($_POST["user_{$user->ID}"]), intval($_POST["user_{$user->ID}"]));
					$db->query($sql, $params);
				} else {
					$sql = "delete from {$wpdb->prefix}timesheet_employee_title_join where user_id = {$user->ID}";
					$db->query($sql);
				}
			}
		}
	}
	
	function manage_employee_titles() {
		global $wpdb;
		$db = new time_sheets_db();
		$folder = plugins_url();

		$sql = "select * from {$wpdb->prefix}timesheet_employee_titles order by name";
		
		$titles = $db->get_results($sql);
		
		$nonce = wp_create_nonce('manage_employee_titles'); 
		
		echo "<form>
		<input type='hidden' name='_wpnonce' value='{$nonce}'>
		<table>
		<tr align='center'><td>Job Title:</td><td><input type='text' name='name'></td></tr>
		<tr><td colspan='2'><input type='submit' value='Save Title' name='action' class='button-primary'></td></tr>
		</table>
		<input type='hidden' name='page' value='setup_employee_titles'>
		</form><P>";
		
		if ($titles) {
			echo "<table border='1' cellpadding='0' cellspacing='0'><tr><td>Delete</td><td>Title</td></tr>";
			foreach ($titles as $title) {
				echo "<tr><td align='center'><a href='admin.php?page=setup_employee_titles&action=delete&title_id={$title->title_id}&_wpnonce={$nonce}'><img src='{$folder}/time-sheets/x.png' height='13' width='13'></a></td><td>{$title->name}</td></tr>";
			}
			echo "</table>
			<p><p>
			* Deleting a title will remove all data about that title from all projects in the system. This can not be undone.";
		}
	}
	
	function delete_employee_title() {
		global $wpdb;
		$db = new time_sheets_db();
		
		$nonce = check_admin_referer('manage_employee_titles');
		
		$sql = "delete from {$wpdb->prefix}timesheet_project_employee_titles where title_id = %d";
		$param = array($_GET['title_id']);
		$db->query($sql, $param);
		
		$sql = "delete from {$wpdb->prefix}timesheet_employee_titles where title_id = %d";
		$param = array($_GET['title_id']);
		$db->query($sql, $param);
		
		
	}
	
	function save_employee_title() {
		global $wpdb;
		$db = new time_sheets_db();
		
		$nonce = check_admin_referer('manage_employee_titles');
		
		$sql = "select * from {$wpdb->prefix}timesheet_employee_titles where name = %s";
		$param = array($_GET['name']);
		$title = $db->get_row($sql, $param);
		
		if ($title) {
			echo "<div class='notice notice-error is-dismissible'><p>Title has already been entered.</p></div>";
				return;
		}
		
		$sql = "insert into {$wpdb->prefix}timesheet_employee_titles
		(name)
		values
		(%s)";
		$db->query($sql, $param);
	}

	function employees_allways_to_payroll() {
		if (isset($_POST['action']) && $_POST['action']=="save") {
			$this->employees_always_to_payroll_update_emps();
		}

		$this->employees_always_to_payroll_show_emps();
	}

	function employees_always_to_payroll_update_emps() {
		global $wpdb;
		$db = new time_sheets_db();

		check_admin_referer( 'add_user_to_payroll');

		$sql = "select u.ID, u.user_login, eatp.user_id, u.display_name
			from {$wpdb->users} u
			left outer join {$wpdb->prefix}timesheet_employee_always_to_payroll eatp on u.ID = eatp.user_id
			order by u.user_login";

		$users = $db->get_results($sql);
		if ($users) {
			foreach ($users as $user) 
			{
				$id = "user_{$user->ID}";

				if (isset($_POST[$id]) && $user->user_id == "") {
					$sql = "INSERT INTO {$wpdb->prefix}timesheet_employee_always_to_payroll (user_id) VALUES ({$user->ID})";
					$db->query($sql);
				}

				IF (!isset($_POST[$id]) && $user->user_id != "") {
					$sql = "DELETE FROM {$wpdb->prefix}timesheet_employee_always_to_payroll WHERE user_id = {$user->ID}";
					$db->query($sql);
				}
			}
		}

		echo '<div class="notice notice-success is-dismissible">Employees Updated.</div>';
	}

	function employees_always_to_payroll_show_emps() {
		global $wpdb;
		$db = new time_sheets_db();

		$sql = "select u.ID, u.display_name, u.user_login, eatp.user_id, u.display_name
			from {$wpdb->users} u
			join {$wpdb->prefix}usermeta um on u.id = um.user_id
			left outer join {$wpdb->prefix}timesheet_employee_always_to_payroll eatp on u.ID = eatp.user_id
			where  (um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}')
			order by u.display_name";

		$users = $db->get_results($sql);

		if ($users) {
			echo "<BR><BR><form method='POST' name='force_employee'><table border='1' cellspacing='0'><tr align='center'><td><b>Force Employee<br>Timesheets to<br>Payroll</b></td><td><b>Employee</b></td><td><b>User Name</b></td></tr>";
			foreach ($users as $user) {
				echo "<tr><td align='center'><input type='checkbox' name='user_{$user->ID}' value='1'";
				if ($user->user_id) {
					echo " checked";
				}
				echo "></td><td>{$user->display_name}</td><td>{$user->user_login}</td></tr>";
			}
			echo "</table><input type='submit' name='submit' value='Save Settings' class='button-primary'>
<input type='hidden' name='page' value='employees_allways_to_payroll'><input type='hidden' name='action' value='save'>";

			wp_nonce_field( 'add_user_to_payroll' );

			echo "</form>";
		} else {
			echo "You have no logins, this shouldn't be possible. Something is very wrong.";
		}
	}

	

	function setup_approval_teams() {
		if (isset($_GET['action']) && $_GET['action']=='add') {
			$this->add_member_to_team();
		}
		if (isset($_GET['action']) && $_GET['action']=='delete') {
			$this->remove_member_from_team();
		}

		$this->show_users_as_team_leads();

		if (isset($_GET['approver_user_id']) && $_GET['approver_user_id']) {
			$this->show_leads_team(intval($_GET['approver_user_id']));
		}
	}

	function add_member_to_team() {
		global $wpdb;
		$db = new time_sheets_db();
		
		
		$user = wp_get_current_user();

		$sql = "insert into {$wpdb->prefix}timesheet_approvers_approvies (approver_user_id, approvie_user_id)
			values (%d, %d)";
		$values = array(intval($_GET['approver_user_id']), intval($_GET['approvie_user_id']));

		$db->query($sql, $values);

		$sql = "insert into {$wpdb->prefix}timesheet_approvers_approvies_archive
						select $user->ID, now(), 'INSERTED', approver_user_id, approvie_user_id
						FROM {$wpdb->prefix}timesheet_approvers_approvies
						where approver_user_id = %d and approvie_user_id = %d";
		$parms = array(intval($_GET['approver_user_id']), intval($_GET['approvie_user_id']));
		$db->query($sql, $parms);
		
	}

	function remove_member_from_team() {
		global $wpdb;
		$db = new time_sheets_db();
		
		$user = wp_get_current_user();
		
		$sql = "insert into {$wpdb->prefix}timesheet_approvers_approvies_archive
						select $user->ID, now(), 'DELETED', approver_user_id, approvie_user_id
						FROM {$wpdb->prefix}timesheet_approvers_approvies
						where approver_user_id = %d and approvie_user_id = %d";
		$parms = array(intval($_GET['approver_user_id']), intval($_GET['approvie_user_id']));
		$db->query($sql, $parms);
		
		

		$sql = "delete from {$wpdb->prefix}timesheet_approvers_approvies where approver_user_id = %d and approvie_user_id = %d";
		$values = array(intval($_GET['approver_user_id']), intval($_GET['approvie_user_id']));

		$db->query($sql, $values);
	}

	function show_leads_team($approver_user_id){
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$folder = plugins_url();

		$users = $common->return_approvers_team_list($approver_user_id, 0);

		echo "<table border='1' cellpadding='0' cellspacing='0'><tr><td>Add Team Member</td><td>Remove Team Member</td><td>User</td></td><td>User Name</td></tr>";
		foreach ($users as $user) {
			echo "<tr><td align='center'>";
			if (!$user->a) {
				echo "<a href='admin.php?page=setup_approval_teams&action=add&approvie_user_id={$user->id}&approver_user_id={$approver_user_id}'><img src='{$folder}/time-sheets/check.png' height='13' width='13'></a>";
			}
			echo "</td><td align='center'>";
			if ($user->a) {
				echo "<a href='admin.php?page=setup_approval_teams&action=delete&approvie_user_id={$user->id}&approver_user_id={$approver_user_id}'><img src='{$folder}/time-sheets/x.png' height='13' width='13'></a>";
			}
			echo "</td><td>{$user->display_name}</td><td>{$user->user_login}</td></tr>";
		}
		echo "</table>";

	}

	function show_users_as_team_leads() {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();

		$sql = "select u.user_login, ta.user_id a, u.id, u.display_name
		from {$wpdb->users} u
		join {$wpdb->prefix}usermeta um on u.id = um.user_id
		join {$wpdb->prefix}timesheet_approvers ta ON u.id = ta.user_id
		where um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}'
		order by u.display_name";

		$users = $db->get_results($sql);

		echo "<form method='GET'>";
		echo "Select Approver To Modify: ";
		echo "<select name='approver_user_id'>";
		
		$approver = isset($_GET['approver_user_id']) ? $_GET['approver_user_id'] : "";

		foreach ($users as $user) {
			echo "<option value='{$user->id}'{$common->is_match($user->id, $approver, ' SELECTED')}>{$user->display_name} ({$user->user_login})</option>";
		}
		echo "</select>";
		echo "<input type='hidden' name='page' value='setup_approval_teams'>";
		echo '<br class="submit"><input name="submit" type="submit" class="button-primary" value="Select User" /></div>';
		echo "</form>";
	}

	function get_active_tab($tab) {
		if ($tab == "automation" || $tab == "") {
			$active_tab = "automation";
		} elseif ($tab == "display_settings") {
			$active_tab = "display_settings";
		} elseif ($tab == "retainer") {
			$active_tab = "retainer";
		} elseif ($tab == "basics") {
			$active_tab = "basics";
		} elseif ($tab == "payroll") {
			$active_tab = "payroll";
		}
		if (isset($active_tab)) {
			return $active_tab;
		}
	}
	
	function show_settings_page() {
		settings_errors();
		
		if (isset($_GET["tab"])) {
			$tab = $_GET["tab"];
		} else {
			$tab = '';
		}
		
		$active_tab = $this->get_active_tab($tab);

		
		
		echo '<div class="wrap">';
		echo '<H2>Time Sheets Settings</H2>';
		?>
		<h2 class="nav-tab-wrapper">
			<!-- when tab buttons are clicked we jump back to the same page but with a new parameter that represents the clicked tab. accordingly we make it active -->
			<a href="?page=time_sheets_settings&tab=automation" class="nav-tab <?php if($active_tab == 'automation'){echo 'nav-tab-active';} ?> "><?php _e('Automation', 'sandbox'); ?></a>
			<a href="?page=time_sheets_settings&tab=display_settings" class="nav-tab <?php if($active_tab == 'display_settings'){echo 'nav-tab-active';} ?>"><?php _e('Display Settings', 'sandbox'); ?></a>
			<a href="?page=time_sheets_settings&tab=retainer" class="nav-tab <?php if($active_tab == 'retainer'){echo 'nav-tab-active';} ?>"><?php _e('Retainer Settings', 'sandbox'); ?></a>
			<a href="?page=time_sheets_settings&tab=basics" class="nav-tab <?php if($active_tab == 'basics'){echo 'nav-tab-active';} ?>"><?php _e('Email Settings', 'sandbox'); ?></a>
			<a href="?page=time_sheets_settings&tab=payroll" class="nav-tab <?php if($active_tab == 'payroll'){echo 'nav-tab-active';} ?>"><?php _e('Payroll Settings', 'sandbox'); ?></a>
</h2>

		<?php
		echo '<form name="settingsFrm" action="options.php" method="post">';
		settings_fields('time_sheets');
		do_settings_sections('time_sheets');
		
		echo "<input type='hidden' name='tab' value='{$active_tab}'>";
		submit_button();
		echo '</form>
<br>
<br>If you wish to display the New Timesheet form on a page within your site, use the shortcode "[timesheet_entry]" within the page.
<br>If you wish to display the Open Timesheets form on a page within your site, use the shortcode "[timesheet_search]" within the page.
<br>Both these shortcodes will only work if the user is logged in, and will display an error message if the user is not logged in.
<BR><BR>Be sure to save settings before switching tabs.</div>';
		
		echo "<script>
			function check_ot() {
				
				if (settingsFrm.allow_overtime.checked==true) {
					settingsFrm.show_overtime_when_empty.disabled=false;
				} else {
					settingsFrm.show_overtime_when_empty.disabled=true;
				}
			}

			function requesting_access_js() {
				if (settingsFrm.elements['time_sheets[enable_requesting_access]'].checked==true) {
					settingsFrm.elements['time_sheets[enable_requesting_access_self_approve]'].disabled=false;
				} else {
					settingsFrm.elements['time_sheets[enable_requesting_access_self_approve]'].checked=false;
					settingsFrm.elements['time_sheets[enable_requesting_access_self_approve]'].disabled=true;
				}
			}

			function toggleSetting() {
				if (settingsFrm.elements['time_sheets[override_date_format]'].value=='system_defined') {
					settingsFrm.elements['time_sheets[new_date_format]'].disabled=true;
				} else {
					settingsFrm.elements['time_sheets[new_date_format]'].disabled=false;
				}

			}
";

		if (isset($_GET['tab'])) {
			$tab = $this->get_active_tab($_GET['tab']);
		} else {
			$tab = $this->get_active_tab('');
		}

		if ($tab = 'display_settings') {
			echo "requesting_access_js();
			toggleSetting();
			";
		} elseif ($tab = "payroll") {
			echo "check_ot();";
		}


			echo "</script>";
	}

	function settings_header_email() {
		echo "Email settings for the time sheets application.";
	}

	function settings_validate($inputs) {
		$options = get_option('time_sheets');
		
		if (isset($_POST["tab"])) {
			$tab = $_POST["tab"];
		} else {
			$tab = '';
		}
			
		$active_tab = $this->get_active_tab($tab);

		if ($active_tab != "automation") {
			$inputs["cron_user"] = $options["cron_user"];
			$inputs["project_trigger_percent"] = $options["project_trigger_percent"];
			$inputs["email_address_on_project_over"] = $options["email_address_on_project_over"];
			$inputs["default_auto_close"] = $options["default_auto_close"];
			$inputs["allow_recurring_timesheets"] = $options["allow_recurring_timesheets"];
		}
		if ($active_tab != "display_settings") {
			$inputs["display_notes_toggle"] = $options["display_notes_toggle"];
			$inputs["display_notes_list"] = $options["display_notes_list"];
			$inputs["display_expenses_toggle"] = $options["display_expenses_toggle"];
			$inputs["display_expenses_list"] = $options["display_expenses_list"];
			$inputs["menu_location"] = $options["menu_location"];
			$inputs["users_override_location"] = $options["users_override_location"];
			$inputs["show_header_queues"] = $options["show_header_queues"];
			$inputs["show_header_open_invoices"] = $options["show_header_open_invoices"];
			$inputs["rel_url_to_timesheet"] = $options["rel_url_to_timesheet"];
			$inputs["override_date_format"] = $options["override_date_format"];
			$inputs["new_date_format"] = $options["new_date_format"];
			$inputs["user_specific_date_format"] = $options["user_specific_date_format"];
			$inputs["hide_client_project"] = $options["hide_client_project"];
			$inputs["remove_embargo"] = $options["remove_embargo"];
			$inputs["week_starts"] = $options["week_starts"];
			$inputs["queue_order"] = $options["queue_order"];
			$inputs["sales_override"] = $options["sales_override"];
			$inputs["hide_notes"] = $options["hide_notes"];
			$inputs["currency_char"] = $options["currency_char"];
			$inputs["distance_metric"] = $options["distance_metric"];
			$inputs["enable_non_billable_time"] = $options["enable_non_billable_time"];
			$inputs["enable_requesting_access"] = $options["enable_requesting_access"];
			$inputs["enable_requesting_access_self_approve"] = $options["enable_requesting_access_self_approve"];
			$inputs["require_unique_project_names"] = $options["require_unique_project_names"];
			$inputs["disable_cacheing"] = $options["disable_cacheing"];
		}
		
		if ($active_tab != "retainer") {
			$inputs["update_retainer_hours"] = $options["update_retainer_hours"];
			$inputs["retainers_per_client"] = $options["retainers_per_client"];
			$inputs["allow_money_based_retainers"] = $options["allow_money_based_retainers"];
		}
		
		if ($active_tab != "basics") {
			$inputs["enable_email"] = $options["enable_email"];
			$inputs["email_late_timesheets"] = $options["email_late_timesheets"];
			$inputs["email_retainer_due"] = $options["email_retainer_due"];
			$inputs["email_from"] = $options["email_from"];
			$inputs["email_name"] = $options["email_name"];
			$inputs["show_email_notice"] = $options["show_email_notice"];
			$inputs["hide_dcac_ad"] = $options["hide_dcac_ad"];
			$inputs["day_of_week_timesheet_reminders"] = $options["day_of_week_timesheet_reminders"];
		}
		
		if ($active_tab != "payroll") {
			$inputs["mileage"] = $options["mileage"];
			$inputs["per_diem"] = $options["per_diem"];
			$inputs["flight_cost"] = $options["flight_cost"];
			$inputs["hotel"]  = $options["hotel"];
			$inputs["rental_car"] = $options["rental_car"];
			$inputs["taxi"] = $options["taxi"];
			$inputs["tolls"] = $options["tolls"];
			$inputs["other_expenses"] = $options["other_expenses"];
			$inputs["allow_overtime"] = $options["allow_overtime"];
			$inputs["ot_multiplier"] = $options["ot_multiplier"];
			$inputs["show_overtime_when_empty"] = $options["show_overtime_when_empty"];
			$inputs["allow_users_to_reject"] = $options["allow_users_to_reject"];
			$inputs["show_non_billable_when_empty"] = $options["show_non_billable_when_empty"];
		}
		
		if (!is_numeric($inputs['project_trigger_percent']) || $inputs['project_trigger_percent'] < 1 || $inputs['project_trigger_percent'] > 100) {
			add_settings_error('project_trigger_percent', 'error_project_trigger_percent', 'The Project Trigger Percernt must be a number between 1 and 100.', 'error');
			
		}
		
		if (isset($inputs["image_upload_path"])) {
			$upload =wp_upload_dir();
			$upload_dir = $upload['basedir'];
			$upload_dir = $upload_dir . '/' . $inputs["image_upload_path"];
			if (! is_dir($upload_dir)) {
			   mkdir( $upload_dir, 0700 );
			}
		}
		
		
		
		#update_settings('time_sheets', $inputs);
		return $inputs;
	}

	function settings_header_automation() {
		echo "";
	}

	function settings_header_display() {
		echo "";
	}

	function user_can_enter_notes($user_id, $setting, $array_setting) {
		$options = get_option('time_sheets');
		global $wpdb;
		$db = new time_sheets_db();

		$sql = "select u.user_login, ta.user_id a, u.id
		from {$wpdb->users} u
		join {$wpdb->prefix}timesheet_approvers_approvies taa on u.id = taa.approvie_user_id
		join {$wpdb->prefix}timesheet_approvers ta ON taa.approver_user_id = ta.user_id
		where u.ID = {$user_id}";

		$users = $db->get_results($sql);

		$array_value = isset($options[$array_setting]) ? $options[$array_setting] : array();

		
		if ($users) {
			foreach ($users as $user) {
				if ($options[$setting]=='all_except') {
					if (in_array($user->id, $array_value)) {
						return 0;
					}
				} else { //only_these
					if (in_array($user->id, $array_value)) {
						return 1;
					}
				}
			}
			if (isset($options[$setting])) {
				$setting_value = $options[$setting];
			} else {
				$setting_value = '';
			}
			if ($setting_value=='all_except') {
				return 1;
			} else { //only_these
				return 0;
			}
		} else {
			if ($options[$setting]=='all_except') {
				return 1;
			} else { //only_these
				return 0;
			}
		}		
	}

	function display_notes_list() {
		$options = get_option('time_sheets');
		global $wpdb;
		$db = new time_sheets_db();

		$sql = "select u.user_login, ta.user_id a, u.id, u.display_name
		from {$wpdb->users} u
		join {$wpdb->prefix}timesheet_approvers ta ON u.ID = ta.user_id
		join {$wpdb->prefix}usermeta um on u.id = um.user_id 
		where um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}'
		order by u.user_login";

		$users = $db->get_results($sql);

		$rows = count($users);

		if ($rows > 6) {
			$rows = 6;
		}

		echo "<select multiple size='{$rows}' name='time_sheets[display_notes_list][]'>";

		foreach ($users as $user) {
			$match = "";
			if (isset($options['display_notes_list']) && in_array($user->id, $options['display_notes_list'])) {
				$match = " selected";
			}
			echo "<option value='{$user->id}'{$match}>{$user->display_name} ({$user->user_login})</option>";
		}

		echo "</select> (Hold control key for multiple values)";
	}

	function display_notes_toggle() {
		$options = get_option('time_sheets');

		if (isset($options['display_notes_toggle'])) {
			if ($options['display_notes_toggle'] == 'all_except') {
				$all_except = ' checked';
				$only_these = '';
			} else {
				$all_except = '';
				$only_these = ' checked';
			}
		} else {
			$all_except = '';
			$only_these = ' checked';
		}

		echo "<input type='radio' name='time_sheets[display_notes_toggle]' value='all_except'{$all_except}> All users except... <BR>
<input type='radio' name='time_sheets[display_notes_toggle]' value='only_these'{$only_these}> Only these users...";
	}

	function display_expenses_list() {
		$options = get_option('time_sheets');
		global $wpdb;
		$db = new time_sheets_db();

		$sql = "select u.user_login, ta.user_id a, u.id, u.display_name
		from {$wpdb->users} u
		join {$wpdb->prefix}timesheet_approvers ta ON u.ID = ta.user_id
		join {$wpdb->prefix}usermeta um on u.id = um.user_id 
		where um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}'
		order by u.user_login";

		$users = $db->get_results($sql);

		$rows = count($users);

		if ($rows > 6) {
			$rows = 6;
		}

		echo "<select multiple size='{$rows}' name='time_sheets[display_expenses_list][]'>";

		foreach ($users as $user) {
			$match = "";
			if (isset($options['display_expenses_list']) && in_array($user->id, $options['display_expenses_list'])) {
				$match = " selected";
			}
			echo "<option value='{$user->id}'{$match}>{$user->display_name} ({$user->user_login})</option>";
		}

		echo "</select> (Hold control key for multiple values)";
	}



	function display_expenses_toggle() {
		$options = get_option('time_sheets');

		if (isset($options['display_expenses_toggle'])) {
			if ($options['display_expenses_toggle'] == 'all_except') {
				$all_except = ' checked';
				$only_these = '';
			} else {
				$all_except = '';
				$only_these = ' checked';
			}
		} else {
			$all_except = '';
			$only_these = '';
		}
		echo "<input type='radio' name='time_sheets[display_expenses_toggle]' value='all_except'{$all_except}> All users except... <BR>
<input type='radio' name='time_sheets[display_expenses_toggle]' value='only_these'{$only_these}> Only these users...";
	}

	function blank() {

	}

	function day_of_week_timesheet_reminders() {
		$options = get_option('time_sheets');
		$common = new time_sheets_common();

		echo "<select name='time_sheets[day_of_week_timesheet_reminders]'>
			<option value='0'{$common->is_match($options['day_of_week_timesheet_reminders'], 0, ' selected')}>Sunday</option>
			<option value='1'{$common->is_match($options['day_of_week_timesheet_reminders'], 1, ' selected')}{$common->is_match($options['day_of_week_timesheet_reminders'], '', ' selected')}>Monday</option>
			<option value='2'{$common->is_match($options['day_of_week_timesheet_reminders'], 2, ' selected')}>Tuesday</option>
			<option value='3'{$common->is_match($options['day_of_week_timesheet_reminders'], 3, ' selected')}>Wednesday</option>
			<option value='4'{$common->is_match($options['day_of_week_timesheet_reminders'], 4, ' selected')}>Thursday</option>
			<option value='5'{$common->is_match($options['day_of_week_timesheet_reminders'], 5, ' selected')}>Friday</option>
			<option value='6'{$common->is_match($options['day_of_week_timesheet_reminders'], 6, ' selected')}>Saturday</option>
		</select>";
		if (!isset($options['day_of_week_timesheet_reminders'])) {
			echo '<p style="color: red;">   * Setting Not Set</p>';
		}
	}

	function week_starts() {
	$options = get_option('time_sheets');
		$common = new time_sheets_common();

		echo "<select name='time_sheets[week_starts]'>
			<option value='0'{$common->is_match($options['week_starts'], 0, ' selected')}>Sunday</option>
			<option value='1'{$common->is_match($options['week_starts'], 1, ' selected')}{$common->is_match($options['week_starts'], '', ' selected')}>Monday</option>
			<option value='2'{$common->is_match($options['week_starts'], 2, ' selected')}>Tuesday</option>
			<option value='3'{$common->is_match($options['week_starts'], 3, ' selected')}>Wednesday</option>
			<option value='4'{$common->is_match($options['week_starts'], 4, ' selected')}>Thursday</option>
			<option value='5'{$common->is_match($options['week_starts'], 5, ' selected')}>Friday</option>
			<option value='6'{$common->is_match($options['week_starts'], 6, ' selected')}>Saturday</option>
		</select>";
		
		if (!isset($options['week_starts'])) {
			echo '<p style="color: red;">   * Setting Not Set</p>';
		}
	}

	function project_trigger_percent() {
		$options = get_option('time_sheets');
		$project_trigger_percent = $options['project_trigger_percent'];
		if (!$project_trigger_percent) {
			$project_trigger_percent = 100;
		}
		echo "<input type='text' name='time_sheets[project_trigger_percent]' value='{$project_trigger_percent}' size='3' maxlength='3'>";
		
		if (!isset($options['project_trigger_percent'])) {
			echo '<p style="color: red;">   * Setting Not Set</p>';
		}
	}
	
	function email_address_on_project_over() {
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		$email_address_on_project_over = $common->esc_textarea($options['email_address_on_project_over']);
		
		echo "<input type='text' name='time_sheets[email_address_on_project_over]' value='{$email_address_on_project_over}'> Leave blank to disable. Seperate multiple addresses with a comma.";
	}
	
	function hide_notes() {
		$options = get_option('time_sheets');
		$hide_notes = isset($options['hide_notes']) ? " checked": "";
				
		echo "<input type='checkbox' name='time_sheets[hide_notes]' value='1'{$hide_notes}>";
	}
	
	function default_auto_close() {
		$options = get_option('time_sheets');
		$default_auto_close = isset($options['default_auto_close']) ? " checked" : "";
				
		echo "<input type='checkbox' name='time_sheets[default_auto_close]' value='1'{$default_auto_close}>";
	}
	
	function allow_recurring_timesheets() {
		$options = get_option('time_sheets');
		$allow_recurring_timesheets = isset($options['allow_recurring_timesheets']) ? " checked" : "";
				
		echo "<input type='checkbox' name='time_sheets[allow_recurring_timesheets]' value='1'{$allow_recurring_timesheets}>";
		
	}
	
	function register_settings() {
		if (isset($_GET["tab"])) {
			$tab = $_GET["tab"];
		} else {
			$tab = '';
		}
		$active_tab = $this->get_active_tab($tab);

		register_setting('time_sheets', 'time_sheets', array(&$this, 'settings_validate'));

		if ($active_tab=="automation") {
				add_settings_section('time_sheets_automation', __('Automation', ''), array(&$this, 'settings_header_automation'), 'time_sheets');
				add_settings_field('cron_user', __('User For Automatic Entries:', ''), array(&$this, 'cron_user'), 'time_sheets', 'time_sheets_automation');
				add_settings_field('project_trigger_percent', __('Percent of project completion to show alerts:', ''), array(&$this, 'project_trigger_percent'), 'time_sheets', 'time_sheets_automation');
				add_settings_field('email_address_on_project_over', __('Email address to email when a project is over on hours:', ''), array(&$this, 'email_address_on_project_over'), 'time_sheets', 'time_sheets_automation');
				add_settings_field('default_auto_close', __('Default new projects to auto-close:', ''), array(&$this, 'default_auto_close'), 'time_sheets', 'time_sheets_automation');
				add_settings_field('allow_recurring_timesheets', __('Allow recurring time sheets to be saved:', ''), array(&$this, 'allow_recurring_timesheets'), 'time_sheets', 'time_sheets_automation');

				//add_settings_field('image_upload_path', __('Folder within the uploads path to put recipt images:', ''), array(&$this, 'image_upload_path'), 'time_sheets', 'time_sheets_automation');
			
		} elseif ($active_tab=="display_settings") {
			
				add_settings_section('time_sheets_display', __('Display Settings', ''), array(&$this, 'settings_header_display'), 'time_sheets');
				add_settings_field('display_notes_toggle', __('Display daily notes fields to:', ''), array(&$this, 'display_notes_toggle'), 'time_sheets', 'time_sheets_display');
				add_settings_field('display_notes_list', __('Notes To/Except list (based on prior setting):', ''), array(&$this, 'display_notes_list'), 'time_sheets', 'time_sheets_display');
				add_settings_field('display_expenses_toggle', __('Display expenses fields to:', ''), array(&$this, 'display_expenses_toggle'), 'time_sheets', 'time_sheets_display');
				add_settings_field('display_expenses_list', __('Expenses To/Except list (based on prior setting):', ''), array(&$this, 'display_expenses_list'), 'time_sheets', 'time_sheets_display');
				add_settings_field('menu_location', __('Time Sheets below:', ''), array(&$this, 'menu_location'), 'time_sheets', 'time_sheets_display');
				add_settings_field('users_override_location', __('Users can override menu location:', ''), array(&$this, 'users_override_location'), 'time_sheets', 'time_sheets_display');
				add_settings_field('show_header_queues', __('Show Queues in Admin Header:', ''), array(&$this, 'show_header_queues'), 'time_sheets', 'time_sheets_display');
				add_settings_field('show_header_open_invoices', __('Show Users Open Timesheets in Admin Header:', ''), array(&$this, 'show_header_open_invoices'), 'time_sheets', 'time_sheets_display');
				add_settings_field('rel_url_to_timesheet', __('Relative URL to Timesheet Entry Page:', ''), array(&$this, 'rel_url_to_timesheet'), 'time_sheets', 'time_sheets_display');
				add_settings_field('override_date_format', __('Override Site Wide Date Format:', ''), array(&$this, 'override_date_format'), 'time_sheets', 'time_sheets_display');
				add_settings_field('new_date_format', __('New Date Format:', ''), array(&$this, 'new_date_format'), 'time_sheets', 'time_sheets_display');
				add_settings_field('user_specific_date_format', __('Allow users to set their own date format:', ''), array(&$this, 'user_specific_date_format'), 'time_sheets', 'time_sheets_display');
				add_settings_field('hide_client_project', __('Hide the Client and Project fields if only one option:', ''), array(&$this, 'hide_client_project'), 'time_sheets', 'time_sheets_display');
				add_settings_field('remove_embargo', __('Remove embargo options:', ''), array(&$this, 'remove_embargo'), 'time_sheets', 'time_sheets_display');
				add_settings_field('week_starts', __('Week starts:', ''), array(&$this, 'week_starts'), 'time_sheets', 'time_sheets_display');
				add_settings_field('queue_order', __('Payroll Queue Processing:', ''), array(&$this, 'queue_order'), 'time_sheets', 'time_sheets_display');
				add_settings_field('sales_override', __('Sales Person Priorty:', ''), array(&$this, 'sales_override'), 'time_sheets', 'time_sheets_display');
				add_settings_field('hide_notes', __('Hide the notes field for completed time sheets:', ''), array(&$this, 'hide_notes'), 'time_sheets', 'time_sheets_display');
				add_settings_field('currency_char', __('Currency Symbol to Show:', ''), array(&$this, 'currency_char'), 'time_sheets', 'time_sheets_display');
				add_settings_field('distance_metric', __('Distance Metric:', ''), array(&$this, 'distance_metric'), 'time_sheets', 'time_sheets_display');
				add_settings_field('error_if_date_already_used', __('Disallow Time Sheets on Matching Project/Week/User:', ''), array(&$this, 'error_if_date_already_used'), 'time_sheets', 'time_sheets_display');
				add_settings_field('enable_non_billable_time', __('Display non-billable time fields on timesheet:', ''), array(&$this, 'enable_non_billable_time'), 'time_sheets', 'time_sheets_display');

				add_settings_field('enable_requesting_access', __('Allow users to request access to clients they can not see:', ''), array(&$this, 'enable_requesting_access'), 'time_sheets', 'time_sheets_display');
				add_settings_field('enable_requesting_access_self_approve', __('Auto approve access requests:', ''), array(&$this, 'enable_requesting_access_self_approve'), 'time_sheets', 'time_sheets_display');
				add_settings_field('require_unique_project_names', __('Require Unique Project Names:', ''), array(&$this, 'require_unique_project_names'), 'time_sheets', 'time_sheets_display');
				add_settings_field('disable_cacheing', __('Disable cacheing for database queries:', ''), array(&$this, 'disable_cacheing'), 'time_sheets', 'time_sheets_display');
			
			
			
			} elseif ($active_tab=="retainer") {
			
				add_settings_section('time_sheets_retainer', __('Retainer Settings', ''), array(&$this, 'blank'), 'time_sheets');
				add_settings_field('update_retainer_hours', __('Update allowed hours for retainers monthly:', ''), array(&$this, 'update_retainer_hours'), 'time_sheets', 'time_sheets_retainer');
			 	add_settings_field('retainers_per_client', __('Allow multiple retainers per client:', ''), array(&$this, 'retainers_per_client'), 'time_sheets', 'time_sheets_retainer');
				add_settings_field('allow_money_based_retainers', __('Allow retainers which are a bucket of funds based, not hourly based:', ''), array(&$this, 'allow_money_based_retainers'), 'time_sheets', 'time_sheets_retainer');
			
			} elseif ($active_tab=="basics") {
				add_settings_section('time_sheets_basics', __('Email Settings', ''), array(&$this, 'settings_header_email'), 'time_sheets');
				add_settings_field('enable_email', __('Enable Email:', ''), array(&$this, 'enable_email'), 'time_sheets', 'time_sheets_basics');
				add_settings_field('email_late_timesheets', __('Email users when timesheets are overdue:', ''), array(&$this, 'email_late_timesheets'), 'time_sheets', 'time_sheets_basics');
				add_settings_field('email_retainer_due', __('Email users that retainer timesheets are due:', ''), array(&$this, 'email_retainer_due'), 'time_sheets', 'time_sheets_basics');
				add_settings_field('email_from', __('From Email Address:', ''), array(&$this, 'email_from'), 'time_sheets', 'time_sheets_basics');
				add_settings_field('email_name', __('From Email Name:', ''), array(&$this, 'email_name'), 'time_sheets', 'time_sheets_basics');
				add_settings_field('show_email_notice', __('Show Notice On Email Send:', ''), array(&$this, 'show_email_notice'), 'time_sheets', 'time_sheets_basics');
				add_settings_field('hide_dcac_ad', __('Hide Application Ad:', ''), array(&$this, 'hide_dcac_ad'), 'time_sheets', 'time_sheets_basics');
				add_settings_field('day_of_week_timesheet_reminders', __('Day of week for Time Sheet Reminders:', ''), array(&$this, 'day_of_week_timesheet_reminders'), 'time_sheets', 'time_sheets_basics');
				
		} elseif ($active_tab=="payroll") {

				add_settings_section('time_sheets_process_payroll', __('Payroll Triggers', ''), array(&$this, 'process_payroll'), 'time_sheets');
				add_settings_field('mileage', __('Mileage', ''), array(&$this, 'mileage'), 'time_sheets', 'time_sheets_process_payroll');
				add_settings_field('per_diem', __('Per Diem', ''), array(&$this, 'per_diem'), 'time_sheets', 'time_sheets_process_payroll');
				add_settings_field('flight_cost', __('Flight/Train Cost', ''), array(&$this, 'flight_cost'), 'time_sheets', 'time_sheets_process_payroll');
				add_settings_field('hotel', __('Hotel Charges', ''), array(&$this, 'hotel'), 'time_sheets', 'time_sheets_process_payroll');
				add_settings_field('rental_car', __('Rental Car', ''), array(&$this, 'rental_car'), 'time_sheets', 'time_sheets_process_payroll');
				add_settings_field('taxi', __('Taxi/Rideshare', ''), array(&$this, 'taxi'), 'time_sheets', 'time_sheets_process_payroll');
				add_settings_field('tolls', __('Tolls', ''), array(&$this, 'tolls'), 'time_sheets', 'time_sheets_process_payroll');
				add_settings_field('other_expenses', __('Other Expenses', ''), array(&$this, 'other_expenses'), 'time_sheets', 'time_sheets_process_payroll');
				add_settings_section('time_sheets_payroll_options', __('Payroll Options', ''), array(&$this, 'blank'), 'time_sheets');
				add_settings_field('allow_overtime', __('Show Overtime Fields', ''), array(&$this, 'allow_overtime'), 'time_sheets', 'time_sheets_payroll_options');
				add_settings_field('ot_multiplier', __('Overtime Multiplier', ''), array(&$this, 'ot_multiplier'), 'time_sheets', 'time_sheets_payroll_options');
				add_settings_field('show_overtime_when_empty', __('Show Overtime When Value of 0:', ''), array(&$this, 'show_overtime_when_empty'), 'time_sheets', 'time_sheets_payroll_options');
				add_settings_field('show_non_billable_when_empty', __('Display non-billable When Value of 0:', ''), array(&$this, 'show_non_billable_when_empty'), 'time_sheets', 'time_sheets_payroll_options');
				add_settings_field('allow_users_to_reject', __('Allow Users to Reject Timesheets:', ''), array(&$this, 'allow_users_to_reject'), 'time_sheets',  'time_sheets_payroll_options');
			}
	}
	
	function disable_cacheing() {
		$disable_cacheing = '';
		$options = get_option('time_sheets');
			if (isset($options['disable_cacheing'])) {
			$disable_cacheing = ' checked';
		}
		echo "<input type='checkbox' id='disable_cacheing' name='time_sheets[disable_cacheing]' value=' checked' {$disable_cacheing} >";
	}
	
	function require_unique_project_names() {
		$require_unique_project_names = '';
		$options = get_option('time_sheets');
			if (isset($options['require_unique_project_names'])) {
			$require_unique_project_names = ' checked';
		}
		echo "<input type='checkbox' id='require_unique_project_names' name='time_sheets[require_unique_project_names]' value=' checked' {$require_unique_project_names} >";
	}
	
	function allow_money_based_retainers() {
		$allow_money_based_retainers = '';
		$options = get_option('time_sheets');
		if (isset($options['allow_money_based_retainers'])) {
			$allow_money_based_retainers = ' checked';
		}
		echo "<input type='checkbox' id='allow_money_based_retainers' name='time_sheets[allow_money_based_retainers]' value=' checked' {$allow_money_based_retainers} >";
	}

	function enable_requesting_access() {
		$enable_requesting_access = '';
		$options = get_option('time_sheets');
		if (isset($options['enable_requesting_access'])) {
			$enable_requesting_access = ' checked';
		}
		echo "<input type='checkbox' id='enable_requesting_access' name='time_sheets[enable_requesting_access]' value=' checked' {$enable_requesting_access} onclick='requesting_access_js()'>";

	}

	function enable_requesting_access_self_approve() {
		$enable_requesting_access_self_approve = '';
		$options = get_option('time_sheets');
		if (isset($options['enable_requesting_access_self_approve'])) {
			$enable_requesting_access_self_approve = ' checked';
		}
		echo "<input type='checkbox' id='enable_requesting_access_self_approve' name='time_sheets[enable_requesting_access_self_approve]' value=' checked' {$enable_requesting_access_self_approve}>";

	}

	
	function show_non_billable_when_empty() {
		$show_non_billable_when_empty = '';
		$options = get_option('time_sheets');
		if (isset($options['show_non_billable_when_empty'])) {
			$show_non_billable_when_empty = ' checked';
		}
		echo "<input type='checkbox' id='show_non_billable_when_empty' name='time_sheets[show_non_billable_when_empty]' value=' checked' {$show_non_billable_when_empty}>";
	}
	function enable_non_billable_time() {
		$enable_non_billable_time = '';
		$options = get_option('time_sheets');
		if (isset($options['enable_non_billable_time'])) {
			$enable_non_billable_time = ' checked';
		}
		echo "<input type='checkbox' id='enable_non_billable_time' name='time_sheets[enable_non_billable_time]' value=' checked' {$enable_non_billable_time}>";	
	}
	
	function retainers_per_client() {
		$retainers_per_client = '';
		$options = get_option('time_sheets');
		if (isset($options['retainers_per_client'])) {
			$retainers_per_client = ' checked';
		}
		echo "<input type='checkbox' id='error_if_date_already_used' name='time_sheets[retainers_per_client]' value=' checked' {$retainers_per_client}>";
	}
	
	function error_if_date_already_used() {
		
		$error_if_date_already_used = '';
		$options = get_option('time_sheets');
		if (isset($options['error_if_date_already_used'])) {
			$error_if_date_already_used = ' checked';
		}
		echo "<input type='checkbox' id='error_if_date_already_used' name='time_sheets[error_if_date_already_used]' value=' checked' {$error_if_date_already_used}>";
	}
	
	function allow_users_to_reject() {
		$allow_users_to_reject = '';
		$options = get_option('time_sheets');
		if (isset($options['allow_users_to_reject'])) {
			$allow_users_to_reject = ' checked';
		}
		echo "<input type='checkbox' id='allow_users_to_reject' name='time_sheets[allow_users_to_reject]' value=' checked' {$allow_users_to_reject}>";
	}
	
	function show_overtime_when_empty() {
		$show_overtime_when_empty = '';
		$options = get_option('time_sheets');
		if (isset($options['show_overtime_when_empty'])) {
			$show_overtime_when_empty = ' checked';
		}
		echo "<input type='checkbox' id='show_overtime_when_empty' name='time_sheets[show_overtime_when_empty]' value=' checked' {$show_overtime_when_empty}>";

	}
	
	function image_upload_path() {
		$options = get_option('time_sheets');
		
		echo "<input type='text' name='time_sheets[image_upload_path]' value='{$options['image_upload_path']}' size='5'>";
	}
	
	function distance_metric() {
		$options = get_option('time_sheets');
		$distance_metric = $options['distance_metric'];
		
		$common = new time_sheets_common();
		
		echo "<select name='time_sheets[distance_metric]'>
			<option{$common->is_match($distance_metric, 'Miles', ' selected', 0)}>Miles</option>
			<option{$common->is_match($distance_metric, 'Kilometers', ' selected', 0)}>Kilometers</option>
			</select>";
		
		if (!isset($options['distance_metric'])) {
			echo '<p style="color: red;">   * Setting Not Set</p>';
		}
	}
	
	function currency_char() {
		$options = get_option('time_sheets');
		$currency_char = $options['currency_char'];
		if ($currency_char == "") {
			$currency_char = "$";
		}
		echo "<input type='text' name='time_sheets[currency_char]' value='{$currency_char}' size='5'>";
		
		if (!isset($options['currency_char'])) {
			echo '<p style="color: red;">   * Setting Not Set</p>';
		}
	}
	
	function ot_multiplier(){
		$options = get_option('time_sheets');
		$ot_multiplier = floatval($options['ot_multiplier']);
		if ($ot_multiplier==0) {
			$ot_multiplier = 1;
		}
		echo "<input type='text' name='time_sheets[ot_multiplier]' value='{$ot_multiplier}' size='5'><br>
		Multiplier to be applies to overtime hours worked when calculating hours used against a project. This does not effect the hours entered by the worked.  Typical values are 1.5.";
	}
	
	function allow_overtime() {
		$options = get_option('time_sheets');
		$allow_overtime = isset($options['allow_overtime'])? " checked" : "";
		echo "<input type='checkbox' id='allow_overtime' name='time_sheets[allow_overtime]' value=' checked' {$allow_overtime} onclick='check_ot()'>";
	}
	
	function sales_override() {
		$common = new time_sheets_common();
		$options = get_option('time_sheets');
		echo "<select name='time_sheets[sales_override]'>
				<option value='customer'{$common->is_match($options['sales_override'], 'customer', ' selected', 0)}>Customer is priority</option>
				<option value='project' {$common->is_match($options['sales_override'], 'project', ' selected', 0)}>Project is priority</option>
			</select>";
		
		if (!isset($options['sales_override'])) {
			echo '<p style="color: red;">   * Setting Not Set</p>';
		}
	}
	
	function queue_order() {
		$common = new time_sheets_common();
		$options = get_option('time_sheets');
		echo "<select name='time_sheets[queue_order]'>
			<option value='after'{$common->is_match($options['queue_order'], 'after', ' selected')}{$common->is_match($options['queue_order'], '', ' selected')}>After Invoicing</option>
			<option value='parallel'{$common->is_match($options['queue_order'], 'parallel', ' selected')}>In Parallel to Invoicing</option>
		</select>";
		
		if (!isset($options['queue_order'])) {
			echo '<p style="color: red;">   * Setting Not Set</p>';
		}
	}

	function remove_embargo() {
		$options = get_option('time_sheets');
		$remove_embargo = isset($options['remove_embargo']) ? " selected" : "";
		echo "<input type='checkbox' name='time_sheets[remove_embargo]' value=' checked'{$remove_embargo}> This will not unembargo any time sheets which are embargoed. That needs to be done before this setting is enabled.";
	}

	function hide_client_project() {
		$options = get_option('time_sheets');
		$hide_project = isset($options['hide_client_project']) ? " selected" : "";
		echo "<input type='checkbox' name='time_sheets[hide_client_project]' value=' checked'{$hide_project}> If checked and the user only has access to a single client and a single project then the client and project will be hidden from view.";
	}

	function override_date_format() {
		$options = get_option('time_sheets');
		$common = new time_sheets_common();

		if ($options['override_date_format'] <> 'system_defined' && $options['override_date_format'] <> 'admin_defined') {
			$options['override_date_format']= 'system_defined';
		}

		echo "<input type='radio' name='time_sheets[override_date_format]' onClick='toggleSetting()' value='system_defined' {$common->is_match($options['override_date_format'], 'system_defined', ' checked')}> Use the system wide settings<br>";
		echo "<input type='radio' name='time_sheets[override_date_format]' onClick='toggleSetting()' value='admin_defined' {$common->is_match($options['override_date_format'], 'admin_defined', ' checked')}> Use the setting defined below";

		if (($options['override_date_format'] <> 'system_defined' && $options['override_date_format'] <> 'admin_defined')) {
			echo '<p style="color: red;">   * Setting Not Set</p>';
		}
		
		echo "";
	}

	function new_date_format() {
		$options = get_option('time_sheets');
		$value = isset($options['new_date_format']) ? $options['new_date_format'] : "";
		echo "<input type='text' name='time_sheets[new_date_format]'value='{$value}'> <a href='https://wordpress.org/support/article/formatting-date-and-time/'>Documentation on Date Formatting</a><BR>Use of a non-ISO date format can cause search issues when searching for old time sheets.";
	}

	function user_specific_date_format() {
		$options = get_option('time_sheets');
		$value = isset($options['user_specific_date_format']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[user_specific_date_format]' value='checked' {$value}>";
	}

	function rel_url_to_timesheet() {
		$options = get_option('time_sheets');
		$value = isset($options['rel_url_to_timesheet']) ? $options['rel_url_to_timesheet'] : "";
		echo "<input type='text' name='time_sheets[rel_url_to_timesheet]' value='{$value}'> (This setting is only needed when using shortcut codes for time sheet entry and time sheet search pages.  To use create a page with this value as the slug and [timesheet_entry] as the value.)";
	}

	function show_header_open_invoices() {
		$options = get_option('time_sheets');
		$value = isset($options['show_header_open_invoices']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[show_header_open_invoices]' value='checked' {$value}>";
	}

	function update_retainer_hours() {
		$options = get_option('time_sheets');
		$value = isset($options['update_retainer_hours']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[update_retainer_hours]' value='checked' {$value}>";
	}

	function show_header_queues() {
		$options = get_option('time_sheets');
		$value = isset($options['show_header_queues']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[show_header_queues]' value='checked' {$value}> (Queues are only shown when there are items in the queue to be processed.)";
	}

	function menu_location() {
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		echo "<select name='time_sheets[menu_location]'>
			<option value='1'{$common->is_match('1', $options['menu_location'], ' selected')}>Top Menu</option>
			<option value='3'{$common->is_match('3', $options['menu_location'], ' selected')}>Dashboard</option>
			<option value='6'{$common->is_match('6', $options['menu_location'], ' selected')}>Posts</option>
			<option value='11'{$common->is_match('11', $options['menu_location'], ' selected')}>Media</option>
			<option value='16'{$common->is_match('16', $options['menu_location'], ' selected')}>Links</option>
			<option value='21'{$common->is_match('21', $options['menu_location'], ' selected')}>Pages</option>
			<option value='26'{$common->is_match('26', $options['menu_location'], ' selected')}>Comments</option>
			<option value='61'{$common->is_match('61', $options['menu_location'], ' selected')}>Appearance</option>
			<option value='66'{$common->is_match('66', $options['menu_location'], ' selected')}>Plugins</option>
			<option value='71'{$common->is_match('71', $options['menu_location'], ' selected')}>Users</option>
			<option value='76'{$common->is_match('76', $options['menu_location'], ' selected')}>Tools</option>
			<option value='81'{$common->is_match('81', $options['menu_location'], ' selected')}>Settings</option>
			<option value='10000'{$common->is_match('10000', $options['menu_location'], ' selected')}>Bottom of List</option>
			<option value=''{$common->is_match('', $options['menu_location'], ' selected')}>Default Location - Where ever WordPress wants</option>
		</select>";
	}

	function users_override_location() {
		$options = get_option('time_sheets');
		$value = isset($options['users_override_location']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[users_override_location]' value='checked' {$value}>";
	}

	function email_late_timesheets() {
		$options = get_option('time_sheets');
		$value = isset($options['email_late_timesheets']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[email_late_timesheets]' value='checked' {$value}>";

	}

	function email_retainer_due() {
		$options = get_option('time_sheets');
		$value = isset($options['email_retainer_due']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[email_retainer_due]' value='checked' {$value}>";
	}

	function flight_cost() {
		$options = get_option('time_sheets');
		$value = isset($options['flight_cost']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[flight_cost]' value='checked' {$value}>";
	}

	function per_diem() {
		$options = get_option('time_sheets');
		$value = isset($options['per_diem']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[per_diem]' value='checked' {$value}>";
	}

	function hotel() {
		$options = get_option('time_sheets');
		$value = isset($options['hotel']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[hotel]' value='checked' {$value}>";
	}

	function rental_car() {
		$options = get_option('time_sheets');
		$value = isset($options['rental_car']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[rental_car]' value='checked' {$value}>";
	}

	function tolls() {
		$options = get_option('time_sheets');
		$value = isset($options['tolls']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[tolls]' value='checked' {$value}>";
	}
	
	function taxi() {
		$options = get_option('time_sheets');
		$value = isset($options['taxi']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[taxi]' value='checked' {$value}>";
	}

	function other_expenses() {
		$options = get_option('time_sheets');
		$value = isset($options['other_expenses']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[other_expenses]' value='checked' {$value}>";
	}

	function mileage() {
		$options = get_option('time_sheets');
		$value = isset($options['mileage']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[mileage]' value='checked' {$value}>";
	}

	function process_payroll() {
		echo "Select the Expense categories which should trigger timesheets being sent to the Payrol workflow.";
	}


	function get_holidays() {
		$options = get_option('time_sheets');
		echo "<input type='checkbox' name='time_sheets[get_holidays]' value='checked' {$options['get_holidays']}>Allows the application to call out to the Denny Cherry & Associates Consulting web servers to request a list of holidays in order to mark days in the timesheet as a holiday.";
	}

	function license_key() {
		$options = get_option('time_sheets');
		echo "<input type='text' name='time_sheets[license_key]' value='{$options['license_key']}'>";

		if (!$options['license_key']) {
			echo "<div if='message' class='error'><p>No license key was provided.  Application will be fully functional, however the number of clients and projects will be limited to two clients and 5 projects.</p></div>";
		}
	}

	function cron_user() {
		global $wpdb;
		$db = new time_sheets_db();
		$options = get_option('time_sheets');
		$sql = "select * 
		from {$wpdb->users} u 
		join {$wpdb->prefix}usermeta um on u.id = um.user_id 
		where um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}'
		order by u.display_name";

		$users = $db->get_results($sql);

		echo "<select name='time_sheets[cron_user]'>";
		foreach ($users as $user) {
			echo "<option value='{$user->ID}'";
			if ($user->ID==$options['cron_user']) {
				echo " selected";
			}
			echo ">{$user->display_name} ({$user->user_login})</option>";
		}
		echo "</select>";
	}

	function show_email_notice() {
		$options = get_option('time_sheets');
		$value = isset($options['show_email_notice']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[show_email_notice]' value='checked' {$value}> (Mostly used for troubleshooting.)";
	}

	function hide_dcac_ad() {
		$options = get_option('time_sheets');
		$value = isset($options['hide_dcac_ad']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[hide_dcac_ad]' value='checked' {$value}>";
	}

	function email_name() {
		$options = get_option('time_sheets');
		$value = isset($options['email_name']) ? $options['email_name'] : "";
		echo "<input type='text' name='time_sheets[email_name]' value='{$value}'>";
	}

	function email_from() {
		$options = get_option('time_sheets');
		$value = isset($options['email_from']) ? $options['email_from'] : "";
		echo "<input type='text' name='time_sheets[email_from]' value='{$value}'><br>
		If you are having problems with this setting <a href='http://www.dcac.co/applications/wordpress-plugins/time-sheets/smtp-settings-wont-stay'>refer to this page</a>.";
	}

	function enable_email() {
		$options = get_option('time_sheets');
		$value = isset($options['enable_email']) ? " checked" : "";
		echo "<input type='checkbox' name='time_sheets[enable_email]' value='checked' {$value}>";
	}

	function manage_approvers() {
		if (isset($_GET['action']) && $_GET['action']=='add') {
			$this->add_person('approvers');
		}

		if (isset($_GET['action']) && $_GET['action']=='delete') {
			$this->delete_person('approvers');
		}
		$this->show_approvers();
	}

	function add_person($table) {
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();

		$sql = "insert into {$wpdb->prefix}timesheet_{$table} (user_id) values (%d)";
		$params = array($_GET['user_id']);

		$db->query($sql, $params);
		
		$sql = "insert into {$wpdb->prefix}timesheet_{$table}_archive
						select $user->ID, now(), 'INSERTED', user_id";
		if ($table == "approvers") {
			$sql = $sql . ", backup_user_id, backup_expires_on";
		}
		
		$sql = $sql . " FROM {$wpdb->prefix}timesheet_{$table}
						WHERE user_id = %d";
		$parms = array($_GET['user_id']);
		$db->query($sql, $parms);
		
	}

	function delete_person($table) {
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();
		
		$sql = "insert into {$wpdb->prefix}timesheet_{$table}_archive
						select $user->ID, now(), 'DELETED', user_id";
		if ($table == "approvers") {
			$sql = $sql . ", backup_user_id, backup_expires_on";
		}
		
		$sql = $sql . " 
						FROM {$wpdb->prefix}timesheet_{$table}
						WHERE user_id = %d";
		$parms = array($_GET['user_id']);
		$db->query($sql, $parms);

		$sql = "delete from {$wpdb->prefix}timesheet_{$table} where user_id = %d";
		$params = array($_GET['user_id']);

		$db->query($sql, $params);
	}

	function show_approvers() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$folder = plugins_url();

		$sql = "select u.user_login, ta.user_id a, u.id, u.display_name
		from {$wpdb->users} u
		left outer join {$wpdb->prefix}timesheet_approvers ta ON u.ID = ta.user_id
		join {$wpdb->prefix}usermeta um on u.id = um.user_id 
		where um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}'
		order by u.display_name";

		$users = $db->get_results($sql);
		echo "<br><table border='1' cellpadding='0' cellspacing='0'><tr><td><b>User<b></td><td><b>User Name</b></td><td><b>Current State</b></td><td><b>Add Permission</b></td><td><b>Remove Permission</b></td></tr>";
		foreach ($users as $user) {
			echo "<tr><td>{$user->display_name}</td><td>{$user->user_login}</td><td>";
			if ($user->a) {
				echo "Has Access";
			} else {
				echo "No Access";
			}

			echo "</td><td align='center'>";
			if (!$user->a) {
				echo "<a href='admin.php?page=time_sheets_manage_approvers&action=add&user_id={$user->id}'><img src='{$folder}/time-sheets/check.png' height='13' width='13'></a>";
			}
			echo "</td><td align='center'>";
			if ($user->a) {
				echo "<a href='admin.php?page=time_sheets_manage_approvers&action=delete&user_id={$user->id}'><img src='{$folder}/time-sheets/x.png' height='13' width='13'></a>";
			}
			echo "</td></tr>";
		}
		echo "</table>";
	}

	function show_invoicers() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$folder = plugins_url();

		$sql = "select u.user_login, ta.user_id a, u.id, u.display_name
		from {$wpdb->users} u
		left outer join {$wpdb->prefix}timesheet_invoicers ta ON u.ID = ta.user_id
		join {$wpdb->prefix}usermeta um on u.id = um.user_id 
		where um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}'
		order by u.display_name";

		$users = $db->get_results($sql);
		echo "<br><table border='1' cellpadding='0' cellspacing='0'><tr><td><b>User</b></td><td><b>User Name</b></td><td><b>Current State</b></td><td><b>Add Permission</b></td><td><b>Remove Permission</b></td></tr>";
		foreach ($users as $user) {
			echo "<tr><td>{$user->display_name}</td><td>{$user->user_login}</td><td>";
			if ($user->a) {
				echo "Has Access";
			} else {
				echo "No Access";
			}

			echo "</td><td align='center'>";
			if (!$user->a) {
				echo "<a href='admin.php?page=time_sheets_manage_invoicers&action=add&user_id={$user->id}'><img src='{$folder}/time-sheets/check.png' height='13' width='13'></a>";
			}
			echo "</td><td align='center'>";
			if ($user->a) {
				echo "<a href='admin.php?page=time_sheets_manage_invoicers&action=delete&user_id={$user->id}'><img src='{$folder}/time-sheets/x.png' height='13' width='13'></a>";
			}
			echo "</td></tr>";
		}
		echo "</table>";
	}

	function show_payrollers() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$folder = plugins_url();

		$sql = "select u.user_login, ta.user_id a, u.id, u.display_name
		from {$wpdb->users} u
		left outer join {$wpdb->prefix}timesheet_payrollers ta ON u.ID = ta.user_id
		join {$wpdb->prefix}usermeta um on u.id = um.user_id 
		where um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}'
		order by u.display_name";

		$users = $db->get_results($sql);
		echo "<br><table border='1' cellpadding='0' cellspacing='0'><tr><td><b>User</b></td><td><b>User Name</b></td><td><b>Current State</b></td><td><b>Add Permission</b></td><td><b>Remove Permission</b></td></tr>";
		foreach ($users as $user) {
			echo "<tr><td>{$user->display_name}</td><td>{$user->user_login}</td><td>";
			if ($user->a) {
				echo "Has Access";
			} else {
				echo "No Access";
			}

			echo "</td><td align='center'>";
			if (!$user->a) {
				echo "<a href='admin.php?page=time_sheets_manage_payroll&action=add&user_id={$user->id}'><img src='{$folder}/time-sheets/check.png' height='13' width='13'></a>";
			}
			echo "</td><td align='center'>";
			if ($user->a) {
				echo "<a href='admin.php?page=time_sheets_manage_payroll&action=delete&user_id={$user->id}'><img src='{$folder}/time-sheets/x.png' height='13' width='13'></a>";
			}
			echo "</td></tr>";
		}
		echo "</table>";
	}

	function manage_payrollers() {
		if (isset($_GET['action']) && $_GET['action']=='add') {
			$this->add_person('payrollers');
		}

		if (isset($_GET['action']) && $_GET['action']=='delete') {
			$this->delete_person('payrollers');
		}
		$this->show_payrollers();
	}

	function manage_invoicers() {
		if (isset($_GET['action']) && $_GET['action']=='add') {
			$this->add_person('invoicers');
		}

		if (isset($_GET['action']) && $_GET['action']=='delete') {
			$this->delete_person('invoicers');
		}
		$this->show_invoicers();
	}
}