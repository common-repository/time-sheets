<?php
class time_sheets_manage_projects {
	function main() {
		$main = new time_sheets_main();
		$queue_invoice = new time_sheets_queue_invoice();
		$queue_approval = new time_sheets_queue_approval();

		$invoicer=$queue_invoice->employee_invoicer_check();
		$approver=$queue_approval->employee_approver_check('false');
		
		$client_managers = new time_sheets_client_managers();
		
		echo "<P><form method='GET'>
		<input type='hidden' value='timesheet_manage_clients' name='page'>";
		IF (isset($_GET['ClientId'])) {
			echo "<input type='hidden' name='ClientId' value='";
			echo intval($_GET['ClientId']);
			echo "'>";
		} elseif (isset($_POST['ClientId'])) {
			echo "<input type='hidden' name='ClientId' value='";
			echo intval($_POST['ClientId']);
			echo "'>";
		}

		if (($client_managers->client_manager_check()==1) || ($approver==1)) {
			echo "<input type='submit' value='New Client' name='menu' class='button-primary'>&nbsp;
			<input type='submit' value='Edit Client' name='menu' class='button-primary'>&nbsp;";
		}
		if (($client_managers->client_manager_check()==1) || ($approver==1)) {
			echo "<input type='submit' value='New Project' name='menu' class='button-primary'>&nbsp;
			<input type='submit' value='Edit Project' name='menu' class='button-primary'>";
		}
		      echo "</form>";

		if (isset($_GET['menu'])) {
			if ($_GET['menu']=='New Client') {
				$this->add_client();
			}
			if ($_GET['menu']=='Edit Client') {
				$this->add_client_users();
			}
			if ($_GET['menu']=='New Project') {
				$this->NewProject();
			}
			if ($_GET['menu']=='Edit Project') {
				$this->EditProject();
			}
			if ($_GET['menu']=='view_timesheets_for_project') {
				$this->view_timesheets_for_project();
			}
			if ($_GET['menu'] == 'title_override') {
				$this->title_override();
			}
		}
	}
	function check_unique_project_name ($client_id, $project_id, $project_name) {
		global $wpdb;
		$db = new time_sheets_db();
		
		$sql = "select ProjectId from {$wpdb->prefix}timesheet_client_projects where ClientId = %d and ProjectName = %s and ProjectId <> %d";
		$params = array($client_id, $project_name, $project_id);
		$projects = $db->get_results ($sql, $params);
		
		$return = '';
		
		if ($projects) {
			$return = '<div class="notice notice-error is-dismissible"><p>The project name has already been used by another project for this client.</p></div>';
		}
		
		$sql = "select ProjectId from {$wpdb->prefix}timesheet_client_projects_changequeue where ClientId = %d and ProjectName = %s and ProjectId <> %d and is_processed = 0";
		$params = array($client_id, $project_name, $project_id);
		$projects = $db->get_results ($sql, $params);
		
		if ($projects) {
			$return = $return . '<div class="notice notice-error is-dismissible"><p>The project name is queued for use by another project for this client.</p></div>';
		}
		
		return $return;
		
	}
	
	function update_employee_to_title_with_expiration($project_id) {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$settings = new time_sheets_settings();
		
		$nonce = check_admin_referer('match_employee_to_title');
		
		$users = $settings->get_employee_list($project_id);
		
		if ($users) {
			foreach ($users as $user) {

				if ($_POST["user_{$user->ID}"] != '' && ($user->title_id != $_POST["user_{$user->ID}"])){
					$expiration_date = (($_POST["employee_expiration_{$user->ID}"]!='')?"'".$common->mysql_date($_POST["employee_expiration_{$user->ID}"])."'":"NULL");
					
					$sql = "insert into {$wpdb->prefix}timesheet_customer_project_employee_title_override
					(user_id, project_id, title_id, expiration_date)
					values
					(%d, %d, %d, {$expiration_date})
					on duplicate key update title_id = %d, expiration_date = {$expiration_date}";
					$params = array($user->ID, $project_id, intval($_POST["user_{$user->ID}"]), intval($_POST["user_{$user->ID}"]));

				} else {
					$sql = "delete from {$wpdb->prefix}timesheet_customer_project_employee_title_override where user_id = %d and project_id = %d";
					$params = array($user->ID, $project_id);
					
				}
				
				$db->query($sql, $params);
			}
		}
	}

	function title_override() {
		global $wpdb;
		$db = new time_sheets_db();
		$settings = new time_sheets_settings();
		
		$inputs = array(
			'page' => 'timesheet_manage_clients',
			'menu' => 'title_override',
			'ClientId' => intval($_GET['ClientId']),
			'ProjectId' => intval($_GET['ProjectId'])
		);
		
		if (isset($_POST['action'])) {
			if ($_POST['action'] == "Save Mappings") {
				$this->update_employee_to_title_with_expiration($inputs['ProjectId']);
			}
		}
		
		$settings->match_employee_to_title(TRUE, $inputs);
		
			
	}
	
	function view_timesheets_for_project(){
		$db = new time_sheets_db();
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;

		$sql = "select t.timesheet_id, t.start_date, t.total_hours, invoiced, approved, EmbargoPendingProjectClose, u.display_name
			from {$wpdb->prefix}timesheet t
			join {$wpdb->users} u on t.user_id = u.ID
			where ProjectId = %d
			order by start_date, display_name";
		$parms = array(intval($_GET['ProjectId']));

		$timesheets = $db->get_results($sql, $parms);

		if ($timesheets) {
			echo "<p><table border='1' cellpadding='0' cellspacing='1'><tr><td>Timesheet ID</td><td>Week Starting</td><td>Employee</td><td>Hours Invoiced</td><td>Is Approved</td><td>Is Invoiced</td><td>Is Embargoed</td></tr>";
			$totalhours = 0;
			foreach ($timesheets as $timesheet) {
				if ($timesheet->invoiced=="1") {
					$is_invoiced=plugins_url( 'check.png' , __FILE__);
				} else {
					$is_invoiced=plugins_url( 'x.png' , __FILE__);
				}
				if ($timesheet->approved=="1") {
					$is_approved=plugins_url( 'check.png' , __FILE__);
				} else {
					$is_approved= plugins_url( 'x.png' , __FILE__) ;
				}
				if ($timesheet->EmbargoPendingProjectClose=="1") {
					$is_Embargoed= plugins_url( 'check.png' , __FILE__) ;
				} else {
					$is_Embargoed= plugins_url( 'x.png' , __FILE__);
				}
				$totalhours = $totalhours + $timesheet->total_hours;
				$start_date = date('Y-m-d', strtotime($timesheet->start_date));
				echo "<tr><td align='center'><a href='admin.php?page=show_timesheet&timesheet_id={$timesheet->timesheet_id}'>{$timesheet->timesheet_id}</a></td>
				<td align='center'>{$start_date}</td>
				<td>{$timesheet->display_name}</td>
				<td align='center'>{$timesheet->total_hours}</td>
				<td align='center'><img src='{$is_approved}' width='15' height='15'></td>
				<td align='center'><img src='{$is_invoiced}' width='15' height='15'></td>
				<td align='center'><img src='{$is_Embargoed}' width='15' height='15'></td>
				</tr>";
			}
			echo "</table>
			<BR>
			Total Hours To Date: {$totalhours}";
		} else {
			echo "There are no timesheets for this project at this time.";
		}
	}


