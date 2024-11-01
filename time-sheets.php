<?php
/*
Plugin Name: Time Sheets
Version: 2.1.3
Plugin URI: https://www.dcac.com/go/time-sheets
Description: Time Sheets application
Author: Denny Cherry & Associates Consulting
Author URI: https://www.dcac.com/
*/

require_once dirname( __FILE__ ) .'/common.php';
require_once dirname( __FILE__ ) .'/setup.php';
require_once dirname( __FILE__ ) .'/entry.php';
require_once dirname( __FILE__ ) .'/settings.php';
require_once dirname( __FILE__ ) .'/db.php';
require_once dirname( __FILE__ ) .'/cron.php';
require_once dirname( __FILE__ ) .'/manage-projects.php';
require_once dirname( __FILE__ ) .'/mysettings.php';
require_once dirname( __FILE__ ) .'/manage-client-approvers.php';
require_once dirname( __FILE__ ) .'/my_dashboard.php';
require_once dirname( __FILE__ ) .'/docs.php';
require_once dirname( __FILE__ ) .'/widget.php';

require_once dirname( __FILE__ ) .'/queue-payroll.php';
require_once dirname( __FILE__ ) .'/queue-invoice.php';
require_once dirname( __FILE__ ) .'/queue-approval.php';

$plugins_url = plugins_url();
$base_url = get_option( 'siteurl' );
$plugins_dir = str_replace( $base_url, ABSPATH, $plugins_url );

$folder = $plugins_dir .'/time-sheets-modules' ;
$filename = "{$folder}/custom.php";

$vcount_pending_approval = 0;
$vcount_pending_invoice = 0;
$vcount_pending_payroll = 0;

if (file_exists($filename)) {
	DEFINE('time_sheet_custom', 'yes');
	require_once $filename;
}

class time_sheets_main {

	function activation() {

		// Default options
		$options = array (
			'from_email' => '',
			'from_name' => '',
			'email_enabled' => '',
			'hide_dcac_ad' => '',
			'email_late_timesheets' => 'checked',
			'override_date_format' => 'system_defined'
		);

		// Add options
		$option = get_option('time_sheets');
		if (!$option) {
			add_option('time_sheets', $options);
		}

		$setup = new time_sheets_setup();
		$setup->create_db_objects();

		$cron = new time_sheets_cron();
		$cron->add_cron();

	 }

	function deactivation() {
		//delete_option('time_sheets');
		$cron = new time_sheets_cron();
		$cron->remove_cron();
	}

	function upgrade() {
		$setup = new time_sheets_setup();
		$setup->create_db_objects();
		$options = get_option('time_sheets');

		$cron = new time_sheets_cron();
		$cron->remove_cron();
		$cron->add_cron();
	}

