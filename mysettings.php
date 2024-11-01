<?php
class time_sheets_my_settings {
	function main() {
		global $wpdb;
		$entry = new time_sheets_entry();
		$main = new time_sheets_main();
		$common = new time_sheets_common();
		$db = new time_sheets_db();

		$client_manager = new time_sheets_client_managers();
		$queue_approval = new time_sheets_queue_approval();
		$queue_invoice = new time_sheets_queue_invoice();
		$queue_payroll = new time_sheets_queue_payroll();

		$options = get_option('time_sheets');
		$user = wp_get_current_user();
		$user_id = $user->ID;

		if (isset($_POST['submit'])) {
			$this->save_settings();
		}

		$DefaultDate = get_user_option('timesheet_defaultdate', $user_id);

		if (!$DefaultDate) {
			$DefaultDate='Monday Last Week';
		}

		echo "<form name='mysettings' method='POST'>
		<table><tr>
			<td>Default Date:</td>
			<td><select name='default_date'>
				<option";
			if ($DefaultDate == 'Last Monday') {
				echo " selected";
			}
			echo ">Last Monday</option>
				<option";
			if ($DefaultDate == 'Monday Last Week') {
				echo " selected";
			}
			echo ">Monday Last Week</option>
			<option";
			if ($DefaultDate == 'Monday This Week') {
				echo " selected";
			}
			echo ">Monday This Week</option>
			</select></td></tr>";

		if ($queue_approval->employee_approver_check('false')==1) {
			$common->show_datestuff();

			echo "<tr><td>Transfer Approval Team to:<br>
					(You can not undo this)</td>";

			$sql = "select ID, display_name, user_login
				from {$wpdb->users}
				where ID <> {$user_id}
				order by display_name";

			$users = $db->get_results($sql);

			echo "<td><select name='transfer_to_user_id' onChange='enableTransferCheckBox()'>";
			echo "<option value=''>--Do Not Transfer</option>";
			foreach ($users as $user) {
				echo "<option value='{$user->ID}'>{$user->display_name} ({$user->user_login})</option>";
			}
			echo "</select></td></tr>
			<tr><td colspan='2'><input type='checkbox' name='approve_change' value='yes' disabled> I conform that I want to move my team to the above approver.</td></tr>";

			$sql = "select * from {$wpdb->prefix}timesheet_approvers where user_id = {$user_id}";

			$approver_info = $db->get_row($sql);

			echo "<tr><td>Backup Approver:</td>
			<td><select name='backup_user_id' onChange='enableApproverDate()'>
				<option {$common->is_match('', $approver_info->backup_user_id, ' selected')}>--No Backup Approver</option>";
				foreach ($users as $user) {
					echo "<option value='{$user->ID}'{$common->is_match($user->ID, $approver_info->backup_user_id, ' selected')}>{$user->display_name} ({$user->user_login})</option>";
				}
				$backup_expires_on = date("Y-m-d", strtotime($approver_info->backup_expires_on));
				echo "</select></td></tr>";
			echo "<tr><td>Backup Approver Expires On:</td>
			<td><input type='date' name='backup_expires_on' value='{$backup_expires_on}'  data-date-inline-picker='false' data-date-open-on-focus='true' ></td></tr>";
		}
		if ($queue_invoice->employee_invoicer_check()!=0) {
			$primary_sort_col = get_user_option('time_sheets_invoicing_primary_sort_col', $user_id);
			$primary_sort_order = get_user_option('time_sheets_invoicing_primary_sort_order', $user_id);
			$secondary_sort_col = get_user_option('time_sheets_invoicing_secondary_sort_col', $user_id);
			$secondary_sort_order = get_user_option('time_sheets_invoicing_secondary_sort_order', $user_id);
			$tertiary_sort_col = get_user_option('time_sheets_invoicing_tertiary_sort_col', $user_id);
			$tertiary_sort_order = get_user_option('time_sheets_invoicing_tertiary_sort_order', $user_id);
			$allow_show_record_invoicing = get_user_option('time_sheets_invoicing_allow_show_record_invoicing', $user_id);

			if ($allow_show_record_invoicing != '') {
				$allow_show_record_invoicing = 'checked';
			}

			if ($primary_sort_col=='') {
				$primary_sort_col = 'c.ClientName';
			}
			if ($primary_sort_order=='') {
				$primary_sort_order='asc';
			}
			if ($secondary_sort_col=='') {
				$secondary_sort_col = 't.start_date';
			}
			if ($secondary_sort_order=='') {
				$secondary_sort_order='asc';
			}
			if ($tertiary_sort_col=='') {
				$tertiary_sort_col = 't.start_date';
			}
			if ($secondary_sort_order=='') {
				$stertiary_sort_order='asc';
			}

			echo "<tr><td>Invoice List Sort Order</td><td>
				<select name='primary_sort_col'>
					<option{$common->is_match('c.ClientName', $primary_sort_col, ' selected')}>Client</option>
					<option{$common->is_match('cp.ProjectName', $primary_sort_col, ' selected')}>Project Name</option>
					<option{$common->is_match('t.start_date', $primary_sort_col, ' selected')}>Start Date</option>
					<option{$common->is_match('t.timesheet_id', $primary_sort_col, ' selected')}>Time Sheet</option>
					<option{$common->is_match('u.display_name', $primary_sort_col, ' selected')}>User</option>
				</select>
				<select name='primary_sort_order'>
					<option{$common->is_match('asc', $primary_sort_order, ' selected')}>Ascending</option>
					<option{$common->is_match('desc', $primary_sort_order, ' selected')}>Descending</option>
				</select>
				<select name='secondary_sort_col'>
					<option{$common->is_match('c.ClientName', $secondary_sort_col, ' selected')}>Client</option>
					<option{$common->is_match('cp.ProjectName', $secondary_sort_col, ' selected')}>Project Name</option>
					<option{$common->is_match('t.start_date', $secondary_sort_col, ' selected')}>Start Date</option>
					<option{$common->is_match('t.timesheet_id', $secondary_sort_col, ' selected')}>Time Sheet</option>
					<option{$common->is_match('u.display_name', $secondary_sort_col, ' selected')}>User</option>
				</select>
				<select name='secondary_sort_order'>
					<option{$common->is_match('asc', $secondary_sort_order, ' selected')}>Ascending</option>
					<option{$common->is_match('desc', $secondary_sort_order, ' selected')}>Descending</option>
				</select>
				<select name='tertiary_sort_col'>
					<option{$common->is_match('c.ClientName', $tertiary_sort_col, ' selected')}>Client</option>
					<option{$common->is_match('cp.ProjectName', $tertiary_sort_col, ' selected')}>Project Name</option>
					<option{$common->is_match('t.start_date', $tertiary_sort_col, ' selected')}>Start Date</option>
					<option{$common->is_match('t.timesheet_id', $tertiary_sort_col, ' selected')}>Time Sheet</option>
					<option{$common->is_match('u.display_name', $tertiary_sort_col, ' selected')}>User</option>
				</select>
				<select name='tertiary_sort_order'>
					<option{$common->is_match('asc', $tertiary_sort_order, ' selected')}>Ascending</option>
					<option{$common->is_match('desc', $tertiary_sort_order, ' selected')}>Descending</option>
				</select>
			</td></tr>";

			echo "<tr><td colspan='2'><input type='checkbox' name='allow_show_record_invoicing' value='checked' {$allow_show_record_invoicing}> Always show 'Record Invoicing' button above time sheet list</td></tr>";
		}
		if ($queue_invoice->employee_invoicer_check ()==1) {
			$allow_invoice_comment_snipit = get_user_option('allow_invoice_comment_snipit', $user_id);
			
			if ($allow_invoice_comment_snipit) {
				$allow_invoice_comment_snipit = "checked";
			}
			
			echo "<tr><td colspan='2'><input type='checkbox' name='allow_invoice_comment_snipit' value='checked' {$allow_invoice_comment_snipit}> Show a snipit of the comments on the Closed Timesheet's search screen</td></tr>";
			
		}

		if ($options['users_override_location']) {
			$menu_location = get_user_option('time_sheets_menu_location', $user_id);

			echo "<tr><td>Time Sheets Below:</td><td><select name='menu_location'>
			<option value='1'{$common->is_match('1', $menu_location, ' selected')}>Top Menu</option>
			<option value='3'{$common->is_match('3', $menu_location, ' selected')}>Dashboard</option>
			<option value='6'{$common->is_match('6', $menu_location, ' selected')}>Posts</option>
			<option value='11'{$common->is_match('11', $menu_location, ' selected')}>Media</option>
			<option value='16'{$common->is_match('16', $menu_location, ' selected')}>Links</option>
			<option value='21'{$common->is_match('21', $menu_location, ' selected')}>Pages</option>
			<option value='26'{$common->is_match('26', $menu_location, ' selected')}>Comments</option>
			<option value='61'{$common->is_match('61', $menu_location, ' selected')}>Appearance</option>
			<option value='66'{$common->is_match('66', $menu_location, ' selected')}>Plugins</option>
			<option value='71'{$common->is_match('71', $menu_location, ' selected')}>Users</option>
			<option value='76'{$common->is_match('76', $menu_location, ' selected')}>Tools</option>
			<option value='81'{$common->is_match('81', $menu_location, ' selected')}>Settings</option>
			<option value='10000'{$common->is_match('10000', $menu_location, ' selected')}>Bottom of List</option>
			<option value=''{$common->is_match('', $menu_location, ' selected')}>Default Location - Where ever WordPress wants (or where your admin puts it)</option>
		</select></td></tr>";
		}

		if ($options['user_specific_date_format']) {
			$user_date = get_user_option('user_date_format', $user_id);

			echo "<tr><td>Custom Date Format:</td><td><input type='text' name='user_date_format' value='{$user_date}'>  <a href='https://codex.wordpress.org/Formatting_Date_and_Time'>Documentation on Date Formatting</a><BR>Use of a non-ISO date format can cause search issues when searching for old time sheets.</td></tr>";
		}

		echo "<tr><td colspan='2'><input type='submit' value='Save Settings' name='submit' class='button-primary'></td></tr>";
		echo "</table>";
		if ($queue_approval->employee_approver_check('false')==1) {
			echo "
<script>
function enableTransferCheckBox() {
	if (mysettings.transfer_to_user_id.selectedIndex != 0) {
		mysettings.approve_change.disabled=false;
		mysettings.approve_change.checked=false;
	} else {
		mysettings.approve_change.disabled=true;
	}
}

function enableApproverDate() {

	if (mysettings.backup_user_id.selectedIndex == 0) {
		mysettings.backup_expires_on.disabled=true;
	} else {
		mysettings.backup_expires_on.disabled=false;
	}
}

enableApproverDate();
</script>";
		}
	}