	function add_client_users() {
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;

		if (isset($_POST['ClientId']) && isset($_POST['action']) && $_POST['action']=='update_client1') {
			$sql = "select ID
				from {$wpdb->users} u";
			$users = $db->get_results($sql);

			foreach ($users as $user) {
				$id = "user_{$user->ID}";

				if (isset($_POST[$id])) {
					$sql = "select * from {$wpdb->prefix}timesheet_clients_users where ClientId = %d and user_id = %d";
					$parms = array(intval($_POST['ClientId']), $user->ID);
					unset($u);
					$u= $db->query($sql, $parms);
					
					if (!$u) {

						$sql = "INSERT IGNORE INTO {$wpdb->prefix}timesheet_clients_users
							set ClientId=%d,
							    user_id=%d";

						$db->query($sql, $parms);
						$common->archive_records ('timesheet_clients_users', 'timesheet_clients_users_archive', "user_id = {$user->ID} and ClientId", intval($_POST['ClientId']), '=', 'INSERTED');
						
					}
				} else {
					
					$common->archive_records ('timesheet_clients_users', 'timesheet_clients_users_archive', "user_id = {$user->ID} and ClientId", intval($_POST['ClientId']), '=', 'DELETED');
					
					$sql = "delete from {$wpdb->prefix}timesheet_clients_users
					where ClientId = %d and user_id = %d";

					$db->query($sql, array(intval($_POST['ClientId']), $user->ID));
				}
			}

			$sql = "update {$wpdb->prefix}timesheet_clients set ClientName = %s, sales_person_id = %d, technical_sales_person_id = %d where ClientId = %d";
			$parms = array(sanitize_text_field($_POST['ClientName']), intval($_POST['sales_person_id']), intval($_POST['technical_sales_person_id']), intval($_POST['ClientId']));

			$db->query($sql, $parms);
			
			$common->archive_records ('timesheet_clients', 'timesheet_clients_archive', 'ClientId', intval($_POST['ClientId']), '=', 'UPDATED');

			echo '<div class="notice notice-success is-dismissible"><p>Client Updated.</p></div>';
		}

		echo "<form name='new_client' method='POST'>
			<input type='hidden' value='timesheet_manage_clients' name='page'>
			<input type='hidden' value='Edit Client' name='menu'>";
		echo "<table><tr><td>Client Name</td><td>";
		$this->GetClient();
		#$clients = $db->get_results("select ClientName, tc.ClientId, tc.sales_person_id
		#			from {$wpdb->prefix}timesheet_clients tc
		#			order by ClientName");

		#if ($clients) {
		#	echo "<td><select name='ClientId'>";
		#	foreach ($clients as $client) {
		#		echo "<option value='{$client->ClientId}'";
		#		if ($client->ClientId==$_POST['ClientId']) {
		#			echo " selected";
		#		}
		#		echo ">{$client->ClientName}</option>";
		#	}
		#	echo "</select>";
		#}
		echo "</td></tr><tr><td colspan='2'><input type='submit' value='Select Client' name='submit' class='button-primary'></td></tr></table>";
		echo "<input type='hidden' name='action' value='update_client'>";
		echo "</form>";

		if (isset($_POST['ClientId'])) {
			$sql = "select u.user_login, u.ID, tcu.user_id cu, u.display_name
				from {$wpdb->users} u
				join {$wpdb->prefix}usermeta um on u.id = um.user_id
				left outer join {$wpdb->prefix}timesheet_clients_users tcu on u.id = tcu.user_id
				and tcu.ClientId = %d
				where um.meta_key = '{$wpdb->prefix}capabilities'
					and um.meta_value != 'a:0:{}'
				order by u.display_name";

			$users = $db->get_results($sql, array(intval($_POST['ClientId'])));

			$sql = "select * from {$wpdb->prefix}timesheet_clients where ClientId = %d";

			$client = $db->get_row($sql, array(intval($_POST['ClientId'])));

			echo "<form method='POST' name='new_post'>

			<input type='hidden' value='timesheet_manage_clients' name='page'>
			<input type='hidden' value='Edit Client' name='menu'>";
			echo "<BR><table><tr><td>Client Name:</td><td><input type='text' name='ClientName' value='{$client->ClientName}'></td></tr><tr><td>Sales Person</td><td>";
			$users1 = $common->return_employee_list();
			echo "<select name='sales_person_id'>
				<option value='-10'>No Sales Person</option>";
			foreach ($users1 as $user) {
				echo "<option value='{$user->id}'{$common->is_match($client->sales_person_id, $user->id, ' selected', 0)}>{$user->display_name}</option>";
			}
			echo "</select></td></tr><tr><td>Technical Sales Person</td><td>";

			echo "<select name='technical_sales_person_id'>
				<option value='-10'>No Sales Person</option>";
			foreach ($users1 as $user) {
				echo "<option value='{$user->id}'{$common->is_match($client->technical_sales_person_id, $user->id, ' selected', 0)}>{$user->display_name}</option>";
			}
			
			echo "</select></td></tr>";
			echo "</table><br>Employees working on this client<br>
			<table border='1' cellspacing='0'><tr><td>&nbsp;</td><td align='center'>User Name</td><td align='center'>User</td></tr>";
			foreach ($users as $user) {
				echo "<tr><td><input type='checkbox' name='user_{$user->ID}' value='checked'";
				if ($user->cu) {
					echo " checked";
				}
				echo "></td><td>{$user->user_login}</td><td>{$user->display_name}</td></tr>";
			}
			echo "</table>";
			echo "</td></tr></table><BR><input type='submit' value='Update Client' name='submit' class='button-primary'>";
			echo "<input type='hidden' value='update_client1' name='action'>";
			echo "<input type='hidden' value='{$_POST['ClientId']}' name='ClientId'>";
			echo "</form>";
		}

	}


	function add_client() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$common = new time_sheets_common();