	function custom_menu() {
		$options = get_option('time_sheets');
		$time_sheets_main = new time_sheets_main();
		$time_sheets_entry = new time_sheets_entry();
		$time_sheets_settings = new time_sheets_settings();
		$docs = new time_sheets_docs();
		$time_sheets_manage_projects = new time_sheets_manage_projects();
		$mydashboard = new time_sheets_mydashboard();

		$queue_payroll = new time_sheets_queue_payroll();
		$queue_invoice = new time_sheets_queue_invoice();
		$queue_approval = new time_sheets_queue_approval();

		$client_managers = new time_sheets_client_managers();
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$my = new time_sheets_my_settings();
		$cron = new time_sheets_cron();

		global $vcount_pending_approval, $vcount_pending_invoicing, $vcount_pending_payroll;

		
		if ($queue_approval->employee_approver_check()!=0) {
			$vcount_pending_approval = $queue_approval->count_pending_approval();
		} else {
			$vcount_pending_approval = 0;
		}
		if ($queue_invoice->employee_invoicer_check()!=0) {
			$vcount_pending_invoice = $queue_invoice->count_pending_invoice();
		} else {
			$vcount_pending_invoice = 0;
		}
		if ($queue_payroll->employee_payroll_check()!=0) {
			$vcount_pending_payroll = $queue_payroll->count_pending_payroll();
		} else {
			$vcount_pending_payroll = 0;
		}

		$timesheets_not_complete = $queue_invoice->count_users_open_invoice();
		
		$pending_stuff = 0;

		if (isset($vcount_pending_approval->NotRetainer)) {
			$pending_stuff = $pending_stuff + $vcount_pending_approval->NotRetainer;
		}
		
		if (isset($vcount_pending_approval->Retainer)) {
			$pending_stuff = $pending_stuff + $vcount_pending_approval->Retainer;
		}
		
		if (isset($vcount_pending_invoice->NotRetainer)) {
			$pending_stuff = $pending_stuff + $vcount_pending_invoice->NotRetainer;
		}
		
		if (isset($vcount_pending_invoice->Retainer)) {
			$pending_stuff = $pending_stuff + $vcount_pending_invoice->Retainer;
		}
		
		$pending_stuff = $pending_stuff + $vcount_pending_payroll + $timesheets_not_complete;

		if (isset($options['menu_location'])) {
			$menuLocation = $options['menu_location'];

		} else {
			$menuLocation = NULL;
		}

		$userMenuLocation = get_user_option('time_sheets_menu_location', $user_id);
		if (isset($options['users_override_location'])) {
			if ($userMenuLocation != '') {
				$menuLocation = $userMenuLocation;
			}
		}

		if ($pending_stuff != 0) {
			$tag = "Time Sheets <span class='update-plugins count-1'><span class='plugin-count'>{$pending_stuff}</span></span>";
		} else {
			$tag = 'Time Sheets';
		}
		
		add_menu_page('Time Sheets', $tag, '', 'time_sheets_top', array($time_sheets_entry, 'show_timesheet'), plugins_url( 'time-sheets/icon.png' ), $menuLocation);

		add_submenu_page('time_sheets_top', 'Enter Time Sheet', 'Enter Time Sheet', 'read', 'show_timesheet', array($time_sheets_entry, 'show_timesheet'));

		if ($timesheets_not_complete == 0) {
			$tag = "My Dashboard";
		} else {
			$tag = "My Dashboard<span class='update-plugins count-1'><span class='plugin-count'>{$timesheets_not_complete}</span></span>";
		}

		add_submenu_page('time_sheets_top', 'My Dashboard', $tag, 'read', 'search_timesheet', array($mydashboard, 'show_dashboard'));

		if ($client_managers->client_manager_check()==1) {
			add_submenu_page('time_sheets_top', 'Manage Clients & Projects', 'Manage Clients', 'read', 'timesheet_manage_clients', array($time_sheets_manage_projects, 'main'));
		}

		if ($queue_approval->employee_approver_check('true')!=0) {
			#$vcount_pending_approval = $queue_approval->count_pending_approval();

			if ($vcount_pending_approval->Embargoed != 0) {
				$embargo_value = "/{$vcount_pending_approval->Embargoed}";
			} else {
				$embargo_value = '';
			}

			if ($vcount_pending_approval->NotRetainer+$vcount_pending_approval->Retainer != 0) {
				$tag = "Approval Queue<span class='update-plugins count-1'><span class='plugin-count'>{$vcount_pending_approval->NotRetainer}/{$vcount_pending_approval->Retainer}{$embargo_value}</span></span>";

			} else {
				$tag = "Approval Queue";
			}

			add_submenu_page('time_sheets_top', 'Approvel Queue', $tag, 'read', 'approve_timesheet', array($queue_approval, 'approve_timesheet'));
		}

		if ($queue_invoice->employee_invoicer_check()!=0) {
			#$vcount_pending_invoicing = $queue_invoice->count_pending_invoice();

			if ($vcount_pending_invoicing->NotRetainer+$vcount_pending_invoicing->Retainer != 0) {
				$tag = "Invoice Queue<span class='update-plugins count-1'><span class='plugin-count'>{$vcount_pending_invoicing->NotRetainer}/{$vcount_pending_invoicing->Retainer}</span></span>";
			} else {
				$tag = "Invoice Queue";
			}


			add_submenu_page('time_sheets_top', 'Invoice Queue', $tag, 'read', 'invoice_timesheet', array($queue_invoice, 'invoice_timesheet'));
			add_submenu_page('time_sheets_top', 'Closed Timesheets', 'Closed Timesheets', 'read', 'closed_timesheet', array($queue_invoice, 'closed_timesheet'));

		}
		
		if ($queue_payroll->employee_payroll_check()!=0) {
			#$vcount_pending_payroll = $queue_payroll->count_pending_payroll();

			if ($vcount_pending_payroll != 0) {
				$tag = "Payroll Queue<span class='update-plugins count-1'><span class='plugin-count'>{$vcount_pending_payroll}</span></span>";
			} else {
				$tag = "Payroll Queue";
			}


			add_submenu_page('time_sheets_top', 'Payroll Queue', $tag, 'read', 'payroll_timesheet', array($queue_payroll, 'payroll_timesheet'));
			add_submenu_page('time_sheets_top', 'Employees Who Always Are Sent to Payroll for Processing', 'Force Payroll Setup', 'read', 'employees_allways_to_payroll', array($time_sheets_settings, 'employees_allways_to_payroll'));
		}

		add_submenu_page('time_sheets_top', 'Manage Approvers', 'Manage Approvers', 'manage_options', 'time_sheets_manage_approvers', array($time_sheets_settings, 'manage_approvers'));

		add_submenu_page('time_sheets_top', 'Manage Employees Who Process Invoices', 'Manage Invoicers', 'manage_options', 'time_sheets_manage_invoicers', array($time_sheets_settings, 'manage_invoicers'));

		add_submenu_page('time_sheets_top', 'Manage Payroll Processors', 'Manage Payroll', 'manage_options', 'time_sheets_manage_payroll', array($time_sheets_settings, 'manage_payrollers'));

		add_submenu_page('time_sheets_top', 'Manage Client Managers', 'Manage Client Managers', 'manage_options', 'time_sheets_client_managers', array($client_managers, 'manage_client_managers'));

		if ($queue_approval->employee_approver_check('false')!=0) {
			add_submenu_page('time_sheets_top', 'Setup Approval Teams', 'Setup Approval Teams', 'read', 'setup_approval_teams', array($time_sheets_settings, 'setup_approval_teams'));
		}
		
		if (isset($options['allow_money_based_retainers'])) {
			add_submenu_page('time_sheets_top', 'Setup Employee Titles', 'Setup Employee Titles', 'manage_options', 'setup_employee_titles', array($time_sheets_settings, 'setup_employee_titles'))	;
		}
		
		add_submenu_page('time_sheets_top', 'Time Sheet Global Settings', 'Global Settings', 'manage_options', 'time_sheets_settings', array($time_sheets_settings, 'show_settings_page'));
		add_submenu_page('time_sheets_top', 'My Settings', 'My Settings', 'read', 'my_settings', array($my, 'main'));

		//If the customer has requested custom menus they'll be shown here.
		if (class_exists('time_sheets_custom')) {
			$custom = new time_sheets_custom();
			$custom->custom_menu();
		}

		add_submenu_page('time_sheets_top', 'Documentation', 'Documentation', 'manage_options', 'timesheet_docs', array($docs, 'main'));

		$page = '';
		
		if ($_GET) {
			$page = isset($_GET['page']) ? $_GET['page'] : "";
		}

		
		if (($page == "payroll_timesheet" || $page == "invoice_timesheet" || $page == "approve_timesheet" || $page == "enter_timesheet" || $page == "search_timesheet" || $page == "timesheet_manage_clients" || $page == "timesheet_settings" || $page == "my_settings" ) && (!isset($_GET['action'])) && !isset($_POST['action'])) {
			$this->footer();
		}
	}