	function save_settings() {
		global $wpdb;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();
		$queue_approval = new time_sheets_queue_approval();
		$queue_invoice = new time_sheets_queue_invoice();

		$db = new time_sheets_db();

		$user = wp_get_current_user();
		$user_id = $user->ID;
		update_user_option( $user_id, 'timesheet_defaultdate', $_POST['default_date'], false);

		if ($_POST['transfer_to_user_id'] != '') {
			if ($_POST['approve_change']) {
				$sql = "insert into {$wpdb->prefix}timesheet_approvers (user_id) values (%d)";
				$parms = array(intval($_POST['transfer_to_user_id']));

				$db->query($sql, $parms);
				
				$common->archive_records ('timesheet_approvers', 'timesheet_approvers_archive', 'user_id', intval($_POST['transfer_to_user_id']), '=', 'INSERTED');
				
				$sql = "update {$wpdb->prefix}timesheet_approvers_approvies
					set approver_user_id = %d
					where approver_user_id = %d";

				$parms = array(intval($_POST['transfer_to_user_id']), $user_id);

				$db->query($sql, $parms);
				
				$common->archive_records ('timesheet_approvers_approvies', 'timesheet_approvers_approvies_archive', 'approver_user_id', intval($_POST['transfer_to_user_id']), '=', 'INSERTED');

				echo '<div class="notice notice-success is-dismissible"><p>Employees Transferred.</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>Team was not transferred as approval check box was not checked.</p></div>';
			}
		}

		if ($_POST['backup_user_id']) {
			$sql = "update {$wpdb->prefix}timesheet_approvers
				set backup_user_id = %d, backup_expires_on = '%s'
				where user_id = {$user_id}";
			$backup_expires_on = isset($_POST['backup_expires_on']) ? $_POST['backup_expires_on'] : "";
			$parms = array(intval($_POST['backup_user_id']), date("Y-m-d", strtotime($backup_expires_on)));
		} else {
			$sql = "update {$wpdb->prefix}timesheet_approvers
				set backup_user_id = NULL, backup_expires_on = NULL
				where user_id = {$user_id}";

			$parms = array();
		}

		$db->query($sql, $parms);
		
		$common->archive_records ('timesheet_approvers', 'timesheet_approvers_archive', 'user_id', $user_id, '=', 'UPDATED');
		

		if ($queue_invoice->employee_invoicer_check()!=0) {
			$primary_sort_col = $_POST['primary_sort_col'];
			$primary_sort_order = $_POST['primary_sort_order'];
			$secondary_sort_col = $_POST['secondary_sort_col'];
			$secondary_sort_order = $_POST['secondary_sort_order'];
			$tertiary_sort_col = $_POST['tertiary_sort_col'];
			$tertiary_sort_order = $_POST['tertiary_sort_order'];
			
			$allow_show_record_invoicing = $_POST['allow_show_record_invoicing'];
			$allow_invoice_comment_snipit = $_POST['allow_invoice_comment_snipit'];

			if ($primary_sort_col == 'Client') {
				$primary_sort_col = 'c.ClientName';
			} elseif ($primary_sort_col == 'First Billed Date') {
				$primary_sort_col = 'c.first_billed_day';
			} elseif  ($primary_sort_col == 'Project Name') {
				$primary_sort_col = 'cp.ProjectName';
			} elseif  ($primary_sort_col == 'Start Date') {
				$primary_sort_col = 't.start_date';
			} elseif  ($primary_sort_col == 'Time Sheet') {
				$primary_sort_col = 't.timesheet_id';
			} elseif  ($primary_sort_col == 'User') {
				$primary_sort_col = 'u.display_name';
			} else {
				$primary_sort_col = 'c.ClientName';
			}

			if ($primary_sort_order == 'Ascending') {
				$primary_sort_order = 'asc';
			} else {
				$primary_sort_order = 'desc';
			}

			if ($secondary_sort_col == 'Client') {
				$secondary_sort_col = 'c.ClientName';
			} elseif ($secondary_sort_col == 'First Billed Date') {
				$secondary_sort_col = 'c.first_billed_day';
			} elseif  ($secondary_sort_col == 'Project Name') {
				$secondary_sort_col = 'cp.ProjectName';
			} elseif  ($secondary_sort_col == 'Start Date') {
				$secondary_sort_col = 't.start_date';
			} elseif  ($secondary_sort_col == 'Time Sheet') {
				$secondary_sort_col = 't.timesheet_id';
			} elseif  ($secondary_sort_col == 'User') {
				$secondary_sort_col = 'u.display_name';
			} else {
				$secondary_sort_col = 'c.ClientName';
			}

			if ($secondary_sort_order == 'Ascending') {
				$secondary_sort_order = 'asc';
			} else {
				$secondary_sort_order = 'desc';
			}
			
			if ($tertiary_sort_col == 'Client') {
				$tertiary_sort_col = 'c.ClientName';
			} elseif ($tertiary_sort_col == 'First Billed Date') {
				$tertiary_sort_col = 'c.first_billed_day';
			} elseif  ($tertiary_sort_col == 'Project Name') {
				$tertiary_sort_col = 'cp.ProjectName';
			} elseif  ($tertiary_sort_col == 'Start Date') {
				$tertiary_sort_col = 't.start_date';
			} elseif  ($tertiary_sort_col == 'Time Sheet') {
				$tertiary_sort_col = 't.timesheet_id';
			} elseif  ($tertiary_sort_col == 'User') {
				$tertiary_sort_col = 'u.display_name';
			} else {
				$tertiary_sort_col = 'c.ClientName';
			}

			if ($tertiary_sort_order == 'Ascending') {
				$tertiary_sort_order = 'asc';
			} else {
				$tertiary_sort_order = 'desc';
			}


			if ($allow_show_record_invoicing) {
				$allow_show_record_invoicing = 'checked';
			}
			
			if ($allow_invoice_comment_snipit) {
				$allow_invoice_comment_snipit = 'checked';
			}

			update_user_option($user_id, 'time_sheets_invoicing_primary_sort_col', $primary_sort_col, false);
			update_user_option($user_id, 'time_sheets_invoicing_primary_sort_order', $primary_sort_order, false);
			update_user_option($user_id, 'time_sheets_invoicing_secondary_sort_col', $secondary_sort_col, false);
			update_user_option($user_id, 'time_sheets_invoicing_secondary_sort_order', $secondary_sort_order, false);
			update_user_option($user_id, 'time_sheets_invoicing_tertiary_sort_col', $tertiary_sort_col, false);
			update_user_option($user_id, 'time_sheets_invoicing_tertiary_sort_order', $tertiary_sort_order, false);
			update_user_option($user_id, 'time_sheets_invoicing_allow_show_record_invoicing', $allow_show_record_invoicing, false);
			update_user_option($user_id, 'allow_invoice_comment_snipit', $allow_invoice_comment_snipit, false);
		}

		if ($_POST['menu_location']) {
			update_user_option($user_id, 'time_sheets_menu_location', intval($_POST['menu_location']), false);
		} else {
			delete_user_option($user_id, 'time_sheets_menu_location', false);
		}

		if ($options['user_specific_date_format']) {
			update_user_option($user_id, 'user_date_format', sanitize_text_field($_POST['user_date_format']), false);
		} else {
			delete_user_option($user_id, 'user_date_format', false);
		}

		echo '<div class="notice notice-success is-dismissible"><p>Settings updated.</p></div>';
	}
}