		if (isset($_POST['action']) && $_POST['action']=='save_client' && isset($_POST['ClientName'])) {
			$ClientId=$db->get_var("select ClientId from {$wpdb->prefix}timesheet_clients where ClientName=%s", array(sanitize_text_field($_POST['ClientName'])));

			$ClientName = sanitize_text_field($_POST['ClientName']);
			
			if (!$ClientId) {
				$db->query("insert into {$wpdb->prefix}timesheet_clients
					(ClientName, Active, sales_person_id, technical_sales_person_id)
					values
					(%s, 1, %d, %d)", array(sanitize_text_field($_POST['ClientName']), intval($_POST['sales_person_id']), intval($_POST['technical_sales_person_id'])));
				
				$common->archive_records ('timesheet_clients', 'timesheet_clients_archive', 'ClientName', "'{$ClientName}'", '=', 'INSERTED');
				

				$ClientId=$db->get_var("select ClientId from {$wpdb->prefix}timesheet_clients where ClientName=%s", array($ClientName));

				$sql = "select ID
				from {$wpdb->users} u";
				$users = $db->get_results($sql);

				foreach ($users as $user) {
					$id = "user_{$user->ID}";
					
					if (isset($_POST[$id])) {
					
						$sql = "insert into {$wpdb->prefix}timesheet_clients_users
							(ClientId, user_id)
							values (%d, %d)";
						$parms = array($ClientId, $user->ID);
						$db->query($sql, $parms);
						
						$common->archive_records ('timesheet_clients_users', 'timesheet_clients_users_archive', "ClientId = {$ClientId} and user_id", $user->ID, '=', 'INSERTED');				
					}
				}
				
				echo '<div class="notice notice-success is-dismissible"><p>Client Added.</p></div>';
			} else {
				$db->query("insert into {$wpdb->prefix}timesheet_clients_users
					(ClientId, user_id)
					values
					($ClientId, $user_id)");
				
				$common->archive_records ('timesheet_clients_users', 'timesheet_clients_users_archive', 'ClientId = {$ClinetId} and user_id', $user_id, '=', 'INSERTED');				

				echo '<div class="notice notice-error"><p>Client already exists.<BR>Access granted to client.</p></div>';
			}
		}

		echo "<form name='new_client' method='POST'>
			<input type='hidden' value='timesheet_manage_clients' name='page'>
			<input type='hidden' value='New Client' name='menu'>";
		echo "<table><tr><td>Client Name</td><td><input type='text' name='ClientName'></td></tr>";
		echo "<tr><td>Sales Person</td><td>";
		$users = $common->return_employee_list();
		echo "<select name='sales_person_id'>
				<option value='-10'>No Sales Person</option>";
		foreach ($users as $user) {
			echo "<option value='{$user->id}'>{$user->display_name}</option>";
		}
		echo "</select></td></tr>";
		
		echo "<tr><td>Technical Sales Person</td><td>";
		echo "<select name='technical_sales_person_id'>
				<option value='-10'>No Sales Person</option>";
		foreach ($users as $user) {
			echo "<option value='{$user->id}'>{$user->display_name}</option>";
		}
		echo "</select></td></tr><tr><td colspan='3'>Employees working on this client</td></tr><tr><td colspan='2'>";
		$sql = "select u.user_login, u.ID, u.display_name
				from {$wpdb->users} u
				order by u.display_name";

			$users = $db->get_results($sql);

		echo "<table border='1' cellspacing='0'><tr><td>&nbsp;</td><td align='center'>User Name</td><td align='center'>User</td></tr>";
			foreach ($users as $user) {
				echo "<tr><td><input type='checkbox' name='user_{$user->ID}' value='checked'></td><td>{$user->user_login}</td><td>{$user->display_name}</td></tr>";
			}
			echo "</table>";
		echo "</td></tr><tr><td colspan='2'><input type='submit' value='Save Client' name='submit' class='button-primary'></td></tr>";

		echo "<input type='hidden' name='action' value='save_client'>";
		echo "</form>";
	}


	function NewProject() {
		global $wpdb;
		$db = new time_sheets_db();
		$options = get_option('time_sheets');
		$common = new time_sheets_common();

		if (isset($_GET['Active'])) {
			$Active = 1;
		}
		
		$IsRetainer = '';
		$BillOnProjectCompletion = '';
		$MaxHours = 0;
		$flat_rate = "";
		$close_on_completion = "";
		$retainer_id = 0;
		$hours_included = "";
		$hourly_rate = "";
		$max_monthly_budget = 0;
		
		if (isset($_GET['action']) && $_GET['action']=='Save Project'){
			$this->SaveNewProject();
			
			if (isset($_GET['IsRetainer'])) {
				$IsRetainer = ' checked';
			}
			if (isset($Active) && $Active==1) {
				$Active = ' checked';
			}
			if (isset($_GET['BillOnProjectCompletion'])) {
				$BillOnProjectCompletion = ' checked';
			} 
			if (isset($_GET['MaxHours'])) {
				$MaxHours = intval($_GET['MaxHours']);
			}

			if (isset($_GET['flat_rate'])) {
				$flat_rate = " checked";
			}
			
			if (isset($_GET['close_on_completion']) || isset($options['default_auto_close'])) {
				$close_on_completion = " checked";
			} 

			if(isset($_GET['retainer_id'])) {
				$retainer_id = intval($_GET['retainer_id']);
				$hours_included = intval($_GET['hours_included']);
				$hourly_rate = intval($_GET['hourly_rate']);
			}
			
			if (isset($_GET['max_monthly_budget'])) {
				$max_monthly_budget = intval($_GET['max_monthly_budget']);
			}
			
		} else {
				$Active = ' checked';
				$close_on_completion = " checked";
		}

		$ProjectName = isset($_GET['ProjectName']) ? $common->esc_textarea($_GET['ProjectName']) : "";
		$PONumber = isset($_GET['po_number']) ? $common->esc_textarea($_GET['po_number']) : "";

		echo "<form method='GET' name='projectprops'>
			<input type='hidden' value='timesheet_manage_clients' name='page'>
			<input type='hidden' value='New Project' name='menu'>
			<table><tr><td>Select Client:</td><td>";
			$this->GetClient();
			echo "</td></tr>
			<tr><td>Project Name:</td><td><input type='text' name='ProjectName' value='{$ProjectName}'></td></tr>
			<tr><td>PO Number:</td><td><input type='text' name='po_number' value='{$PONumber}'></td></tr><tr><td>Sales Person:</td><td>";
			$users = $common->return_employee_list();
			echo "<select name='sales_person_id'>
				<option value='-10'>No Sales Person</option>";
			foreach ($users as $user) {
				echo "<option value='{$user->id}'>{$user->display_name}</option>";
			}
			echo "</select></td></tr>";
			echo "<tr><td>Technical Sales Person:</td><td>";
			echo "<select name='technical_sales_person_id'>
				<option value='-10'>No Sales Person</option>";
			foreach ($users as $user) {
				echo "<option value='{$user->id}'>{$user->display_name}</option>";
			}
			echo "</select></td></tr>";


			$sql = "select retainer_name, retainer_id from {$wpdb->prefix}timesheet_custom_retainer_frequency where active = 1";
			$frequencies = $db->get_results($sql);


			echo "<td colspan='2'>
			<table><tr><td>Is Retainer Project </td><td><input type='checkbox' id='IsRetainer' name='IsRetainer' value='1'{$IsRetainer} onClick='setupRetainers()'></td><tr>
			<tr>";
			if (isset($options['allow_money_based_retainers'])) {
					echo "<td>Retainer Type:</td>
					<td><select name='retainer_type' onClick='changeRetainerType()'><option value='1'>Hourly</option>";
							echo "<option value='2'";
							if ($max_monthly_budget > 0) {
								echo " selected";
							}
							echo ">Money</option>";
					echo "</select></td>";
			}
				echo "
			 		<td>Retainer Interval</td><td>
				<select name='retainer_id'>
					<option value='2'>Weekly</option>
					<option value='1'>Monthly</option>
					<option value='3'>Quarterly</option>";

					if ($frequencies) {
						foreach ($frequencies as $freq) {
							echo "<option value='{$freq->retainer_id}'}>{$freq->retainer_name}</option>";
						}
					}

				echo "</select>
			</td></tr>
			
				<tr>
					<td>Retainer Hours</td>
					<td><input type='number'  id='hours_included'  name='hours_included'  'min='0' style='width: 5em' value='{$hours_included}'></td>";
			if (isset($options['allow_money_based_retainers'])) {
				echo "<td>Max Project Frequency Budget</td><td>{$options['currency_char']}<input type='number' name='max_monthly_budget'  'min='0' style='width: 5em'  value='{$max_monthly_budget}'> <div id='project_override'>test</div></td>";
				
				$sql = "select e.* from {$wpdb->prefix}timesheet_employee_titles e
				order by e.name";
				$titles = $db->get_results($sql);
				$valign = count((array)$titles)+1;
			} else {
				$valign = 1;
			}
			echo "</tr>
			<tr><td rowspan='{$valign}' valign='top'>Retainer Rate</td><td rowspan='{$valign}' valign='top'><input type='number' id='hourly_rate' name='hourly_rate'  'min='0' style='width: 5em'  value='{$hourly_rate}'></td>";
			if (isset($options['allow_money_based_retainers'])) {
				if ($titles) {
					foreach ($titles as $title) {
						echo "<tr><td>Rate for {$title->name}:</td><td>{$options['currency_char']}<input type='number'  'min='0' style='width: 5em'  name='title_{$title->title_id}' value=''></td></tr>";
					}
				}
			}
			echo "</table><tr><td>Max Project Hours:</td><td><input type='number' name='MaxHours'  'min='0' style='width: 5em'  value='{$MaxHours}'></td></tr>
			<tr><td colspan='2'>Active / Visable <input type='checkbox' name='Active' value='1'{$Active}></td></tr>";
			if (!isset($options['remove_embargo'])) {
				echo "
<tr><td colspan='2'>Bill at end of project <input type='checkbox' name='BillOnProjectCompletion' value='1'{$BillOnProjectCompletion}></td></tr>";
			}
			echo "<tr><td colspan='2'>Flat rate billing project <input type='checkbox' name='flat_rate' value='1'{$flat_rate}> Bypasses invoicing queue for this project</td></tr>";
			echo "<tr><td colspan='2'>Auto close project when project is out of hours <input type='checkbox' name='close_on_completion' value='1'{$close_on_completion}></td></tr>";
			$Notes = isset($_GET['Notes']) ? $common->esc_textarea($_GET['notes']) : "";
			echo "<tr><td>Notes:</td><td><textarea rows='4' cols='50' name='notes'>{$Notes}</textarea></td></tr>
			<tr><td colspan='2'>";

			echo "<input type='submit' value='Save Project' name='action' class='button-primary'";
			if (isset($_GET['action']) && $_GET['action']=='Save Project'){
				echo " disabled";
			}
			echo ">";

			echo "</td></tr>
			</table>";
			$this->ProjectJS();
	}

	function SaveNewProject() {
		if ($this->ValidateProject(true)==false) {
			return;
		}
		global $wpdb;

		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$options = get_option('time_sheets');
		$common = new time_sheets_common();

		if (isset($_GET['IsRetainer'])) {
			$IsRetainer=1;
			$retainer_id = $_GET['retainer_id'];
			$hours_included = $_GET['hours_included'];
			$hourly_rate = $_GET['hourly_rate'];
		} else {
			$IsRetainer=0;
			$retainer_id="";
			$hours_included="0";
			$hourly_rate="0";
		}

		if (isset($_GET['MaxHours'])) {
			$MaxHours = intval($_GET['MaxHours']);
		} else {
			$MaxHours = 0;
		}

		if (isset($_GET['Active'])) {
			$Active = 1;
		} else {
			$Active = 0;
		}

		if (isset($_GET['flat_rate'])) {
			$flat_rate = 1;
		} else {
			$flat_rate = 0;
		}

		if (isset($_GET['BillOnProjectCompletion'])) {
			$BillOnProjectCompletion = 1;
		} else {
			$BillOnProjectCompletion = 0;
		}
		if (isset($_GET['close_on_completion']) && $_GET['close_on_completion']==1) {
			$close_on_completion = "1";
		} else {
			$close_on_completion = "0";
		}
		
		if (isset($_GET['max_monthly_budget'])) {
			$max_monthly_budget = intval($_GET['max_monthly_budget']);
		} else {
			$max_monthly_budget = 0;
		}

		if (isset($options['require_unique_project_names'])) {
			$msg = $this->check_unique_project_name (intval($_GET['ClientId']), 0, sanitize_text_field($_GET['ProjectName']));
			if ($msg <> '') {
				echo $msg;
				return;
			}
		}

		$sql = "INSERT INTO {$wpdb->prefix}timesheet_client_projects
			(ClientId, ProjectName, IsRetainer, MaxHours, HoursUsed, Active, Notes, BillOnProjectCompletion, flat_rate, po_number, sales_person_id, technical_sales_person_id, close_on_completion, retainer_id, hours_included, hourly_rate, max_monthly_bucket)
			values
			(%d, %s, %d, %d, %d, %d, %s, %d, %d, %s, %d, %d, %d, %d, %d, %d, %d)";

		$parms = array(intval($_GET['ClientId']), sanitize_text_field($_GET['ProjectName']), $IsRetainer, $MaxHours, 0, $Active, sanitize_textarea_field($_GET['notes']), $BillOnProjectCompletion, $flat_rate, sanitize_text_field($_GET['po_number']), intval($_GET['sales_person_id']), intval($_GET['technical_sales_person_id']), $close_on_completion, $retainer_id, $hours_included, $hourly_rate, $max_monthly_budget);
		
		$db->query($sql, $parms);
		
		$sql = "select max(ProjectId) project_id from {$wpdb->prefix}timesheet_client_projects";
		$project = $db->get_row($sql);

		$common->archive_records ('timesheet_client_projects', 'timesheet_client_projects_archive', 'ProjectId', $project->project_id, '=', 'INSERTED');				
	
		
		if (isset($options['allow_money_based_retainers'])) {
			$this->write_title_rates($project->project_id);
		}
		
		echo "<div class='notice notice-success is-dismissible'><p>Project added. <a href='./admin.php?page=timesheet_manage_clients&menu=Edit+Project&ClientId={$common->intval($_GET['ClientId'])}&ProjectId={$project->project_id}&action=Select+Project'>Edit</A> Project</p></div>";
		
	}

	function ValidateProject($existsCheck) {
		global $wpdb;
		$db = new time_sheets_db();
		
		$options = get_option('time_sheets');
		
		$valid = true;
		if (!$_GET['ClientId']) {
			echo '<div class="notice notice-error is-dismissible"><p>The selected client is invalid.</p></div>';
			$valid=false;
		}

		if (!$_GET['ProjectName']) {
			echo '<div class="notice notice-error is-dismissible"><p>The name of the project is required.</p></div>';
			$valid=false;
		}

		if (isset($_GET['IsRetainer']) && !isset($options['retainers_per_client'])) {
			if ($_GET['ProjectId']) {
				$sql = "select * from {$wpdb->prefix}timesheet_client_projects where ClientId=%d and IsRetainer=1 and ProjectId<>%d";
				$parms = array(intval($_GET['ClientId']), intval($_GET['ProjectId']));
			} else {
				$sql = "select * from {$wpdb->prefix}timesheet_client_projects where ClientId=%d and IsRetainer=1";
				$parms = array(intval($_GET['ClientId']));
			}

			$project = $db->get_row($sql, $parms);
			if ($project) {
				echo "<div class='notice notice-error is-dismissible'><p>There is already a retainer project for this client. Change the retainer status for '{$project->ProjectName}' to correct this.</p></div>";
				$valid=false;
			}
		}
	
		if ($existsCheck==true) {
			$sql = "select count(*) from {$wpdb->prefix}timesheet_client_projects where ClientId=%d and ProjectName=%s";
			$params = array(intval($_GET['ClientId']), intval($_GET['ProjectName']));
			$ct = $db->get_var($sql, $params);
			if ($ct!=0) {
				echo '<div class="notice notice-error is-dismissible"><p>This project already exists for this client.</p></div>';
				$valid=false;
			}
		}
		return $valid;
	}

	function EditProject() {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$options = get_option('time_sheets');

		if (isset($_GET['subaction']) && $_GET['subaction']=='Save Project')
		{
			$this->SaveExistingProject();
		}
		if (isset($_GET['subaction']) && $_GET['subaction']=='delete_queue')
		{
			$this->DeleteProjectChangeQueue(intval($_GET['ProjectId']), intval($_GET['queue_id']));
		}

		$this->GetProjects();
		echo "<form method='GET' name='editproject'>
			<input type='hidden' value='timesheet_manage_clients' name='page'>
			<input type='hidden' value='Edit Project' name='menu'>
			<table><tr><td>Select Client:</td><td>";
			$this->GetClient();
			echo "</td></tr>
			<tr><td>Project Name:</td><td><select name='ProjectId'></select></td></tr>
			<tr><td colspan='2'><input type='submit' value='Select Project' name='action' class='button-primary'></td></tr>
			</form>
			<script>resetProject();</script>";
			

		if (isset($_GET['action']) && $_GET['action']=='Select Project') {


			if (isset($_GET['subaction']) && $_GET['subaction']=='view_queue')
			{
				$project = $db->get_row("select * from {$wpdb->prefix}timesheet_client_projects_changequeue tcp where ProjectId=%d and queue_id=%d order by ProjectName", array(intval($_GET['ProjectId']), intval($_GET['queue_id'])));
				$process_date = $project->process_date;
			} else {
				$project = $db->get_row("select * from {$wpdb->prefix}timesheet_client_projects tcp where ProjectId=%d order by ProjectName", array(intval($_GET['ProjectId'])));
				$process_date = '';
			}

			$IsRetainer = '';
			$Active = '';
			$BillOnProjectCompletion = '';
			$flat_rate = '';
			$close_on_completion = '';
			
			if ($project->IsRetainer==1) {
				$IsRetainer=" checked";
				$retainer_id = $project->retainer_id;
				$hours_included = $project->hours_included;
				$hourly_rate = $project->hourly_rate;
				$max_monthly_budget = $project->max_monthly_bucket;
			} else {
				$hours_included = "";
				$hourly_rate = "";
				$max_monthly_budget = 0;
			}
			if ($project->Active==1) {
				$Active=" checked";
			}
			if ($project->BillOnProjectCompletion==1) {
				$BillOnProjectCompletion = " checked";
			}
			if ($project->flat_rate==1) {
				$flat_rate = " checked";
			}
			if ($project->close_on_completion==1) {
				$close_on_completion = " checked";
			} 

			$hoursused = (isset($project->HoursUsed)?$project->HoursUsed:0);
			$hoursleft = $project->MaxHours-(isset($project->HoursUsed)?$project->HoursUsed:0);
			
			echo "<form method='GET' name='projectprops'>
			<input type='hidden' value='timesheet_manage_clients' name='page'>
			<input type='hidden' value='Edit Project' name='menu'>
			<input type='hidden' value='Select Project' name='action'>
			<input type='hidden' value='{$common->intval($_GET['ProjectId'])}' name='ProjectId'>
			<input type='hidden' value='{$common->intval($_GET['ClientId'])}' name='ClientId'>
			<tr><td>Project Name:</td><td><input type='text' name='ProjectName' value='{$common->clean_from_db($project->ProjectName)}'></td></tr>
			<tr><td>PO Number:</td><td><input type='text' name='po_number' value='{$common->clean_from_db($project->po_number)}'></td></tr><tr><td>Sales Person:</td><td>";
			$users = $common->return_employee_list();
			echo "<select name='sales_person_id'>
				<option value='-10'>No Sales Person</option>";
			foreach ($users as $user) {
				echo "<option value='{$user->id}'{$common->is_match($project->sales_person_id, $user->id, ' selected', 0)}>{$user->display_name}</option>";
			}
			echo "</select></td></tr>";
			echo "<tr><td>Technical Sales Person:</td><td>";
			echo "<select name='technical_sales_person_id'>
				<option value='-10'>No Sales Person</option>";
			foreach ($users as $user) {
				echo "<option value='{$user->id}'{$common->is_match($project->technical_sales_person_id, $user->id, ' selected', 0)}>{$user->display_name}</option>";
			}
			echo "</select></td></tr>";


			$sql = "select retainer_name, retainer_id from {$wpdb->prefix}timesheet_custom_retainer_frequency where active = 1";
			$frequencies = $db->get_results($sql);


			echo "<td colspan='2'>
			<table><tr><td>Is Retainer Project </td><td><input type='checkbox' id='IsRetainer' name='IsRetainer' value='1'{$IsRetainer} onClick='setupRetainers()'></td><tr>
			<tr>";
			if (isset($options['allow_money_based_retainers'])) {
					echo "<td>Retainer Type:</td>
					<td><select name='retainer_type' onClick='changeRetainerType()'><option value='1'>Hourly</option>";
							echo "<option value='2'";
							if ($max_monthly_budget > 0) {
								echo " selected";
							}
							echo ">Money</option>";
					echo "</select></td>";
			}
				echo "
			 		<td>Retainer Interval</td><td>
				<select name='retainer_id'>
					<option value='2'{$common->is_match(2, $project->retainer_id, ' selected', 0)}>Weekly</option>
					<option value='1'{$common->is_match(1, $project->retainer_id, ' selected', 0)}>Monthly</option>
					<option value='3'{$common->is_match(3, $project->retainer_id, ' selected', 0)}>Quarterly</option>";

					if ($frequencies) {
						foreach ($frequencies as $freq) {
							echo "<option value='{$freq->retainer_id}'{$common->is_match($freq->retainer_id, $project->retainer_id, ' selected')}>{$freq->retainer_name}</option>";
						}
					}

				echo "</select>
			</td></tr>
			
				<tr>
					<td>Retainer Hours</td>
					<td><input type='number'  id='hours_included'  name='hours_included''min='0' style='width: 5em' value='{$hours_included}'></td>";
			if (isset($options['allow_money_based_retainers'])) {
				echo "<td>Max Project Frequency Budget</td><td>{$options['currency_char']}<input type='number' name='max_monthly_budget' 'min='0' style='width: 5em' value='{$max_monthly_budget}'> <div id='project_override'></div></td>";
				
				$sql = "select e.*, p.hourly_rate from {$wpdb->prefix}timesheet_employee_titles e
				left outer join {$wpdb->prefix}timesheet_project_employee_titles p on e.title_id = p.title_id and p.project_id = $project->ProjectId
				order by e.name";
				$titles = $db->get_results($sql);
				$valign = count((array)$titles)+1;
			} else {
				$valign = 1;
			}
			echo "</tr>
			<tr><td rowspan='{$valign}' valign='top'>Retainer Rate</td><td rowspan='{$valign}' valign='top'><input type='number' id='hourly_rate' name='hourly_rate' 'min='0' style='width: 5em' value='{$hourly_rate}'></td>";
			if (isset($options['allow_money_based_retainers'])) {
				if ($titles) {
					foreach ($titles as $title) {
						echo "<tr><td>Rate for {$title->name}:</td><td>{$options['currency_char']}<input type='number' 'min='0' style='width: 5em' name='title_{$title->title_id}' value='{$title->hourly_rate}'></td></tr>";
					}
				}
			}
			echo "</table><tr><td>Max Project Hours:</td><td><input type='number'  id='MaxHours' name='MaxHours' 'min='0' style='width: 5em' value='{$project->MaxHours}'></td></tr>
			<tr><td>Hours Used:</td><td><a href='admin.php?page=timesheet_manage_clients&menu=view_timesheets_for_project&ProjectId={$common->intval($_GET['ProjectId'])}'>{$hoursused}</a></td></tr>
			<tr><td>Hours Remaining:</td><td>{$hoursleft}</td></tr>
			";
			if (!isset($options['remove_embargo'])) {
				echo "
<tr><td >Bill on project completion </td><td><input type='checkbox' name='BillOnProjectCompletion' value='1' {$BillOnProjectCompletion}> (Timesheets are hidden and not processed by the approval queue until a timesheet is submitted with 'Project Completed' selected)</td></tr>";
			}
			echo "<tr><td>Active / Visable </td><td><input type='checkbox' name='Active' value='1'{$Active}></td></tr>
			<tr><td>Flat rate billing project </td><td><input type='checkbox' name='flat_rate' value='1'{$flat_rate}> Bypasses invoicing queue for this project</td></tr>
			<tr><td>Auto close project when<br>project is out of hours </td><td><input type='checkbox' name='close_on_completion' value='1'{$close_on_completion}></td></tr>
			<tr><td>Notes:</td><td><textarea rows='4' cols='50' name='notes'>{$common->clean_from_db($project->notes)}</textarea></td></tr>
			<tr><td>Delay Change Until:</td><td><input type='date' name='process_date' value='{$process_date}'></td></tr>
			<tr><td colspan='2'><input type='submit' value='Save Project' name='subaction' class='button-primary'></td></tr>
			</table>";

			echo "</form>";
			$this->ProjectJS();
			$this->ShowProjectChangeQueue($_GET['ProjectId']);
			$this->ShowProjectRetainerHistory($_GET['ProjectId']);
		}

	}

	function ProjectJS() {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$options = get_option('time_sheets');
		$sql = "select title_id from {$wpdb->prefix}timesheet_employee_titles e";
		$titles = $db->get_results($sql);
		
		
	echo "<script src=\"https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js\"></script>
		<script>
function changeRetainerType() {
	
	if (projectprops.retainer_type.value==1) {
		projectprops.hours_included.disabled = false;
		projectprops.hourly_rate.disabled = false;
		
		projectprops.max_monthly_budget.disabled = true;
		";
		if ($titles) {
			foreach ($titles as $title) {
				echo "
				projectprops.title_{$title->title_id}.disabled = true;
				";
			}
		}
		echo "
		$('#project_override').text('');

} else { //Money
		projectprops.hours_included.disabled = true;
		projectprops.hourly_rate.disabled = true;
		
		projectprops.max_monthly_budget.disabled = false;
		";
		if ($titles) {
			foreach ($titles as $title) {
				echo "
				projectprops.title_{$title->title_id}.disabled = false;
				";
			}
		}
		if (isset($_GET['ProjectId'])) {
			echo "
			document.getElementById('project_override').innerHTML = '<a href=\"./admin.php?page=timesheet_manage_clients&menu=title_override&ClientId={$common->intval($_GET['ClientId'])}&ProjectId={$common->intval($_GET['ProjectId'])}\">Override</a> employee titles for this project'
			";
		} else {
			echo "
			$('#project_override').text('Override employee titles for this project after saving the project')
			";
			
		}
		echo "
	}
}

function disableMaxHours() {
	if (projectprops.IsRetainer.checked==true) {
		projectprops.MaxHours.disabled=true;
		projectprops.flat_rate.disabled=true;
		projectprops.BillOnProjectCompletion.disabled=true;
		projectprops.retainer_id.disabled=false;
		projectprops.hours_included.disabled=false;
		projectprops.hourly_rate.disabled=false;
		";
		if (isset($options['allow_money_based_retainers'])) {
			echo "projectprops.max_monthly_budget.disabled=false;
			projectprops.retainer_type.disabled = false;
			";
			$sql = "select * from {$wpdb->prefix}timesheet_employee_titles order by name";
			$titles = $db->get_results($sql);
			if ($titles) {
				foreach ($titles as $title) {
					echo "projectprops.title_{$title->title_id}.disabled=false;
					";
				}
			}
			
		}
	echo "} else {
		projectprops.MaxHours.disabled=false;
		projectprops.BillOnProjectCompletion.disabled=false;
		projectprops.flat_rate.disabled=false;
		projectprops.retainer_id.disabled=true;
		projectprops.hours_included.disabled=true;
		projectprops.hourly_rate.disabled=true;
		";
		if (isset($options['allow_money_based_retainers'])) {
			echo "projectprops.max_monthly_budget.disabled=true;
			projectprops.retainer_type.disabled = true;
			";
			$sql = "select * from {$wpdb->prefix}timesheet_employee_titles order by name";
			$titles = $db->get_results($sql);
			if ($titles) {
				foreach ($titles as $title) {
					echo "projectprops.title_{$title->title_id}.disabled=true;
					";
				}
			}
		}
	echo "
	}
}

function setupRetainers() {

	disableMaxHours()";
	if (isset($options['allow_money_based_retainers'])) {
		echo "
		$('#project_override').text('');
		
		if (projectprops.IsRetainer.checked==true) {
			changeRetainerType()
		}
		";
		
	}
	echo "
}

setupRetainers()

</script>
";
	}

	function DeleteProjectChangeQueue($project_id, $queue_id) {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$options = get_option('time_sheets');

		$sql = "select * from {$wpdb->prefix}timesheet_client_projects_changequeue  where queue_id = %s";
		$parms = array($queue_id);
		$queue_record = $db->get_row($sql, $parms);

		if ($queue_record->ProjectId != $project_id) {
			echo '<div class="notice notice-error is-dismissible"><p>Unable to delete queued record, incorrect project specified or no record found.</p></div>';
		} else {
			$common->archive_records ('timesheet_project_employee_titles_changequeue', 'timesheet_project_employee_titles_change_archive', "queue_id", $queue_id, '=', 'DELETED');
			
			$sql = "delete from {$wpdb->prefix}timesheet_project_employee_titles_changequeue where queue_id = %d";
			$parms = array($queue_id);
			$db->query($sql, $parms);

			$common->archive_records ('timesheet_client_projects_changequeue', 'timesheet_client_projects_changequeue_archive', "queue_id", $queue_id, '=', 'DELETED');
			
			$sql = "delete from {$wpdb->prefix}timesheet_client_projects_changequeue  where queue_id = %d";
			$db->query($sql, $parms);

			echo '<div class="notice notice-success is-dismissible"><p>Project change queue record deleted.</p></div>';
		}
	}

	function ShowProjectRetainerHistory($project_id) {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$options = get_option('time_sheets');
		$currency_char = isset($options['currency_char'])?$options['currency_char']:'$';
		
		$project_id = intval($project_id);

		if (!isset($options['allow_recurring_timesheets'])) {
			return 0;
		}
		
		$sql = "select mu.*, cr.retainer_name from {$wpdb->prefix}timesheet_project_retainer_money_usage mu
		left outer join {$wpdb->prefix}timesheet_custom_retainer_frequency cr on mu.retainer_id = cr.retainer_id
		where project_id = {$project_id} order by frequency_start_date ASC";
		$info = $db->get_results($sql);
		
		if ($info) {
			echo "<p><table border='1' cellspacing='0' cellpadding='3'><tr><td>Period Start Date</td><td>Retainer Description</td><td>Period Amount</td><td>Amount Used</td></tr>";
			foreach ($info as $inf) {
				$bgcolor= ($inf->used_amount == $inf->max_frequency_bucket)?' bgcolor=yellow ':'';
				$bgcolor= ($inf->used_amount > $inf->max_frequency_bucket)?' bgcolor=red  style=color:white':'';
				
				if (!isset($inf->retainer_name)) {
					if ($inf->retainer_id==1) {
						$retainer = 'Monthly';
					} elseif ($inf->retainer_id==2) {
						$retainer = 'Weekly';
					} elseif ($inf->retainer_id==3) {
						$retainer = 'Quarterly';
					}
				} else {
					$retainer = $inf->retainer_name;
				}
				
				echo "<tr align='center'><td>{$common->mysql_date($inf->frequency_start_date)}</td><td>{$retainer}</td><td>{$currency_char}{$inf->max_frequency_bucket}</td><td{$bgcolor}>{$currency_char}{$inf->used_amount}</td></tr>";
			}
		}
		echo "</table><BR>
		* If there are no time sheets for the interval, than there will be no record.";
	}
	
	function ShowProjectChangeQueue($project_id) {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		$options = get_option('time_sheets');

		$project_id = intval($project_id);

		$sql = "select * 
			from {$wpdb->prefix}timesheet_client_projects_changequeue 
			where ProjectId = $project_id 
				and is_deleted = 0
				order by process_date ASC";

		$queue = $db->get_results($sql);

		if ($queue) {
			
 
			echo "<table border='1' cellspacing='0' cellpadding='3'><tr><td>Actions</td><td>Process On</td><td>Project Name</td><td>Retainer</td><td>Max Hours</td><td>Active</td><td>PO Number</td><td>Close On Completion</td><td>Retainer Hours</td><td>Retainer Rate</td></tr>";
			foreach ($queue as $project) {
				
				
				$retainer = ($project->IsRetainer==0 ? 'False':'True');
				$active  = ($project->Active==0 ? 'False':'True');
				$close = ($project->close_on_completion==0 ? 'False':'True');

				
				echo "<tr><td align='center'>";
				echo "<a href='admin.php?page=timesheet_manage_clients&menu=Edit+Project&ClientId={$_GET['ClientId']}&ProjectId={$_GET['ProjectId']}&action=Select+Project&subaction=view_queue&queue_id={$project->queue_id}'><img src='". plugins_url( 'view.png' , __FILE__) ."' height='15' width='15'></a>";
				echo "&nbsp;&nbsp;";
				if ($project->is_processed == 0) {
					echo "<a href='admin.php?page=timesheet_manage_clients&menu=Edit+Project&ClientId={$_GET['ClientId']}&ProjectId={$_GET['ProjectId']}&action=Select+Project&subaction=delete_queue&queue_id={$project->queue_id}'><img src='". plugins_url( 'x.png' , __FILE__) ."' height='15' width='15'></a>";
				}

				echo "</td><td>{$project->process_date}</td><td>{$project->ProjectName}</td><td align='center'>{$retainer}</td><td align='center'>{$project->MaxHours}</td><td align='center'>{$active}</td><td>{$project->po_number}</td><td align='center'>{$close }</td><td align='center'>{$project->hours_included}</td><td align='center'>{$project->hourly_rate}</td></tr>";
			}
			echo "</table>";
		} else {
			echo "No changes queued";
		}

	}

	function SaveExistingProject() {
		if ($this->ValidateProject(false)==false) {
			return;
		}
		global $wpdb;
		$user = wp_get_current_user();
		$common = new time_sheets_common();

		$db = new time_sheets_db();
		$options = get_option('time_sheets');

		if (isset($_GET['IsRetainer'])) {
			$IsRetainer=1;
		} else {
			$IsRetainer=0;
		}

		if (isset($_GET['MaxHours'])) {
			$MaxHours = intval($_GET['MaxHours']);
		} else {
			$MaxHours = 0;
		}

		if (isset($_GET['Active'])) {
			$Active = 1;
		} else {
			$Active = 0;
		}

		if (isset($_GET['BillOnProjectCompletion'])) {
			$BillOnProjectCompletion = 1;
		} else {
			$BillOnProjectCompletion = 0;
		}


		if (isset($_GET['flat_rate'])) {
			$flat_rate = 1;
		} else {
			$flat_rate = 0;
		}
		
		if (isset($_GET['close_on_completion']) && $_GET['close_on_completion']==1) {
			$close_on_completion = "1";
		} else {
			$close_on_completion = "0";
		}

		if (isset($options['require_unique_project_names'])) {
			$msg = $this->check_unique_project_name (intval($_GET['ClientId']), intval($_GET['ProjectId']), sanitize_text_field($_GET['ProjectName']));
			if ($msg <> '') {
				echo $msg;
				return;
			}
		}

		
		if (isset($_GET['process_date']) && $_GET['process_date'] != "") {

			if ($_GET['process_date'] < date('Y-m-d H:i:s')) {
				echo '<div class="notice notice-error is-dismissible"><p>Project change canceled. Future date required.</p></div>';
			} else {

				$sql = "insert into {$wpdb->prefix}timesheet_client_projects_changequeue
					(process_date, ProjectId, ClientId, ProjectName, IsRetainer, MaxHours, Active, notes, BillOnProjectCompletion,
						flat_rate, po_number, sales_person_id, technical_sales_person_id, close_on_completion,
						retainer_id, hours_included, hourly_rate, is_processed, is_deleted, max_monthly_bucket)
					values
					(%s, %d, %d, %s, %d, %d, %d, %s, %d, %d, %s, %d, %d, %d, %d, %d, %d, 0, 0, %d)";

				$parms = array(sanitize_text_field($_GET['process_date']), intval($_GET['ProjectId']), intval($_GET['ClientId']), 
					sanitize_text_field($_GET['ProjectName']), $IsRetainer, $MaxHours, $Active, 
					sanitize_textarea_field($_GET['notes']), $BillOnProjectCompletion, $flat_rate, 
					sanitize_text_field($_GET['po_number']), intval($_GET['sales_person_id']), 
					intval($_GET['technical_sales_person_id']), $close_on_completion, intval($_GET['retainer_id']), 
					intval($_GET['hours_included']), intval($_GET['hourly_rate']), intval($_GET['max_monthly_budget']) );
				$db->query($sql, $parms);
				
				if (isset($options['allow_money_based_retainers'])) {
					$sql = "select max(queue_id) queue_id from {$wpdb->prefix}timesheet_client_projects_changequeue where ProjectId = %d and process_date = %s";
					$parms = array(intval($_GET['ProjectId']), $_GET['process_date']);
					$queue = $db->get_row($sql, $parms);
					
					if ($queue) {
						$sql = "select * from {$wpdb->prefix}timesheet_employee_titles";
						$titles = $db->get_results($sql);
						if ($titles) {
							foreach ($titles as $title) {
								$project_id = intval($_GET['ProjectId']);
								
								$sql = "insert into {$wpdb->prefix}timesheet_project_employee_titles_changequeue
								(queue_id, project_id, title_id, hourly_rate)
								values
								(%d, %d, %d, %d)";
								$parms = array($queue->queue_id, $project_id, $title->title_id, intval($_GET['title_'.$title->title_id]));
								$db->query($sql, $parms);
								
								$common->archive_records ('timesheet_project_employee_titles_changequeue', 'timesheet_project_employee_titles_change_archive', "queue_id = {$queue->queue_id} and project_id = {$project_id} and title_id", $title->title_id, '=', 'INSERTED');
							}
						}
					}
					
				}
	
				echo '<div class="notice notice-success is-dismissible"><p>Project change queued.</p></div>';
			}
		} else {
			if (isset($_GET['IsRetainer'])) {
				$sql = "select MaxHours from {$wpdb->prefix}timesheet_client_projects where ProjectId = %d";
				$pamrs = array(intval($_GET['ProjectId']));
				$project = $db->get_row($sql, $pamrs);
				
				$MaxHours = $project->MaxHours;
			}
			
			$sql = "UPDATE {$wpdb->prefix}timesheet_client_projects
				SET ClientId=%d, 
					ProjectName=%s, 
					IsRetainer=%d, 
					MaxHours=%d, 
					Active=%d,
					Notes=%s,
					BillOnProjectCompletion=%d,
					flat_rate = %d,
					po_number = %s,
					sales_person_id = %d,
					technical_sales_person_id = %d,
					close_on_completion = %d,
					retainer_id = %d,
					hours_included = %d,
					hourly_rate = %d,
					max_monthly_bucket = %d
				WHERE ProjectId=%d";

			$parms = array(intval($_GET['ClientId']), sanitize_text_field($_GET['ProjectName']), $IsRetainer, $MaxHours, $Active, sanitize_textarea_field($_GET['notes']), $BillOnProjectCompletion, $flat_rate, sanitize_text_field($_GET['po_number']), intval($_GET['sales_person_id']), intval($_GET['technical_sales_person_id']), $close_on_completion, isset($_GET['retainer_id'])?intval($_GET['retainer_id']):0, isset($_GET['hours_included'])?intval($_GET['hours_included']):0, isset($_GET['hourly_rate'])?intval($_GET['hourly_rate']):0, isset($_GET['max_monthly_budget'])?intval($_GET['max_monthly_budget']):0, intval($_GET['ProjectId']) );
			$db->query($sql, $parms);

			$common->archive_records ('timesheet_client_projects', 'timesheet_client_projects_archive', 'ProjectId', intval($_GET['ProjectId']), '=', 'UPDATED');
			
			if (isset($options['allow_money_based_retainers'])) {
				$this->write_title_rates(intval($_GET['ProjectId']));
			}
		
			echo '<div class="notice notice-success is-dismissible"><p>Project updated.</p></div>';

		}
		
		if ($close_on_completion == 0) {
			$sql = "delete from {$wpdb->prefix}timesheet_client_project_autoclose where project_id = %d";
			$parms = array(intval($_GET['ProjectId']));
			
			$db->query($sql, $parms);
			
			echo '<div class="notice notice-info is-dismissible"><p>Any queued close requests of this project has been deleted.</p></div>';
		}
		
	}
	
	function write_title_rates($project_id) {
		
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		
		$sql = "select * from {$wpdb->prefix}timesheet_employee_titles";
		$titles = $db->get_results($sql);
		
		$common->archive_records ('timesheet_project_employee_titles', 'timesheet_project_employee_titles_archive', "project_id", intval($project_id), '=', 'DELETED');
		
		$sql = "delete from {$wpdb->prefix}timesheet_project_employee_titles where project_id = %d";
		$parms = array(intval($project_id));
		$db->query($sql, $parms);
		
		if ($titles && isset($_GET['IsRetainer'])) {
			
			foreach ($titles as $title) {
				if (!isset($_GET['title_'.$title->title_id])) {
					$hourly_rate = 0;
				} else {
					$hourly_rate = $_GET['title_'.$title->title_id];
				}
				$sql = "insert into {$wpdb->prefix}timesheet_project_employee_titles
								(project_id, title_id, hourly_rate)
								values
								(%d, %d, %d)";
				$parms = array(intval($project_id), $title->title_id, $hourly_rate);
				$db->query($sql, $parms);
				
				$common->archive_records ('timesheet_project_employee_titles', 'timesheet_project_employee_titles_archive', "title_id = {$title->title_id} and project_id", intval($project_id), '=', 'INSERTED');
			}
		}
	}

	function GetClient() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$common = new time_sheets_common();


		$clients = $db->get_results("
					select ClientName, tc1.ClientId
					from {$wpdb->prefix}timesheet_clients tc1
					order by ClientName");

		echo "<select name='ClientId' onclick='resetProject()'>";
		$client_id =isset($_GET['ClientId']) ? intval($_GET['ClientId']) : 0;
		if (isset($_POST['ClientId'])) {
			$client_id = $_POST['ClientId'];
		}
			foreach ($clients as $client) {
				echo "<option value='{$client->ClientId}'{$common->is_match($client->ClientId, $client_id, ' selected')}>{$client->ClientName}</option>";
			}
			echo "</select>";
	}

	function GetProjects() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;	
		$common = new time_sheets_common();


		$projects = $db->get_results("select tcp.ClientId, ProjectId, concat(ProjectName, case when Active = 0 then ' (Inactive)' else ' (Active)' end) as ProjectName from {$wpdb->prefix}timesheet_client_projects tcp order by Active desc, ProjectName");

		foreach ($projects as $project) {
			$project->ProjectName = $common->clean_from_db($project->ProjectName);
			$clean_projects[] = $project;
		}


		$js_projects = json_encode($clean_projects);

		echo "<script>
function resetProject(){
	var projectlist = {$js_projects};
	editproject.ProjectId.options.length = 0;

	var numberOfProjects = projectlist.length;
	//alert(numberOfProjects);
	for (var i = 0; i < numberOfProjects; i++) {
		project = projectlist[i];
		if (project['ClientId']==editproject.ClientId.value) {
			var opt = document.createElement('option');
			opt.value = project['ProjectId'];
			opt.innerHTML = project['ProjectName'];
			editproject.ProjectId.appendChild(opt);
		}
	  //alert(project['ProjectName']);
	}";

	if (isset($_GET['ProjectId'])) {
		echo "
	editproject.ProjectId.value={$_GET['ProjectId']};";
	}
	echo "
}
		</script>";

		
	}

}