	function init_settings(){
		$settings = new time_sheets_settings();
		$settings->register_settings();
	}

	function footer() {
		$options=get_option('time_sheets');
		$time_sheets_main = new time_sheets_main();

		if (!$options['hide_dcac_ad']) {
			add_filter('admin_footer_text', array($time_sheets_main, 'show_footer'));
			add_filter( 'update_footer', array($time_sheets_main, 'show_footer_version'));
		} #else {
		#	remove_filter('admin_footer_text', array($time_sheets_main, 'show_footer'));
		#	remove_filter( 'update_footer', array($time_sheets_main, 'show_footer_version'));
		#}
	}

	function show_footer() {
		echo '<span id="footer-thankyou"><a href="https://www.dcac.co/applications/wordpress-plugins/time-sheets">Time Sheets</a> provided by <a href="http://www.dcac.co">Denny Cherry & Associates Consulting</a><p></span>';
	}

	function show_footer_version() {
		$folder = plugins_url();
		$info = get_plugin_data( __FILE__ );
		echo "Version {$info['Version']}";
	}

	function toolbar_open_invoices( $wp_admin_bar ) {
		$queue_invoice = new time_sheets_queue_invoice();
		$client_managers = new time_sheets_client_managers();
		$vcount_open_invoices = $queue_invoice->count_users_open_invoice();

		$title = "My Time Sheet Dashboard({$vcount_open_invoices})";

		if ($vcount_open_invoices != 0 || $client_managers->client_manager_check()==1) {
			$args = array(
				'id'    => 'open_timesheets',
				'title' => $title,
				'href'  => admin_url('admin.php?page=search_timesheet'),
				'meta'  => array( 'class' => 'my-toolbar-page' )
			);
			$wp_admin_bar->add_node( $args );
			
			$args = array(
				'id'     => 'NewTimeSheet',     // id of the existing child node (New > Post)
				'title'  => 'New Time Sheet', // alter the title of existing node
				'parent' => 'open_timesheets', //'new-content',          // set parent to false to make it a top level (parent) node
				'href'  => admin_url('admin.php?page=show_timesheet')
			);
			$wp_admin_bar->add_node( $args );
			
			if ($client_managers->client_manager_check()==1) {
				if (isset($_POST['ClientId'])) {
					$client_id = "&ClientId=" . $_POST['ClientId'];
				} elseif (isset($_GET['ClientId'])) {
					$client_id = "&ClientId=" . $_GET['ClientId'];
				} else {
					$client_id = '';
				}

				$args = array(
					'id'     => 'Client',     // id of the existing child node (New > Post)
					'title'  => 'Client', // alter the title of existing node
					'parent' => 'open_timesheets', //'new-content',          // set parent to false to make it a top level (parent) node
					'href'  => admin_url('admin.php?page=timesheet_manage_clients&menu=New+Client')
				);
				$wp_admin_bar->add_node( $args );

				$args = array(
					'id'     => 'NewClient',     // id of the existing child node (New > Post)
					'title'  => 'New Client', // alter the title of existing node
					'parent' => 'Client', //'new-content',          // set parent to false to make it a top level (parent) node
					'href'  => admin_url('admin.php?page=timesheet_manage_clients&menu=New+Client')
				);
				$wp_admin_bar->add_node( $args );

				$url = admin_url("admin.php?page=timesheet_manage_clients"  . $client_id . "&menu=Edit+Client");
				$args = array(
					'id'     => 'EditClient',     // id of the existing child node (New > Post)
					'title'  => 'Edit Client', // alter the title of existing node
					'parent' => 'Client', //'new-content',          // set parent to false to make it a top level (parent) node
					'href'  => $url
				);
				$wp_admin_bar->add_node( $args );

				$url = admin_url("admin.php?page=timesheet_manage_clients"  . $client_id . "&menu=New+Project");
				$args = array(
					'id'     => 'Project',     // id of the existing child node (New > Post)
					'title'  => 'Project', // alter the title of existing node
					'parent' => 'open_timesheets', //'new-content',          // set parent to false to make it a top level (parent) node
					'href'  => $url
				);
				$wp_admin_bar->add_node( $args );

				$url = admin_url("admin.php?page=timesheet_manage_clients"  . $client_id . "&menu=New+Project");
				$args = array(
					'id'     => 'NewProject',     // id of the existing child node (New > Post)
					'title'  => 'New Project', // alter the title of existing node
					'parent' => 'Project', //'new-content',          // set parent to false to make it a top level (parent) node
					'href'  => $url
				);
				$wp_admin_bar->add_node( $args );

				$url = admin_url("admin.php?page=timesheet_manage_clients"  . $client_id . "&menu=Edit+Project");
				$args = array(
					'id'     => 'EditProject',     // id of the existing child node (New > Post)
					'title'  => 'Edit Project', // alter the title of existing node
					'parent' => 'Project', //'new-content',          // set parent to false to make it a top level (parent) node
					'href'  => $url
				);
				$wp_admin_bar->add_node( $args );


			}
		} else {
			$args = array(
			'id'     => 'TimeSheets',     // id of the existing child node (New > Post)
			'title'  => 'Time Sheet', // alter the title of existing node
			'parent' => 'new-content',          // set parent to false to make it a top level (parent) node
			'href'  => admin_url('admin.php?page=enter_timesheet')
		);
		$wp_admin_bar->add_node( $args );
		}
	}

