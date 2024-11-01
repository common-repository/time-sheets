<?php
class time_sheets_widget {
	function show_widget() {
		$queue_payroll = new time_sheets_queue_payroll();
		$queue_invoice = new time_sheets_queue_invoice();
		$queue_approval = new time_sheets_queue_approval();

		if ($queue_approval->employee_approver_check()!=0) {
			$vcount_pending_approval = $queue_approval->count_pending_approval();
		} 
		if ($queue_invoice->employee_invoicer_check()!=0) {
			$vcount_pending_invoice = $queue_invoice->count_pending_invoice();
		} 
		if ($queue_payroll->employee_payroll_check()!=0) {
			$vcount_pending_payroll = $queue_payroll->count_pending_payroll();
		} 
		
		$vcount_open_invoices = $queue_invoice->count_users_open_invoice();
		$dashboard_url = admin_url('admin.php?page=search_timesheet');
		$timesheet_url = admin_url('admin.php?page=show_timesheet');
		
		$page = "<table class='wp-list-table widefat fixed striped'>
			<thead>
				<th>
					Item
				</th>
				<th>
					Count
				</th>
			</thead>
			<tr>
				<td>
					<a href='{$dashboard_url}'>Open Timehseets</a>
				</td>
				<td>
					<a href='{$dashboard_url}'>{$vcount_open_invoices}</a>
				</td>
			</tr>";
	
		if (isset($vcount_pending_approval )) {
			if ($vcount_pending_approval->NotRetainer != 0 || $vcount_pending_approval->Retainer != 0 || $vcount_pending_approval->Embargoed != 0) {
				$approve_url = admin_url('admin.php?page=approve_timesheet');
				$page = $page . "<tr>
					<td>
						<a href='{$approve_url}'>Pending Approval</a>
					</td>
					<td>
						<a href='{$approve_url}'>{$vcount_pending_approval->NotRetainer}</a> / <a href='{$approve_url}&show_retainers=checked&hide_nonretainers=checked'>{$vcount_pending_approval->Retainer}</a>";
						if ($vcount_pending_approval->Embargoed != 0) {
							$page = $page . " / <a href='{$approve_url}&show_embargoed=checked'>{$vcount_pending_approval->Embargoed}</a>";
						}
					echo "</td>
				</tr>";
			}
		}
		
		if (isset($vcount_pending_invoice)) {
			if ($vcount_pending_invoice->NotRetainer != 0 || $vcount_pending_invoice->Retainer != 0) {
				$invoice_url = admin_url('admin.php?page=invoice_timesheet');
				$page = $page . "<tr>
					<td>
						<a href='{$invoice_url}'>Pending Invoicing</a>
					</td>
					<td>
						<a href='{$invoice_url}'>{$vcount_pending_invoice->NotRetainer}</a> / <a href='{$invoice_url}&show_retainers=checked&retainer_type=0&hide_nonretainers=checked'>{$vcount_pending_invoice->Retainer}</a>

					</td>
				</tr>";
			}
		}
		
		if (isset($vcount_pending_payroll)) {
			if ($vcount_pending_payroll != 0) {
				$payroll_url = admin_url('admin.php?page=payroll_timesheet');
				$page = $page . "<tr>
					<td>
						<a href='{$payroll_url}'>Pending Payroll</a>
					</td>
					<td>
						<a href='{$payroll_url}'>{$vcount_pending_payroll}</a>
					</td>
				</tr>";
			}
		}
		$page = $page . "</table>";
		

		$page = $page . "<div class='sub'><a href='$timesheet_url'>Create Timesheet</a>&nbsp;&nbsp;&nbsp;<a href='{$dashboard_url}'>Timesheet Dashboard</a></div>
		";
		echo $page;
	}
}