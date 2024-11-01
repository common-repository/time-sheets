<?php
class time_sheets_queue_invoice {
	function closed_timesheet() {
		$this->closed_timesheet_menu();
		
		if (isset($_POST['filled_out'])) {
			$this->closed_timesheet_search();
		}
	}
	function closed_timesheet_search() {
		global $wpdb;
		$db = new time_sheets_db();
		$common = new time_sheets_common();
		
		$start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : -2;
		$end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : "";
		$invoiced_start_date = isset($_POST['invoiced_start_date']) ? sanitize_text_field($_POST['invoiced_start_date']) : "";
		$invoiced_end_date = isset($_POST['invoiced_end_date']) ? sanitize_text_field($_POST['invoiced_end_date']) : "";
		
		
		$ClientId = isset($_POST['ClientId']) ? intval($_POST['ClientId']) : "";
		$ProjectId = isset($_POST['ProjectId']) ? intval($_POST['ProjectId']) : "";
		$invoice_number = isset($_POST['invoice_number']) ? intval($_POST['invoice_number']) : "";
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : "";
		
		$allow_invoice_comment_snipit = get_user_option('allow_invoice_comment_snipit', $user_id);
		
		$options = get_option('time_sheets');
		
		$Projects = $common->return_projects_for_user(-1);
		
		if ($ClientId == -2) {
			unset($ClientId);
		}
		if ($ProjectId == -2) {
			unset($ProjectId);
		}
		
		$sql = "select t.timesheet_id, u.user_login, t.start_date, c.ClientName client_name, cp.ProjectName project_name, t.invoiceid, cp.notes, u.display_name, cp.po_number,
date_add(t.start_date, INTERVAL CASE WHEN monday_hours != 0 then 0 when tuesday_hours != 0 then 1 when wednesday_hours != 0 then 2 when thursday_hours != 0 then 3 when friday_hours != 0 then 4 when saturday_hours != 0 then 5 when sunday_hours != 0 then 6 else 0 end DAY) first_billed_day, 
			case when cs.display_name   is null then 'None' else cs.display_name   end cs_display_name, 
			case when cps.display_name  is null then 'None' else cps.display_name  end cps_display_name, 
			case when tcs.display_name  is null then 'None' else tcs.display_name  end tcs_display_name, 
			case when tcps.display_name is null then 'None' else tcps.display_name end tcps_display_name, 
			t.total_hours, t.invoiceid, t.approved, t.marked_complete_by, t.other_expenses_notes
		from {$wpdb->prefix}timesheet t
		JOIN {$wpdb->prefix}timesheet_client_projects cp ON t.ProjectId = cp.ProjectId
		JOIN {$wpdb->prefix}timesheet_clients c ON cp.ClientId = c.ClientId
		left outer join {$wpdb->users} cs on c.sales_person_id = cs.ID
		left outer join {$wpdb->users} cps on cp.sales_person_id = cps.ID
		left outer join {$wpdb->users} tcs on c.technical_sales_person_id = tcs.ID
		left outer join {$wpdb->users} tcps on cp.technical_sales_person_id = tcps.ID
		join {$wpdb->users} u on t.user_id = u.ID
		where approved = 1
			and t.invoiced = 1";
		
		if ($start_date) {
			$sql = $sql . " and t.start_date > '{$start_date}'";
		}
		if ($end_date) {
			$sql = $sql . " and t.start_date <= '{$end_date}'";
		}
		if ($invoiced_start_date) {
			$sql = $sql . " and t.invoiced_date > '{$invoiced_start_date}'";
		}
		if ($invoiced_end_date) {
			$sql = $sql . " and t.invoiced_date <= '{$invoiced_end_date}'";
		}
		if (isset($ClientId)) {
			$sql = $sql . " and c.ClientId = {$ClientId}";
		}
		if ($ProjectId) {
			$sql = $sql . " and t.ProjectId = {$ProjectId}";
		}
		if ($invoice_number) {
			$sql = $sql . " and t.invoiceid = {$invoice_number}";
		}
		if ($user_id) {
			$sql = $sql . " and t.user_id = {$user_id}";
		}
		
		$timesheets = $db->get_results($sql);
		
		if (!$timesheets) {
			echo "<div class='notice notice-info is-dismissible'><p>No matching time sheets found.</p></div>";
		}
		
		echo "<table border='1' cellspacing='0' width='100%'><tr><td align='center'>Timesheet</td><td align='center'>View</td><td align='center'>User</td><td align='center'>Approved</td><td align='center'>Week Completed</td><td align='center'>Invoice Number</td><td align='center'>Start Date</td><td align='center'>First Billed Date</td>";
		if ((isset($options['hide_client_project']) && sizeof($Projects) != 1) || !isset($options['hide_client_project'])) {
			echo "<td align='center'>Client</td><td align='center'>Project Name</td>";
		}
		echo "<td align='center'>Total Hours</td><td align='center'>Sales Person</td><td align='center'>Technical Sales Person</td>";
		if ($allow_invoice_comment_snipit) {
			echo "<td>Notes</td>";
		}
		echo "</tr>";
			foreach ($timesheets as $timesheet) {
				echo "<td align='center'>{$timesheet->timesheet_id}</td>
					<td align='center'><a href='./admin.php?page=invoice_timesheet&timesheet_id={$timesheet->timesheet_id}&action=preinvoice' target='_new'><img src='". plugins_url( 'view.png' , __FILE__) ."' width='15' height='15'></a></td>
					<td>{$timesheet->display_name}</td><td align='center'>";
				if ($timesheet->approved==1) {
					echo "<img src='". plugins_url( 'check.png' , __FILE__) ."' height='15' width='15'>";
				} else {
					echo "&nbsp;";
				}
				echo "</td><td align='center'>";
				if ($timesheet->marked_complete_by) {
					echo "<img src='". plugins_url( 'check.png' , __FILE__) ."' height='15' width='15'>";
				} else {
					echo "&nbsp;";
				}
				echo "</td><td align='center'>{$timesheet->invoiceid}</td><td>{$common->f_date($timesheet->start_date)}</td><td>{$common->f_date($timesheet->first_billed_day)}</td>";
				if ((isset($options['hide_client_project']) && sizeof($Projects) != 1) || !isset($options['hide_client_project'])) {
					echo "<td>{$common->clean_from_db($timesheet->client_name)}</td><td>{$common->clean_from_db($timesheet->project_name)}</td>";
				}
				echo "<td>{$timesheet->total_hours}</td><td>";
				if ($options['sales_override']=='project') {
					if ($timesheet->cps_display_name) {
						echo $timesheet->cps_display_name;
						echo "</td><td>";
						echo $timesheet->tcps_display_name;
					} else {
						echo $timesheet->cs_display_name;
						echo "</td>";
						echo $timesheet->tcs_display_name;
					}
				} else {
					if ($timesheet->cs_display_name) {
						echo $timesheet->cs_display_name;
						echo "</td><td>";
						echo $timesheet->tcs_display_name;
					} else {
						echo $timesheet->cps_display_name;
						echo "</td>";
						echo $timesheet->tcps_display_name;
					}
				}
				if ($allow_invoice_comment_snipit) {
					echo "<td>";
					echo substr($common->esc_textarea($timesheet->other_expenses_notes), 0, 20);
					if (strlen($common->esc_textarea($timesheet->other_expenses_notes)) > 20) {
						echo " ...";
					}
					echo "</td>";
				}
				echo "</tr>";
			}
			echo "</table>";
	}
	function closed_timesheet_menu() {
		global $wpdb;
		
		$common = new time_sheets_common();
		$db = new time_sheets_db();
		
		$start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : "";
		$end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : "";
		
		$invoiced_start_date = isset($_POST['invoiced_start_date']) ? sanitize_text_field($_POST['invoiced_start_date']) : "";
		$invoiced_end_date = isset($_POST['invoiced_end_date']) ? sanitize_text_field($_POST['invoiced_end_date']) : "";
		
		
		$ClientId = isset($_POST['ClientId']) ? intval($_POST['ClientId']) : "";
		$ProjectId = isset($_POST['ClientId']) ? intval($_POST['ClientId']) : "";
		$invoice_number = isset($_POST['invoice_number']) ? sanitize_text_field($_POST['invoice_number']) : "";
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : "";
		
		$options = get_option('time_sheets');
		
		$Projects = $common->return_projects_for_user(-1);
	
		$users = $common->return_employee_list();
	
		if (!$ClientId) {
			$ClientId = -1;
		}

		if (!$ProjectId) {
			$ProjectId = -1;
		}
		
		echo "<h2>Search for Invoiced Time Sheets</h2>";
		echo "<form method='POST' class='ws-validate' name='search'><table>
		<tr><td>Week Starting From:</td><td><div class='form-row show-inputbtns'><input type='date' name='start_date' value='{$start_date}' data-date-inline-picker='false' data-date-open-on-focus='true' onChange='setupDates()' /></div></td>
		<td>To:</td><td><div class='form-row show-inputbtns'><input type='date' name='end_date' value='{$end_date}' data-date-inline-picker='false' data-date-open-on-focus='true' onChange='setupDates()' /></div></td></tr>
		<tr><td>Invoiced On From:</td><td><div class='form-row show-inputbtns'><input type='date' name='invoiced_start_date' value='{$invoiced_start_date}' data-date-inline-picker='false' data-date-open-on-focus='true' onChange='setupDates()' /></div></td>
		<td>To:</td><td><div class='form-row show-inputbtns'><input type='date' name='invoiced_end_date' value='{$invoiced_end_date}' data-date-inline-picker='false' data-date-open-on-focus='true' onChange='setupDates()' /></div></td></tr>";
		
		if ((isset($options['hide_client_project']) && sizeof($Projects) != 1) || !isset($options['hide_client_project'])){
			echo "<tr><td>Select Client</td><td colspan='3'>";
			$clients = $db->get_results("select ClientName, tc.ClientId, im.client_id IsMonthly
						from {$wpdb->prefix}timesheet_clients tc
						left outer join {$wpdb->prefix}timesheet_recurring_invoices_monthly im on tc.clientid = im.client_id
						order by ClientName");

				echo "<select name='ClientId'><option value='-2'>Select a client if needed</option>";
				foreach ($clients as $client) {
					echo "<option value='{$client->ClientId}'{$common->is_match($ClientId, $client->ClientId,' selected')}>{$client->ClientName}</option>";
				}
			echo "</select></td></tr>";
		}
		echo "<tr><td>Submitted By</td><td colspan='3'><SELECT name='user_id'><option value=''>No Employee Selected</option>";
		foreach ($users as $user) {
			echo "<option value='{$user->id}'{$common->is_match($user->id, $user_id, ' selected', 0)}>{$user->display_name}</option>";
		}
		
		
		echo "</select></td></tr><tr><td>Invoice Number</td><td colspan='3'><input type='text' name='invoice_number' value='{$invoice_number}' width='12'></td></tr>
		<tr><td colspan='4'><input type='submit' value='Search Timesheets' name='submit' class='button-primary'></td></tr>
		</table>
		<input type='hidden' name='filled_out' value='1'>
		</form>";
		
		
		
		echo "
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
";

		$common->client_and_projects_javascript('', 'search', 1, '');
		
	echo "</script>";
	}
	function show_invoicing_list() {
		global $wpdb;
		$db = new time_sheets_db();
		$main = new time_sheets_main();
		$queue_approval = new time_sheets_queue_approval();
		$approver = $queue_approval->employee_approver_check();
		$common = new time_sheets_common();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		
		$timesheets = $this->return_invoicing_list();

		$allow_show_record_invoicing = get_user_option('time_sheets_invoicing_allow_show_record_invoicing', $user_id);
		$currency_char = $options['currency_char'];
		
		$retainers_parm = "";
		if (isset($_GET['show_retainers'])) {
			$retainers_parm = "&show_retainers=checked";
		}
		if (isset($_GET['hide_nonretainers'])) {
			$retainers_parm = $retainers_parm . "&hide_nonretainers=checked";
		}

		if ($timesheets) {
			echo "<script type='text/javascript'>

			function check_all() {
				";

				foreach ($timesheets as $timesheet) {
					echo "document.getElementById('timesheet_{$timesheet->timesheet_id}').checked = true;
					";
				}
				echo "
			}
			</script>
			";
			

			echo "<table border='1' cellspacing='0'><tr><td>Timesheet</td>";
			if ($approver<>0) {
				echo "<td>Reject</td>";
			}
			echo "<td>View</td><td><a href='#' onclick='check_all()'>Completed</a></td><td>Invoice Number</td><td>User</td>";
			if (isset($options['allow_money_based_retainers'])) {
				echo "<TD>Users Title</TD>";
			}
			echo "<td>Start Date</td><td>First Billed Day</td><td>Last Billed Day</td><td>Client</td><td>Project Name</td><td>PO Number</td><td>Sales Person</td><td>Technical Sales Person</td><td>Hours</td><td>Expenses</td></tr>";
			foreach ($timesheets as $timesheet) {
				echo "<td align='center'>{$timesheet->timesheet_id}</td>";
				if ($approver<>0) {
					echo "<td align='center'><a href='./admin.php?page=invoice_timesheet&timesheet_id={$timesheet->timesheet_id}&action=reject{$retainers_parm}'><img src='". plugins_url( 'x.png' , __FILE__) ."' width='15' height='15'></a></td>";
				}
				$invoice_id = $timesheet->invoiceid;
				if ($invoice_id == 0) {
					$invoice_id = '';
				}
				echo "<td align='center'><a href='./admin.php?page=invoice_timesheet&timesheet_id={$timesheet->timesheet_id}&action=preinvoice' target='_blank'><img src='". plugins_url( 'view.png' , __FILE__) ."' width='15' height='15'></a></td><td align='center'><input type='checkbox' id='timesheet_{$timesheet->timesheet_id}' name='timesheet_{$timesheet->timesheet_id}' value=1></td><td align='center'><input type='text' name='timesheet_{$timesheet->timesheet_id}_invoice' value='{$invoice_id}' size='6' autocomplete='off'></td><td>{$common->replace(' ', '&nbsp;', $timesheet->display_name)}</td>";
				if (isset($options['allow_money_based_retainers'])) {
					echo "<TD><div style='white-space: nowrap'>";
					if (isset($timesheet->TitleNameOverride)) {
						echo $common->clean_from_db($timesheet->TitleNameOverride) . "<font color='red'>*</font>";
					} else {
						echo $common->clean_from_db($timesheet->TitleName);
					}
					echo "</div></TD>";
				}
				echo "<td>{$common->f_date($timesheet->start_date)}</td><td>{$common->f_date($timesheet->first_billed_day)}</td><td>{$common->f_date($timesheet->last_day_billed)}</td><td>{$common->clean_from_db($timesheet->client_name)}</td><td>{$common->clean_from_db($timesheet->project_name)}</td><td>{$common->clean_from_db($timesheet->po_number)}</td><td>";

				if ($options['sales_override']=='project') {
					if ($timesheet->cps_display_name) {
						echo $timesheet->cps_display_name;
						echo "</td><td>";
						echo $timesheet->tcps_display_name;
					} else {
						echo $timesheet->cs_display_name;
						echo "</td><td>";
						echo $timesheet->tcs_display_name;
					}
				} else {
					if ($timesheet->cs_display_name) {
						echo $timesheet->cs_display_name;
						echo "</td><td>";
						echo $timesheet->tcs_display_name;
					} else {
						echo $timesheet->cps_display_name;
						echo "</td><td>";
						echo $timesheet->tcps_display_name;
					}
				}
				
				$total_hours = $timesheet->total_hours;
				$total_hours = number_format($total_hours, 2);
				if (is_numeric($timesheet->expenses)) {
					$total_expenses = $currency_char . number_format($timesheet->expenses, 2);
				} else {
					$total_expenses = "";
				}
				echo "</td><td>{$total_hours}</td><TD>{$total_expenses}</TD></TR>";
			}
			echo "</table><br><input type='submit' name='submit' value='Record Invoicing' class='button-primary'><input type='hidden' name='page' value='invoice_timesheet'>";
			if (isset($_GET['show_retainers'])) {
				echo "<input type='hidden' name='show_retainers' value='{$_GET['show_retainers']}'>";
			}
			if (isset($_GET['hide_nonretainers'])) {
				echo "<input type='hidden' name='hide_nonretainers' value='{$_GET['hide_nonretainers']}'>";
			}

			wp_nonce_field( 'timesheet_invoice_processing' );

			if (isset($_GET['retainer_type'])) {
				$retainer_type = $_GET['retainer_type'];
			} else {
				$retainer_type = "";
			}

			echo "<input type='hidden' name='action' value='approve'>
			      <input type='hidden' name='retainer_type' value='{$retainer_type}'></form>";
			if (isset($options['allow_money_based_retainers'])) {
				echo "<p><font size=-1>NOTE: Any titles which are being overriden for a specific project, will be marked with a <font color='red'>*</font></font></p>";
			}
		} else {
			echo "<div class='notice notice-info is-dismissible'<p>No time sheets to invoice.</p></div>";
		}
	}

	function invoice_timesheet(){
		global $wpdb;
		$entry = new time_sheets_entry();
		$common = new time_sheets_common();
		$db = new time_sheets_db();

		$user = wp_get_current_user();
		$user_id = $user->ID;

		$allow_show_record_invoicing = get_user_option('time_sheets_invoicing_allow_show_record_invoicing', $user_id);
		if (isset($_GET['retainer_type'])) {
			$retainer_type=intval($_GET['retainer_type']);
		} else {
			$retainer_type=0;
		}

		if (isset($_GET['action']) && $_GET['action']=='preinvoice') {
			$entry->show_timesheet();
		} else {
			$show_retainers =  isset($_GET['show_retainers']) ? " checked" : "";
			$hide_nonretainers = isset($_GET['hide_nonretainers']) ? " checked" : "";

			$sql = "select retainer_name, retainer_id from {$wpdb->prefix}timesheet_custom_retainer_frequency where active = 1";
			$frequencies = $db->get_results($sql);


			echo "<br><form name='show_filter'>";
			echo "<input type='hidden' name='page' value='invoice_timesheet'>";
			echo "<table>";
			echo "<tr><td><input type='checkbox' name='show_retainers' value='checked' {$show_retainers} onclick='enable_retainer_type();'> Show Retainers</td></tr>";
			echo "<tr><td>
				<select name='retainer_type'>
					<option value='0'{$common->is_match('0', $retainer_type, ' selected')}>--All</option>
					<option value='2'{$common->is_match('2', $retainer_type, ' selected')}>Weekly</option>
					<option value='1'{$common->is_match('1', $retainer_type, ' selected')}>Monthly</option>
					<option value='3'{$common->is_match('3', $retainer_type, ' selected')}>Quarterly</option>";
				if ($frequencies) {
					foreach ($frequencies as $freq) {
						echo "<option value='{$freq->retainer_id}'{$common->is_match($freq->retainer_id, $retainer_type, ' selected')}>{$freq->retainer_name}</option>";
					}
				}

				echo "</select>
				</td></tr>";
			echo "<tr><td><input type='checkbox' name='hide_nonretainers' value='checked' {$hide_nonretainers}> Hide Non-Retainers</td></tr>";

			echo "<tr><td><input type='submit' name='submit' value='Filter List' class='button-primary'></td></tr></table></form>";

			echo "<form name='approve_timesheets' method='POST'>";

			if ($allow_show_record_invoicing != '') {
				echo "<input type='submit' name='submit2' value='Record Invoicing' class='button-primary'>";
			} else {
				#if (count($timesheets) >= 15) {
					echo "<input type='submit' name='submit2' value='Record Invoicing' class='button-primary'>";
				#}
			}

			echo "<br><table border='0'><tr><td valign='top'>";


			$this->show_invoicing_list();
			echo "</td><td valign='top'>";
			$common->show_clients_on_retainer();
			echo "</td></tr></table>

			<script>
				function enable_retainer_type() {
					if (show_filter.show_retainers.checked==true) {
						show_filter.retainer_type.disabled=false;
					} else {
						show_filter.retainer_type.disabled=true;
					}
				}
				enable_retainer_type();
			</script>
";
		}
	}

	function do_timesheet_rejectinvoicing() {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$timesheet_id = intval($_GET['timesheet_id']);
		$common = new time_sheets_common();

		$options = get_option('time_sheets');
		if ($options['queue_order']=='parallel') {
			echo '<div class="notice notice-warning is-dismissible"><p>This timesheet may be in the payroll queue and may need to be adjusted.</p></div><br>';
		}

			$sql = "update {$wpdb->prefix}timesheet
				set approved_by=NULL,
					approved_date = NULL,
					approved=0
				where timesheet_id=%d";
			$params=array(intval($_GET['timesheet_id']));
			$db->query($sql, $params);
		
			$common->archive_records ('timesheet', 'timesheet_archive', "timesheet_id", intval($_GET['timesheet_id']), '=', 'UPDATED');	
	
		echo "<div class='notice notice-success is-dismissible'><p>Timesheet {$timesheet_id}has been rejected.</p></div>";
	}

	function return_invoicing_list() {
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();
		$options = get_option('time_sheets');

		$IsRetainer = "-1";
		if (isset($_GET['show_retainers'])) {
			$IsRetainer = $IsRetainer . ", 1";
		}
		if (!isset($_GET['hide_nonretainers'])) {
			$IsRetainer = $IsRetainer . ", 0";
		}

		$primary_sort_col = get_user_option('time_sheets_invoicing_primary_sort_col', $user_id);
		$primary_sort_order = get_user_option('time_sheets_invoicing_primary_sort_order', $user_id);
		$secondary_sort_col = get_user_option('time_sheets_invoicing_secondary_sort_col', $user_id);
		$secondary_sort_order = get_user_option('time_sheets_invoicing_secondary_sort_order', $user_id);
		$tertiary_sort_col = get_user_option('time_sheets_invoicing_tertiary_sort_col', $user_id);
		$tertiary_sort_order = get_user_option('time_sheets_invoicing_tertiary_sort_order', $user_id);

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
		if ($tertiary_sort_order=='') {
			$tertiary_sort_order='asc';
		}

		if (!isset($_GET['retainer_type'])) {
			$retainer_type = "";
		}elseif ($_GET['retainer_type']==0) { //All
			$retainer_type=" and cp.retainer_id IS NOT NULL";
		}elseif ($_GET['retainer_type']==2) { //Weekly
			$retainer_type=" and cp.retainer_id = 2";
		}elseif ($_GET['retainer_type']==1) { //monthly
			$retainer_type=" and cp.retainer_id = 1";
		}elseif ($_GET['retainer_type']==3) { //Quarterly
			$retainer_type=" and cp.retainer_id = 3";
		}else {
			$retainer_type = " and cp.retainer_id = " . intval($_GET['retainer_type']);
		}
		

		$sql = "select t.timesheet_id, u.user_login, t.start_date, c.ClientName client_name, cp.ProjectName project_name, t.invoiceid, cp.notes, u.display_name, cp.po_number,
date_add(t.start_date, INTERVAL CASE WHEN monday_hours != 0 then 0 when tuesday_hours != 0 then 1 when wednesday_hours != 0 then 2 when thursday_hours != 0 then 3 when friday_hours != 0 then 4 when saturday_hours != 0 then 5 when sunday_hours != 0 then 6 else 0 end DAY) first_billed_day, 
			case when cs.display_name   is null then 'None' else cs.display_name   end cs_display_name, 
			case when cps.display_name  is null then 'None' else cps.display_name  end cps_display_name, 
			case when tcs.display_name  is null then 'None' else tcs.display_name  end tcs_display_name, 
			case when tcps.display_name is null then 'None' else tcps.display_name end tcps_display_name, 
			date_add(t.start_date, INTERVAL CASE  WHEN sunday_hours != 0 then 6 when saturday_hours != 0 then 5 when friday_hours != 0 then 4 when thursday_hours != 0 then 3 when wednesday_hours != 0 then 2 when tuesday_hours != 0 then 1 when monday_hours != 0 then 0 else 0 end DAY) last_day_billed, total_hours, (hotel_charges+rental_car_charges+tolls+other_expenses+taxi) as expenses";
		if (isset($options['allow_money_based_retainers'])) {
			$sql = $sql . ", et.name as 'TitleName', et2.name as 'TitleNameOverride'";	
		}
		$sql = $sql . "
		from {$wpdb->prefix}timesheet t
		JOIN {$wpdb->prefix}timesheet_clients c ON t.ClientId = c.ClientId
		JOIN {$wpdb->prefix}timesheet_client_projects cp ON t.ProjectId = cp.ProjectId
		left outer join {$wpdb->users} cs on c.sales_person_id = cs.ID
		left outer join {$wpdb->users} cps on cp.sales_person_id = cps.ID
		left outer join {$wpdb->users} tcs on c.technical_sales_person_id = tcs.ID
		left outer join {$wpdb->users} tcps on cp.technical_sales_person_id = tcps.ID
		join {$wpdb->users} u on t.user_id = u.ID
		";
		if (isset($options['allow_money_based_retainers']) ){
			$sql = $sql . "left outer join {$wpdb->prefix}timesheet_employee_title_join etj on u.ID = etj.user_id
			left outer join {$wpdb->prefix}timesheet_customer_project_employee_title_override peto on peto.user_id = u.ID
				and peto.project_id = t.ProjectId
			left outer join {$wpdb->prefix}timesheet_employee_titles et on etj.title_id = et.title_id
			left outer join {$wpdb->prefix}timesheet_employee_titles et2 on peto.title_id = et2.title_id";
		}
		
		$sql = $sql . "
		where t.invoiced = 0 
			and approved = 1
			and cp.ProjectId in (select ProjectId from {$wpdb->prefix}timesheet_client_projects where IsRetainer in ({$IsRetainer})) 
			{$retainer_type}
		order by {$primary_sort_col} {$primary_sort_order}, {$secondary_sort_col} {$secondary_sort_order}, {$tertiary_sort_col} {$tertiary_sort_order}  ";

		$timesheets = $db->get_results($sql);

		return $timesheets;
	}

	function should_be_payrolled($timesheet_id) {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');

		$sql = "select * from {$wpdb->prefix}timesheet where timesheet_id = {$timesheet_id}";
		$timesheet = $db->get_row($sql);


		$sql = "select * from {$wpdb->prefix}timesheet_employee_always_to_payroll where user_id = {$timesheet->user_id}";
		$always_payrol = $db->get_row($sql);

		$payrolled = 1; //Default payrolled to 1, reset to 0 if needed

		if (isset($always_payrol)) {
			$payrolled = 0;
		}

		if (isset($options['mileage']) && $timesheet->mileage!=0) {
			$payrolled = 0;
		}
		if (isset($options['per_diem']) && $timesheet->per_diem_days != 0) {
			$payrolled = 0;
		}
		if (isset($options['flight_cost']) && $timesheet->flight_cost != 0) {
			$payrolled = 0;
		}
		if (isset($options['hotel']) && $timesheet->hotel_charges != 0) {
			$payrolled = 0;
		}
		if (isset($options['rental_car']) && $timesheet->rental_car_charges !=0) {
			$payrolled = 0;
		}
		if (isset($options['taxi']) && $timesheet->taxi!=0) {
			$payrolled = 0;
		}
		if (isset($options['tolls']) && $timesheet->tolls!=0) {
			$payrolled = 0;
		}
		if (isset($options['other_expenses']) && $timesheet->other_expenses!=0) {
			$payrolled = 0;
		}

		return $payrolled;
	}

	function do_timesheet_invoicing_one_timesheet($timesheet_id, $invoiced, $invoiced_by, $invoice_id) {
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');
		$common = new time_sheets_common();


		if ($options['queue_order']!='parallel' && $invoiced==1) {
			$payrolled = $this->should_be_payrolled($timesheet_id);
		
			$sql = "update {$wpdb->prefix}timesheet
			set payrolled = {$payrolled}
			where timesheet_id = {$timesheet_id}";
			$db->query($sql);
		}
		
		$sql = "update {$wpdb->prefix}timesheet
		set invoiced= {$invoiced},
			invoiced_by = {$user_id},
			invoiced_date = CURDATE(),
			invoiceid = {$invoice_id}
		where timesheet_id = {$timesheet_id}";

		$db->query($sql);
		
		$common->archive_records ('timesheet', 'timesheet_archive', "timesheet_id", $timesheet_id, '=', 'UPDATED');	

	}

	function do_timesheet_invoicing(){
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$options = get_option('time_sheets');

		check_admin_referer( 'timesheet_invoice_processing' );

		$timesheets = $this->return_invoicing_list();

		foreach ($timesheets as $timesheet) {
			$valuename = "timesheet_{$timesheet->timesheet_id}_invoice";
			$invoiceid = intval($_POST[$valuename]);

			$valuename = "timesheet_{$timesheet->timesheet_id}";

			if (isset($_POST[$valuename]) && $_POST[$valuename] == 1) {
				$invoiced = 1;
				
			} else {
				$invoiced = 0;
			}
			
			$this->do_timesheet_invoicing_one_timesheet($timesheet->timesheet_id, $invoiced, $user_id, $invoiceid);

		}



		echo '<div class="notice notice-success is-dismissible"><p>Timesheets have been updated.</p></div>';
	}

	function count_pending_invoice() {
		global $wpdb;
		global $vcount_pending_invoicing;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();
		
		if (!isset($vcount_pending_invoicing)) {

			$sql = "select sum(case when tcp.IsRetainer=1 then 1 else 0 end) Retainer, sum(case when tcp.IsRetainer=0 then 1 else 0 end) NotRetainer
				from {$wpdb->prefix}timesheet t
				join {$wpdb->prefix}timesheet_client_projects tcp on t.ProjectId = tcp.ProjectId
				where t.approved = 1 and t.invoiced = 0";

			$count = $db->get_row($sql);
			
			$vcount_pending_invoicing = $count;
			
		} else {
			$count = $vcount_pending_invoicing;
		}
		
		return $count;
	}

	function count_users_open_invoice() {
		global $wpdb;
		
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();

		$sql = "select count(*) 
			from {$wpdb->prefix}timesheet t
			join {$wpdb->prefix}timesheet_client_projects tcp on t.ProjectId = tcp.ProjectId
			where t.week_complete = 0 and t.user_id = {$user_id}";

		$count = $db->get_var($sql);
		
		return $count;
	}

	function employee_invoicer_check () {
		global $wpdb;
		global $invoicer_count;
			
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();

		if (!isset($invoicer_count)) {
			$sql = "select count(*) 
				from {$wpdb->prefix}timesheet_invoicers t
				where user_id = {$user_id}";

			$count = $db->get_var($sql);
			
			$invoicer_count = $count;
		} else {
			$count = $invoicer_count;
		}
		
		return $count;
	}

}