	function toolbar_pending_approval( $wp_admin_bar ) {
		$queue_approval = new time_sheets_queue_approval();
		
		global $vcount_pending_approval;

		if ($queue_approval->employee_approver_check()!=0) {
			#$vcount_pending_approval = $queue_approval->count_pending_approval();

			if (isset($vcount_pending_approval->Embargoe) && ($vcount_pending_approval->Embargoed != 0)) {
				$embargo_value = "/{$vcount_pending_approval->Embargoed}";
			} else {
				$embargo_value = '';
			}

			if (isset($vcount_pending_approval->NotRetainer)) {
				$pending_approval_NotRetainer = $vcount_pending_approval->NotRetainer;
			} else {
				$pending_approval_NotRetainer = 0;
			}

			if (isset($vcount_pending_approval->Retainer)) {
				$pending_approval_retainer = $vcount_pending_approval->Retainer;
			} else {
				$pending_approval_retainer = 0;
			}

			$title = "Approval Queue({$pending_approval_NotRetainer}/{$pending_approval_retainer}{$embargo_value})";
			if ($pending_approval_NotRetainer+$pending_approval_retainer != 0) {
				$args = array(
					'id'    => 'pending_approval',
					'title' => $title,
					'href'  => admin_url('admin.php?page=approve_timesheet'),
					'meta'  => array( 'class' => 'my-toolbar-page' )
				);
				$wp_admin_bar->add_node( $args );
			}
		}
	}

	function toolbar_pending_invoicing( $wp_admin_bar ) {
		$queue_invoice = new time_sheets_queue_invoice();
		
		global $vcount_pending_invoicing;
		
		if ($queue_invoice->employee_invoicer_check()!=0) {
			#$vcount_pending_approval = $queue_invoice->count_pending_invoice();

			if (isset($vcount_pending_invoicing->NotRetainer)) {
				$pending_invoicing_NotRetainer = $vcount_pending_invoicing->NotRetainer;
			} else {
				$pending_invoicing_NotRetainer = 0;
			}

			if (isset($vcount_pending_invoicing->Retainer)) {
				$pending_invoicing_retainer = $vcount_pending_invoicing->Retainer;
			} else {
				$pending_invoicing_retainer = 0;
			}




			$title = "Invoicing Queue({$pending_invoicing_NotRetainer}/{$pending_invoicing_retainer})";

			if ($pending_invoicing_NotRetainer+$pending_invoicing_retainer != 0) {
				$args = array(
					'id'    => 'pending_invoicing',
					'title' => $title,
					'href'  => admin_url('admin.php?page=invoice_timesheet'),
					'meta'  => array( 'class' => 'my-toolbar-page' )
				);
				$wp_admin_bar->add_node( $args );
			}
		}
	}

	function toolbar_pending_payroll( $wp_admin_bar ) {
		$queue_payroll = new time_sheets_queue_payroll();
		
		global $vcount_pending_payroll;
		
		if ($queue_payroll->employee_payroll_check()!=0) {
			#$vcount_pending_payroll = $queue_payroll->count_pending_payroll();

			$title = "Payroll Queue({$vcount_pending_payroll})";

			if ($vcount_pending_payroll != 0) {
				$args = array(
					'id'    => 'pending_payroll',
					'title' => $title,
					'href'  => admin_url('admin.php?page=payroll_timesheet'),
					'meta'  => array( 'class' => 'my-toolbar-page' )
				);
				$wp_admin_bar->add_node( $args );
			}
		}
	}

	function add_new_intervals($schedules) 
	{
		// add weekly and monthly intervals
		$schedules['minutes_5'] = array(
			'interval' => 300,
			'display' => __('Once Every 5 Minutes')
		);

		$schedules['weekly'] = array(
			'interval' => 604800,
			'display' => __('Once Weekly')
		);

		$schedules['monthly'] = array(
			'interval' => 2635200,
			'display' => __('Once a month')
		);

		return $schedules;
	}


	function add_to_add_node( $wp_admin_bar ) {
		$args = array(
			'id'     => 'TimeSheets',     // id of the existing child node (New > Post)
			'title'  => 'Time Sheet', // alter the title of existing node
			'parent' => $false, //'new-content',          // set parent to false to make it a top level (parent) node
			'href'  => admin_url('admin.php?page=enter_timesheet')
		);
		$wp_admin_bar->add_node( $args );
		
		
	}
	
	function admin_notice_post_install(){
		echo '<div if="message" class="error"><p>Settings changes are needed for the time sheet application. Please verify settings.</p></div>';	
	}
	
	function timesheet_register_custom_dashboard_widget() {
		$widget = new time_sheets_widget();
		add_meta_box( 'timesheet-widget', 'Timesheets', array($widget, 'show_widget'), 'dashboard', 'side', 'high' );
	}
} //End Class

$main = new time_sheets_main();
$cron = new time_sheets_cron();
$entry = new time_sheets_entry();
$common = new time_sheets_common();
$dashboard = new time_sheets_mydashboard();

$options = get_option('time_sheets');

register_activation_hook(__FILE__, array($main, 'activation'));
add_action('admin_menu', array($main, 'custom_menu'));
add_action('admin_init', array($main, 'init_settings'), 1);
register_deactivation_hook( __FILE__, array($main, 'deactivation' ));
add_filter('upgrader_post_install', array($main, 'upgrade'), 10, 2); //Deploy database proc on upgrade as needed.

add_filter( 'cron_schedules', array($main, 'add_new_intervals'));
add_action('time_sheets_monthly_cron' , array($cron, 'InsertRecurringInvoices'));
add_action('time_sheets_email_check' , array($cron, 'process_email'));
add_action('time_sheets_email_late_timesheets' , array($cron, 'email_late_timesheets'));
add_action('email_retainers_due', array($cron, 'email_retainers_due'));
add_action('time_sheets_disable_projects', array($cron, 'disable_projects'));
add_action('queue_rejected_reminders', array($cron, 'queue_rejected_reminders'));
add_action('queue_rejected_admin_messages', array($cron, 'queue_rejected_admin_messages'));
add_action('queue_process_client_projects_changequeue', array($cron, 'queue_process_client_projects_changequeue'));
add_action('queue_use_templates', array($cron, 'queue_use_templates'));
add_action('delete_expired_employee_title_overrides', array($cron, 'delete_expired_employee_title_overrides'));


add_action('admin_enqueue_scripts', array($common, 'enqueue_js'));
add_action('wp_enqueue_scripts', array($common, 'enqueue_js'));

add_action( 'wp_dashboard_setup', array($main, 'timesheet_register_custom_dashboard_widget') );

$page = '';

if ($_GET) {
	$page = isset($_GET['page']) ? $_GET['page'] : "";
}

if (($options['override_date_format'] <> 'system_defined' && $options['override_date_format'] <> 'admin_defined') || !isset($options['day_of_week_timesheet_reminders']) || !isset($options['week_starts']) || !isset($options['queue_order']) || !isset($options['sales_override'])  || !isset($options['project_trigger_percent']) || !isset($options['distance_metric']) || !isset($options['currency_char'])) {
	add_action('admin_notices', array($main, 'admin_notice_post_install'));
}

//add_action( 'admin_bar_menu', array($main, 'add_to_add_node'), 100);

if ($page == "payroll_timesheet" || $page == "invoice_timesheet" || $page == "approve_timesheet") { #|| $page == "show_timesheet") {
	add_action( 'admin_bar_menu', array($entry, 'process_timesheets'), 995);
}

if (isset($options['show_header_open_invoices'])) {
	add_action( 'admin_bar_menu', array($main, 'toolbar_open_invoices'), 996 );
}

if (isset($options['show_header_queues'])) {
	add_action( 'admin_bar_menu', array($main, 'toolbar_pending_approval'), 997 );
	add_action( 'admin_bar_menu', array($main, 'toolbar_pending_invoicing'), 998 );
	add_action( 'admin_bar_menu', array($main, 'toolbar_pending_payroll'), 999 );
}

add_shortcode('timesheet_entry', array($entry, 'enter_timesheet_sc'));
add_shortcode('timesheet_search', array($dashboard, 'search_timesheet_sc'));