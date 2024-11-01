<?php
class time_sheets_setup {

	function create_db_objects() {
		$this->create_tables();
	}

	function create_tables() {
		global $wpdb;

		$charset_collate = $this->get_charset();

		$db_ver = get_site_option( 'timesheet_db_version', '0' );

		$user = wp_get_current_user();
		$user_id = $user->ID;


		if ($db_ver=='0') {

			add_site_option( 'timesheet_db_version', '0' );

			$sql = 	"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_approvers`(
				user_id bigint(20),
				PRIMARY KEY (user_id)
				) $charset_collate";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_invoicers` (
				user_id bigint(20),
				PRIMARY KEY (user_id)
				) $charset_collate";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_users` (
				user_id bigint(20),
				PRIMARY KEY (user_id)
				) $charset_collate";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_recurring_invoices_monthly` (
				client_id bigint(20) not null,
				MonthlyHours bigint(20) NULL,
				HourlyRate bigint(20) NULL,
				Notes mediumtext NULL,
				BillOnProjectCompletion tinyint(1) NULL,
				PRIMARY KEY (client_id)
				) $charset_collate";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet` (
				timesheet_id bigint(20) NOT NULL AUTO_INCREMENT,
				user_id bigint(20) NOT NULL,
				start_date datetime NOT NULL,
				entered_date datetime NOT NULL,
				client_name mediumtext NOT NULL,
				project_name mediumtext NULL,
				monday_hours numeric(12,2) NOT NULL,
				tuesday_hours numeric(12,2) NOT NULL,
				wednesday_hours numeric(12,2) NOT NULL,
				thursday_hours numeric(12,2) NOT NULL,
				friday_hours numeric(12,2) NOT NULL,
				saturday_hours numeric(12,2) NOT NULL,
				sunday_hours numeric(12,2) NOT NULL,
				total_hours numeric(12,2) NOT NULL,
				monday_desc mediumtext NULL,
				tuesday_desc mediumtext NULL,
				wednesday_desc mediumtext NULL,
				thursday_desc mediumtext NULL,
				friday_desc mediumtext NULL,
				saturday_desc mediumtext NULL,
				sunday_desc mediumtext NULL,
				per_diem_days numeric(6,2) NULL,
				hotel_charges numeric(12,2) NULL,
				rental_car_charges numeric(12,2) NULL,
				tolls numeric(12,2) NULL,
				other_expenses numeric(12,2) NULL,
				other_expenses_notes longtext NULL,
				week_complete tinyint(1) NOT NULL,
				marked_complete_by bigint(20) NULL,
				marked_complete_date datetime NULL,
				approved tinyint(1) NOT NULL,
				approved_by bigint(20) NULL,
				approved_date datetime NULL,
				invoiced tinyint(1) NOT NULL,
				invoiced_by bigint(20) NULL,
				invoiced_date datetime NULL,
				invoiceid bigint(20) NULL,
				ClientId bigint(20) NULL,
				mileage bigint(20) NULL,
				EmbargoPendingProjectClose tinyint(1) NULL,
				project_complete tinyint(1) NULL,
				ProjectId bigint(20),
				PRIMARY KEY (timesheet_id),
				INDEX IX_user_id_start_date (user_id, start_date),
				INDEX IX_approved (approved),
				INDEX IX_invoiced (invoiced)
				) $charset_collate";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_clients` (
				ClientId bigint(20) NOT NULL AUTO_INCREMENT,
				ClientName mediumtext NOT NULL,
				Active tinyint(1) NULL,
				FinalProjectEnd datetime NULL,
				PRIMARY KEY (ClientId),
				INDEX IX_FinalProjectEnd_Active (FinalProjectEnd, Active)) $charset_collate";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timesheet_clients_users (
				ClientId bigint(20) NOT NULL,
				user_id bigint(20) NOT NULL,
				PRIMARY KEY (ClientId, user_id),
				INDEX IX_user_id_ClientId (user_id, ClientId)) $charset_collate";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timesheet_approvers_approvies (
				approver_user_id bigint(20) NOT NULL,
				approvie_user_id bigint(20) NOT NULL,
				PRIMARY KEY (approver_user_id, approvie_user_id)) $charset_collate";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timesheet_client_projects (
				ProjectId bigint(20) NOT NULL AUTO_INCREMENT,
				ClientId bigint(20) NOT NULL,
				ProjectName mediumtext NOT NULL,
				IsRetainer tinyint(1) NOT NULL,
				MaxHours bigint(20) NOT NULL,
				HoursUsed bigint(20) NOT NULL,
				Active bit NOT NULL,
				notes mediumtext NULL,
				PRIMARY KEY (ProjectId),
				INDEX ix_ClientId (ClientId)) $charset_collate";
			$wpdb->query($sql);

			$db_ver = .5;
		}


		if ($db_ver=='.5') {
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_client_projects
				(ClientId, ProjectName, IsRetainer, MaxHours, HoursUsed, Active)
				SELECT ClientId, case when project_name = '' then 'Not Specified' else project_name end, CASE WHEN project_name = 'Retainer' then 1 else 0 end, 1000, sum(total_hours), 1
				FROM {$wpdb->prefix}timesheet a
				WHERE NOT EXISTS (SELECT * FROM {$wpdb->prefix}timesheet_client_projects b where a.ClientId = b.ClientId AND b.ProjectName = case when project_name = '' then 'Not Specified' else project_name end)
				GROUP BY ClientId, case when project_name = '' then 'Not Specified' else project_name end, CASE WHEN project_name = 'Retainer' then 1 else 0 end, 1";
			$wpdb->query($sql); //Only needed if using prerelease schema.

			$sql = "UPDATE {$wpdb->prefix}timesheet b
				inner join {$wpdb->prefix}timesheet_client_projects a on b.ClientId = a.ClientId 
					AND ProjectName = case when project_name = '' then 'Not Specified' else project_name end
				SET b.ProjectId = a.ProjectId
				WHERE b.ProjectId IS NULL";
			$wpdb->query($sql);

			$sql = "INSERT INTO {$wpdb->prefix}timesheet_clients
				(ClientName, Active, FinalProjectEnd)
				SELECT DISTINCT client_name, 1, NULL
				FROM {$wpdb->prefix}timesheet
				WHERE client_name NOT IN (SELECT ClientName FROM {$wpdb->prefix}timesheet_clients)";
			$wpdb->query($sql);

			$sql = "UPDATE {$wpdb->prefix}timesheet t
				JOIN {$wpdb->prefix}timesheet_clients c ON t.client_name = c.ClientName
				SET t.ClientId = c.ClientId
				WHERE t.ClientId IS NULL";
			$wpdb->query($sql);

			$sql = "INSERT INTO {$wpdb->prefix}timesheet_clients_users
				(ClientId, user_id)
				SELECT DISTINCT ClientId, user_id
				FROM {$wpdb->prefix}timesheet t
				WHERE NOT EXISTS (SELECT * FROM {$wpdb->prefix}timesheet_clients_users c WHERE t.ClientId = c.ClientId AND t.user_id = c.user_id)";
			$wpdb->query($sql);

			$db_ver = 1;
		}

		if ($db_ver==1) {
			$sql = "ALTER TABLE `{$wpdb->prefix}timesheet`
				ADD COLUMN payrolled tinyint(1) DEFAULT 0,
				ADD COLUMN payrolled_on datetime,
				ADD COLUMN payrolled_by bigint(20)
				";
			$wpdb->query($sql);

			$sql = "ALTER TABLE `{$wpdb->prefix}timesheet`
				ADD INDEX IX_invoiced_payrolled (invoiced, payrolled)";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_payrollers` (
				user_id bigint(20),
				PRIMARY KEY (user_id)
				) $charset_collate";
			$wpdb->query($sql);

			$db_ver = 2;
		}

		if ($db_ver==2) {
			$sql = "ALTER TABLE `{$wpdb->prefix}timesheet`
				ADD COLUMN flight_cost decimal(12,2)";
			$wpdb->query($sql);

			$db_ver = 3;
		}

		if ($db_ver==3) {
			$sql = "CREATE TABLE `{$wpdb->prefix}timesheet_emailqueue`
				(email_id  bigint(20) NOT NULL AUTO_INCREMENT,
				send_to varchar(255),
				send_from_email varchar(255),
				send_from_name  varchar(255),
				subject  varchar(255),
				message_body mediumtext not null,
				entered_on datetime,
				PRIMARY KEY (email_id),
				INDEX ix_bigindex (entered_on, send_to, send_from_email, send_from_name, subject),
				INDEX ix_entered_on (entered_on)
				) $charset_collate";
			$wpdb->query($sql);

			$db_ver = 4;
		}

		if ($db_ver==4) {
			$sql = "ALTER TABLE `{$wpdb->prefix}timesheet`
				ADD COLUMN isPerDiem tinyint(2)";
			$wpdb->query($sql);

			$sql = "update {$wpdb->prefix}timesheet
				set isPerDiem = 1
				where isPerDiem IS NULL";
			$wpdb->query($sql);

			$db_ver = 5;
		}

		if ($db_ver==5) {
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
				ADD COLUMN BillOnProjectCompletion tinyint(1)";
			$wpdb->query($sql);

			$db_ver = 6;
		}

		if ($db_ver==6) {
			$sql = "CREATE TABLE `{$wpdb->prefix}timesheet_employee_always_to_payroll`
				(user_id  bigint(20) NOT NULL) $charset_collate";
			$wpdb->query($sql);

			$db_ver = 7;
		}
		if ($db_ver==7) {
			$sql = "ALTER TABLE `{$wpdb->prefix}timesheet`
				ADD COLUMN perdiem_city varchar(255)";
			$wpdb->query($sql);

			$db_ver = 8;
		}

		if ($db_ver==8) {
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_approvers
				ADD COLUMN backup_user_id bigint(20),
				ADD COLUMN backup_expires_on datetime";

			$wpdb->query($sql);

			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_manage_client_users
					(user_id bigint(20)) $charset_collate";


			$wpdb->query($sql);

			$db_ver = 9;
		}


		if ($db_ver==9) {
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
				ADD COLUMN flat_rate tinyint(1)";

			$wpdb->query($sql);

			$db_ver = 10;
		}


		if ($db_ver==10) {
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
				ADD COLUMN po_number varchar(255)";

			$wpdb->query($sql);

			$db_ver = 11;
		}
		
		if ($db_ver==11) {
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
				ADD COLUMN sales_person_id bigint(20)";
			
			$wpdb->query($sql);

			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_clients
				ADD COLUMN sales_person_id bigint(20)";
			
			$wpdb->query($sql);
			
			$db_ver = 12;
		}
		
		if ($db_ver==12) {
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
				ADD COLUMN technical_sales_person_id bigint(20)";
			
			$wpdb->query($sql);

			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_clients
				ADD COLUMN technical_sales_person_id bigint(20)";
			
			$wpdb->query($sql);
			
			$db_ver = 13;
		}
		
		if ($db_ver==13) {
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_comment_audit
				(timesheet_comment_audit_id bigint(20) NOT NULL AUTO_INCREMENT,
				timesheet_id bigint(20) NOT NULL,
				entered_by bigint(20) NOT NULL,
				entered_at datetime NOT NULL,
				comment mediumtext NOT NULL,
				timesheet_rejected tinyint(1) NOT NULL,
				PRIMARY KEY (timesheet_comment_audit_id),
				INDEX view_audits (timesheet_id, entered_at desc)
				)";
			
			$wpdb->query($sql);
			
			$db_ver = 14;
		}
		
		if ($db_ver==14) {
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_client_project_autoclose
			(project_id bigint(20) not null,
			close_date datetime not null,
			PRIMARY KEY (project_id),
			INDEX IX_close_date (close_date)
			)";
			
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
			ADD COLUMN close_on_completion tinyint(1)";
			
			$wpdb->query($sql);
			
			$db_ver = 15;
		}
		
		if ($db_ver==15) {
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN taxi numeric(12,2)";
			
			$wpdb->query($sql);
			
			$db_ver = 16;
		}
		
		
		if ($db_ver==16) {
			$sql = "UPDATE {$wpdb->prefix}timesheet_client_projects
				SET Active = 1
			WHERE IsRetainer = 1";
			
			$wpdb->query($sql);
			
			$db_ver = 17;
		}
		
		if ($db_ver == 17){
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN monday_ot numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN tuesday_ot numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN wednesday_ot numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN thursday_ot numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN friday_ot numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN saturday_ot numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN sunday_ot numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
			MODIFY HoursUsed numeric(12,2)";
			$wpdb->query($sql);
			
			$sql = "UPDATE {$wpdb->prefix}timesheet_client_projects
			INNER JOIN (SELECT ProjectId, sum(total_hours) as sumhours FROM {$wpdb->prefix}timesheet GROUP BY ProjectId) t ON {$wpdb->prefix}timesheet_client_projects.ProjectId = t.ProjectId
			SET HoursUsed = t.sumhours";
			$wpdb->query($sql);
			
						
			$db_ver = 18;
		}
		if ($db_ver == 18){
			$sql = "UPDATE {$wpdb->prefix}timesheet_client_projects
			INNER JOIN (SELECT ProjectId, sum(total_hours) as sumhours FROM {$wpdb->prefix}timesheet where week_complete = 1 GROUP BY ProjectId) t ON {$wpdb->prefix}timesheet_client_projects.ProjectId = t.ProjectId
			SET HoursUsed = t.sumhours";
			$wpdb->query($sql);
			
						
			$db_ver = 19;
		}
		if ($db_ver==19) {
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_recurring_invoices_monthly
			ADD COLUMN reqular_interval tinyint(1) NULL";
			$wpdb->query($sql);
			
			$sql = "UPDATE {$wpdb->prefix}timesheet_recurring_invoices_monthly
						SET reqular_interval = 1";
			$wpdb->query($sql);
			
			$db_ver = 20;
		}
		//Use the colation that the origional tables were created with, not the current one. Hopefully
		//they aren't different, but you never know. I ran across this the hard way. :(
	
		$charset_collate = $this->get_charset2();
		if ($db_ver==20) {
			//Changing the way the tables are made, and depending on if your tables have been created
			//we might need to just skip this version.
			
			$db_ver = 21;
		}
		if ($db_ver==21) {
			$sql = 	"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_approvers_archive`(
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				user_id bigint(20),
				backup_user_id bigint(20),
				backup_expires_on datetime,
				PRIMARY KEY (created_on, user_id)
				) ";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_invoicers_archive` (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				user_id bigint(20),
				PRIMARY KEY (created_on, user_id)
				) ";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_users_archive` (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				user_id bigint(20),
				PRIMARY KEY (created_on, user_id)
				) ";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_recurring_invoices_monthly_archive` (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				client_id bigint(20) not null,
				MonthlyHours bigint(20) NULL,
				HourlyRate bigint(20) NULL,
				Notes mediumtext NULL,
				BillOnProjectCompletion tinyint(1) NULL,
				PRIMARY KEY (created_on, client_id)
				)";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_archive` (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				timesheet_id bigint(20) NOT NULL,
				user_id bigint(20) NOT NULL,
				start_date datetime NOT NULL,
				entered_date datetime NOT NULL,
				client_name mediumtext NOT NULL,
				project_name mediumtext NULL,
				monday_hours numeric(12,2) NOT NULL,
				tuesday_hours numeric(12,2) NOT NULL,
				wednesday_hours numeric(12,2) NOT NULL,
				thursday_hours numeric(12,2) NOT NULL,
				friday_hours numeric(12,2) NOT NULL,
				saturday_hours numeric(12,2) NOT NULL,
				sunday_hours numeric(12,2) NOT NULL,
				total_hours numeric(12,2) NOT NULL,
				monday_desc mediumtext NULL,
				tuesday_desc mediumtext NULL,
				wednesday_desc mediumtext NULL,
				thursday_desc mediumtext NULL,
				friday_desc mediumtext NULL,
				saturday_desc mediumtext NULL,
				sunday_desc mediumtext NULL,
				per_diem_days numeric(6,2) NULL,
				hotel_charges numeric(12,2) NULL,
				rental_car_charges numeric(12,2) NULL,
				tolls numeric(12,2) NULL,
				other_expenses numeric(12,2) NULL,
				other_expenses_notes longtext NULL,
				week_complete tinyint(1) NOT NULL,
				marked_complete_by bigint(20) NULL,
				marked_complete_date datetime NULL,
				approved tinyint(1) NOT NULL,
				approved_by bigint(20) NULL,
				approved_date datetime NULL,
				invoiced tinyint(1) NOT NULL,
				invoiced_by bigint(20) NULL,
				invoiced_date datetime NULL,
				invoiceid bigint(20) NULL,
				ClientId bigint(20) NULL,
				mileage bigint(20) NULL,
				EmbargoPendingProjectClose tinyint(1) NULL,
				project_complete tinyint(1) NULL,
				ProjectId bigint(20),
				payrolled tinyint(1) DEFAULT 0,
				payrolled_on datetime,
				payrolled_by bigint(20),
				flight_cost decimal(12,2),
				isPerDiem tinyint(2),
				perdiem_city varchar(255),
				taxi numeric(12,2),
				monday_ot numeric(12,2) NOT NULL,
				tuesday_ot numeric(12,2) NOT NULL,
				wednesday_ot numeric(12,2) NOT NULL,
				thursday_ot numeric(12,2) NOT NULL,
				friday_ot numeric(12,2) NOT NULL,
				saturday_ot numeric(12,2) NOT NULL,
				sunday_ot numeric(12,2) NOT NULL,
				
				PRIMARY KEY (created_on, timesheet_id),
				INDEX IX_user_id_start_date (user_id, start_date),
				INDEX IX_approved (approved),
				INDEX IX_invoiced (invoiced)
				
				)";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_clients_archive` (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				ClientId bigint(20) NOT NULL,
				ClientName mediumtext NOT NULL,
				Active tinyint(1) NULL,
				FinalProjectEnd datetime NULL,
				sales_person_id bigint(20),
				technical_sales_person_id bigint(20),
				PRIMARY KEY (created_on, ClientId),
				INDEX IX_FinalProjectEnd_Active (FinalProjectEnd, Active))";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timesheet_clients_users_archive (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				ClientId bigint(20) NOT NULL,
				user_id bigint(20) NOT NULL,
				PRIMARY KEY (created_on, ClientId, user_id),
				INDEX IX_user_id_ClientId (user_id, ClientId))";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timesheet_approvers_approvies_archive (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				approver_user_id bigint(20) NOT NULL,
				approvie_user_id bigint(20) NOT NULL,
				PRIMARY KEY (created_on, approver_user_id, approvie_user_id))";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timesheet_client_projects_archive (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				ProjectId bigint(20) NOT NULL,
				ClientId bigint(20) NOT NULL,
				ProjectName mediumtext NOT NULL,
				IsRetainer tinyint(1) NOT NULL,
				MaxHours bigint(20) NOT NULL,
				HoursUsed numeric(12,2) NOT NULL,
				Active bit NOT NULL,
				notes mediumtext NULL,
				BillOnProjectCompletion tinyint(1),
				flat_rate tinyint(1),
				po_number varchar(255),
				sales_person_id bigint(20),
				technical_sales_person_id bigint(20),
				close_on_completion tinyint(1),
				PRIMARY KEY (created_on, ProjectId),
				INDEX ix_ClientId (ClientId))";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_payrollers_archive` (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				user_id bigint(20),
				PRIMARY KEY (created_on, user_id)
				)";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_employee_always_to_payroll_archive`
				(created_by bigint(20),
				created_on datetime,
				action varchar(10),
				user_id  bigint(20) NOT NULL)";
			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timesheet_manage_client_users_archive
					(created_by bigint(20),
				created_on datetime,
				action varchar(10),
				user_id bigint(20))";
			$wpdb->query($sql);

	
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_approvers_archive
				(created_by, created_on, action, user_id, backup_user_id, backup_expires_on)
				SELECT -1, now(), 'SEEDED', user_id, backup_user_id, backup_expires_on
				FROM {$wpdb->prefix}timesheet_approvers
				WHERE NOT EXISTS (SELECT *
					FROM {$wpdb->prefix}timesheet_approvers_archive
					WHERE {$wpdb->prefix}timesheet_approvers_archive.user_id = {$wpdb->prefix}timesheet_approvers.user_id)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_invoicers_archive
				(created_by, created_on, action, user_id)
				SELECT -1, now(), 'SEEDED', user_id
				FROM {$wpdb->prefix}timesheet_invoicers
				WHERE NOT EXISTS (SELECT *
					FROM {$wpdb->prefix}timesheet_invoicers_archive
					WHERE {$wpdb->prefix}timesheet_invoicers_archive.user_id = {$wpdb->prefix}timesheet_invoicers.user_id)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_users_archive
				(created_by, created_on, action, user_id)
				SELECT -1, now(), 'SEEDED', user_id
				FROM {$wpdb->prefix}timesheet_users
				WHERE NOT EXISTS (SELECT *
					FROM {$wpdb->prefix}timesheet_users_archive
					WHERE {$wpdb->prefix}timesheet_users_archive.user_id = {$wpdb->prefix}timesheet_users.user_id)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_recurring_invoices_monthly_archive
(created_by, created_on, action, client_id, MonthlyHours, HourlyRate, Notes, BillOnProjectCompletion)
SELECT -1, now(), 'SEEDED', client_id, MonthlyHours, HourlyRate, Notes, BillOnProjectCompletion
FROM {$wpdb->prefix}timesheet_recurring_invoices_monthly
WHERE NOT EXISTS (SELECT *
FROM {$wpdb->prefix}timesheet_recurring_invoices_monthly_archive
WHERE {$wpdb->prefix}timesheet_recurring_invoices_monthly_archive.client_id = {$wpdb->prefix}timesheet_recurring_invoices_monthly.client_id)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_archive
(created_by, created_on, action, timesheet_id, user_id, start_date, entered_date, client_name, project_name, monday_hours, tuesday_hours, wednesday_hours, thursday_hours, friday_hours, saturday_hours, sunday_hours, total_hours, monday_desc, tuesday_desc, wednesday_desc, thursday_desc, friday_desc, saturday_desc, sunday_desc, per_diem_days, hotel_charges, rental_car_charges, tolls, other_expenses, other_expenses_notes, week_complete, marked_complete_by, marked_complete_date, approved, approved_by, approved_date, invoiced, invoiced_by, invoiced_date, invoiceid, ClientId, mileage, EmbargoPendingProjectClose, project_complete, ProjectId, payrolled, payrolled_on, payrolled_by, flight_cost, isPerDiem, perdiem_city, taxi, monday_ot, tuesday_ot, wednesday_ot, thursday_ot, friday_ot, saturday_ot, sunday_ot)
SELECT -1, now(), 'SEEDED', timesheet_id, user_id, start_date, entered_date, client_name, project_name, monday_hours, tuesday_hours, wednesday_hours, thursday_hours, friday_hours, saturday_hours, sunday_hours, total_hours, monday_desc, tuesday_desc, wednesday_desc, thursday_desc, friday_desc, saturday_desc, sunday_desc, per_diem_days, hotel_charges, rental_car_charges, tolls, other_expenses, other_expenses_notes, week_complete, marked_complete_by, marked_complete_date, approved, approved_by, approved_date, invoiced, invoiced_by, invoiced_date, invoiceid, ClientId, mileage, EmbargoPendingProjectClose, project_complete, ProjectId, payrolled, payrolled_on, payrolled_by, flight_cost, isPerDiem, perdiem_city, taxi, monday_ot, tuesday_ot, wednesday_ot, thursday_ot, friday_ot, saturday_ot, sunday_ot
FROM {$wpdb->prefix}timesheet
WHERE NOT EXISTS (SELECT *
FROM {$wpdb->prefix}timesheet_archive
WHERE {$wpdb->prefix}timesheet_archive.timesheet_id = {$wpdb->prefix}timesheet.timesheet_id)";
			$wpdb->query($sql);
			 
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_clients_archive
(created_by, created_on, action, ClientId, ClientName, Active, FinalProjectEnd, sales_person_id, technical_sales_person_id)
SELECT -1, now(), 'SEEDED', ClientId, ClientName, Active, FinalProjectEnd, sales_person_id, technical_sales_person_id
FROM {$wpdb->prefix}timesheet_clients
WHERE NOT EXISTS (SELECT *
FROM {$wpdb->prefix}timesheet_clients_archive
WHERE {$wpdb->prefix}timesheet_clients_archive.ClientId = {$wpdb->prefix}timesheet_clients.ClientId)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_clients_users_archive
(created_by, created_on, action, ClientId, user_id)
SELECT -1, now(), 'SEEDED', ClientId, user_id
FROM {$wpdb->prefix}timesheet_clients_users
WHERE NOT EXISTS (SELECT *
FROM {$wpdb->prefix}timesheet_clients_users_archive
WHERE {$wpdb->prefix}timesheet_clients_users_archive.ClientId = {$wpdb->prefix}timesheet_clients_users.ClientId)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_approvers_approvies_archive
(created_by, created_on, action, approver_user_id, approvie_user_id)
SELECT -1, now(), 'SEEDED', approver_user_id, approvie_user_id
FROM {$wpdb->prefix}timesheet_approvers_approvies
WHERE NOT EXISTS (SELECT *
FROM {$wpdb->prefix}timesheet_approvers_approvies_archive
WHERE {$wpdb->prefix}timesheet_approvers_approvies_archive.approver_user_id = {$wpdb->prefix}timesheet_approvers_approvies.approver_user_id
	and {$wpdb->prefix}timesheet_approvers_approvies_archive.approvie_user_id = {$wpdb->prefix}timesheet_approvers_approvies.approvie_user_id)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_client_projects_archive
(created_by, created_on, action, ProjectId, ClientId, ProjectName, IsRetainer, MaxHours, HoursUsed, Active, notes, BillOnProjectCompletion, flat_rate, po_number, sales_person_id, technical_sales_person_id, close_on_completion)
SELECT -1, now(), 'SEEDED', ProjectId, ClientId, ProjectName, IsRetainer, MaxHours, HoursUsed, Active, notes, BillOnProjectCompletion, flat_rate, po_number, sales_person_id, technical_sales_person_id, close_on_completion
FROM {$wpdb->prefix}timesheet_client_projects
WHERE NOT EXISTS (SELECT *
FROM {$wpdb->prefix}timesheet_client_projects_archive
WHERE {$wpdb->prefix}timesheet_client_projects_archive.ProjectName = {$wpdb->prefix}timesheet_client_projects.ProjectName)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_payrollers_archive
(created_by, created_on, action, user_id)
SELECT -1, now(), 'SEEDED', user_id
FROM {$wpdb->prefix}timesheet_payrollers
WHERE NOT EXISTS (SELECT *
FROM {$wpdb->prefix}timesheet_payrollers_archive
WHERE {$wpdb->prefix}timesheet_payrollers_archive.user_id = {$wpdb->prefix}timesheet_payrollers.user_id)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_employee_always_to_payroll_archive
(created_by, created_on, action, user_id)
SELECT -1, now(), 'SEEDED', user_id
FROM {$wpdb->prefix}timesheet_employee_always_to_payroll
WHERE NOT EXISTS (SELECT *
FROM {$wpdb->prefix}timesheet_employee_always_to_payroll_archive
WHERE {$wpdb->prefix}timesheet_employee_always_to_payroll_archive.user_id = {$wpdb->prefix}timesheet_employee_always_to_payroll.user_id)";
			$wpdb->query($sql);
			
			$sql = "INSERT INTO {$wpdb->prefix}timesheet_manage_client_users_archive
(created_by, created_on, action, user_id)
SELECT -1, now(), 'SEEDED', user_id
FROM {$wpdb->prefix}timesheet_manage_client_users
WHERE NOT EXISTS (SELECT *
FROM {$wpdb->prefix}timesheet_manage_client_users_archive
WHERE {$wpdb->prefix}timesheet_manage_client_users_archive.user_id = {$wpdb->prefix}timesheet_manage_client_users.user_id)";
			$wpdb->query($sql);
			
	
			
			
			$db_ver = 22;
		}
		
		if ($db_ver==22) {
			$sql = "CREATE TABLE `{$wpdb->prefix}timesheet_emailqueue_archive`
				(email_id  bigint(20) NOT NULL,
				send_to varchar(255),
				send_from_email varchar(255),
				send_from_name  varchar(255),
				subject  varchar(255),
				message_body mediumtext not null,
				entered_on datetime,
				sent_on datetime,
				PRIMARY KEY (email_id)
				) ";
			$wpdb->query($sql);
			
			$db_ver = 23;

		}
		
		if ($db_ver==23) {
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN monday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN tuesday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN wednesday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN thursday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN friday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN saturday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet
			ADD COLUMN sunday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_archive
			ADD COLUMN monday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_archive
			ADD COLUMN tuesday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_archive
			ADD COLUMN wednesday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_archive
			ADD COLUMN thursday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_archive
			ADD COLUMN friday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_archive
			ADD COLUMN saturday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_archive
			ADD COLUMN sunday_nb numeric(12,2) NOT NULL";
			$wpdb->query($sql);

			$db_ver = 24;
		}

		if ($db_ver==24) {
			$sql = "CREATE TABLE `{$wpdb->prefix}timesheet_reject_message_queue`
				(timesheet_id  bigint(20) NOT NULL,
				rejected_by bigint(20) not null,
				rejected_on datetime not null,
				email_reminder_on datetime,
				notify_approved_on datetime,
				PRIMARY KEY (timesheet_id)
				) ";
			$wpdb->query($sql);

			$sql = "CREATE TABLE `{$wpdb->prefix}timesheet_reject_message_queue_archive`
				(created_by bigint(20),
				created_on datetime,
				action varchar(10),
				timesheet_id  bigint(20) NOT NULL,
				rejected_by bigint(20) not null,
				rejected_on datetime not null,
				email_reminder_on datetime,
				notify_approved_on datetime,
				PRIMARY KEY (created_on, timesheet_id)
				) ";
			$wpdb->query($sql);

			
			$db_ver = 25;

		}
		
		if ($db_ver==25) {
		$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_recurring_invoices_monthly_schedule` (
				schedule_id bigint(20) NOT NULL  AUTO_INCREMENT,
				active_date datetime NOT NULL,
				client_id bigint(20) not null,
				MonthlyHours bigint(20) NULL,
				HourlyRate bigint(20) NULL,
				Notes mediumtext NULL,
				BillOnProjectCompletion tinyint(1) NULL,
				regular_interval tinyint(1),
				PRIMARY KEY (schedule_id)) $charset_collate";
			$wpdb->query($sql);
			
		$sql = "ALTER TABLE {$wpdb->prefix}timesheet_recurring_invoices_monthly_archive
			ADD COLUMN reqular_interval tinyint(1) NULL";
			$wpdb->query($sql);

			$db_ver = 26;
		}
		if ($db_ver==26) {
			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_client_users_approval_queue` (
				request_id char(38) not null,
				request_created datetime not null,
				user_id bigint(20) not null,
				client_id bigint(20) not null,
				status int not null,
				approved_by bigint(20),
				approved_on bigint(20),
				primary key (request_id)) $charset_collate";

			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_client_users_approval_queue_log` (
				request_id char(38) not null,
				log_created datetime not null,
				status int not null,
				user_id bigint(20) not null,
				primary key (request_id, log_created, user_id)) $charset_collate";

			$wpdb->query($sql);

			$db_ver=27;
		}

		if ($db_ver==27) {
			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_custom_retainer_frequency` (
				retainer_id int NOT NULL  AUTO_INCREMENT,
				retainer_name char(50) NOT NULL,
				w_m_q_interval char(1),
				interval_value bigint(20),
				last_trigger_date datetime,
				active tinyint(1),
				primary key (retainer_id)) $charset_collate";

			$wpdb->query($sql);

			$sql = "ALTER TABLE `{$wpdb->prefix}timesheet_custom_retainer_frequency` AUTO_INCREMENT = 100";
			$wpdb->query($sql);

			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
					ADD COLUMN retainer_id int,
					ADD COLUMN hours_included bigint(20),
					ADD COLUMN hourly_rate numeric(8,2)";
			$wpdb->query($sql);

			$sql = "update {$wpdb->prefix}timesheet_client_projects p
					inner join {$wpdb->prefix}timesheet_recurring_invoices_monthly r on r.client_id = p.ClientId
					set p.retainer_id = r.reqular_interval,
						p.hours_included = r.MonthlyHours,
						p.hourly_rate = r.HourlyRate,
						p.notes = p.notes + ' ' + r.Notes
					where p.IsRetainer = 1";
			$wpdb->query($sql);


			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects_archive
					ADD COLUMN retainer_id int,
					ADD COLUMN hours_included bigint(20),
					ADD COLUMN hourly_rate numeric(8,2)";
			$wpdb->query($sql);

			$sql = "insert into {$wpdb->prefix}timesheet_client_projects_archive
						select $user->ID, now(), 'UPDATED', ProjectId, ClientId, ProjectName, IsRetainer, MaxHours, HoursUsed, Active, notes, BillOnProjectCompletion, flat_rate, po_number, sales_person_id, technical_sales_person_id, close_on_completion, retainer_id, hours_included, hourly_rate
						FROM {$wpdb->prefix}timesheet_client_projects
						WHERE retainer_id IS NOT NULL";
			$wpdb->query($sql);



			$db_ver=28;
		}
		if ($db_ver==28) {
			$sql = "update {$wpdb->prefix}timesheet_client_projects p
				inner join {$wpdb->prefix}timesheet_recurring_invoices_monthly r on r.client_id = p.ClientId
				set p.retainer_id = r.reqular_interval,
				p.hours_included = r.MonthlyHours,
				p.hourly_rate = r.HourlyRate,
				p.notes = concat(p.notes, ' ', r.Notes)
				where p.IsRetainer = 1
				and p.notes = '0'";

			$wpdb->query($sql);

			$sql = "update {$wpdb->prefix}timesheet_client_projects
					set notes = replace(notes, '0 ', '')
				where notes like '0 %'";

			$wpdb->query($sql);

			$db_ver=29;

		}

		if ($db_ver==29) {
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_client_projects_changequeue
				(queue_id bigint not null AUTO_INCREMENT,
				process_date date not null,
				ProjectId bigint,
				ClientId bigint,
				ProjectName mediumtext,
				IsRetainer tinyint(1),
				MaxHours bigint,
				Active bit(1),
				notes mediumtext,
				BillOnProjectCompletion tinyint(1),
				flat_rate tinyint(1),
				po_number varchar(255),
				sales_person_id bigint,
				technical_sales_person_id bigint,
				close_on_completion tinyint(1),
				retainer_id int,
				hours_included bigint,
				hourly_rate decimal(8,2),
				is_processed bit(1),
				is_deleted bit(1),
				created_by bigint,
			primary key (queue_id),
			index (ProjectId, is_deleted, is_processed)) $charset_collate";

			$wpdb->query($sql);

			$db_ver=30;
		}

		if ($db_ver==30) {
			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_scheduled`
				(timesheet_id bigint(20) NOT NULL AUTO_INCREMENT,
				user_id bigint(20) NOT NULL,
				start_date datetime NOT NULL,
				entered_date datetime NOT NULL,
				client_name mediumtext NOT NULL,
				project_name mediumtext NULL,
				monday_hours numeric(12,2) NOT NULL,
				tuesday_hours numeric(12,2) NOT NULL,
				wednesday_hours numeric(12,2) NOT NULL,
				thursday_hours numeric(12,2) NOT NULL,
				friday_hours numeric(12,2) NOT NULL,
				saturday_hours numeric(12,2) NOT NULL,
				sunday_hours numeric(12,2) NOT NULL,
				total_hours numeric(12,2) NOT NULL,
				monday_desc mediumtext NULL,
				tuesday_desc mediumtext NULL,
				wednesday_desc mediumtext NULL,
				thursday_desc mediumtext NULL,
				friday_desc mediumtext NULL,
				saturday_desc mediumtext NULL,
				sunday_desc mediumtext NULL,
				per_diem_days numeric(6,2) NULL,
				hotel_charges numeric(12,2) NULL,
				rental_car_charges numeric(12,2) NULL,
				tolls numeric(12,2) NULL,
				other_expenses numeric(12,2) NULL,
				other_expenses_notes longtext NULL,
				week_complete tinyint(1) NOT NULL,
				marked_complete_by bigint(20) NULL,
				marked_complete_date datetime NULL,
				approved tinyint(1) NOT NULL,
				approved_by bigint(20) NULL,
				approved_date datetime NULL,
				invoiced tinyint(1) NOT NULL,
				invoiced_by bigint(20) NULL,
				invoiced_date datetime NULL,
				invoiceid bigint(20) NULL,
				ClientId bigint(20) NULL,
				mileage bigint(20) NULL,
				EmbargoPendingProjectClose tinyint(1) NULL,
				project_complete tinyint(1) NULL,
				ProjectId bigint(20),
				payrolled tinyint(1) DEFAULT 0,
				payrolled_on datetime,
				payrolled_by bigint(20),
				flight_cost decimal(12,2),
				isPerDiem tinyint(2),
				perdiem_city varchar(255),
				taxi numeric(12,2),
				monday_ot numeric(12,2) NOT NULL,
				tuesday_ot numeric(12,2) NOT NULL,
				wednesday_ot numeric(12,2) NOT NULL,
				thursday_ot numeric(12,2) NOT NULL,
				friday_ot numeric(12,2) NOT NULL,
				saturday_ot numeric(12,2) NOT NULL,
				sunday_ot numeric(12,2) NOT NULL,
				 monday_nb numeric(12,2) NOT NULL,
				 tuesday_nb numeric(12,2) NOT NULL,
				 wednesday_nb numeric(12,2) NOT NULL,
				 thursday_nb numeric(12,2) NOT NULL,
				 friday_nb numeric(12,2) NOT NULL,
				 saturday_nb numeric(12,2) NOT NULL,
				 sunday_nb numeric(12,2) NOT NULL,
				frequency TINYTEXT,
				next_execution datetime,
				expires_on datetime,
				delete_after_expiration tinyint(1),
				
				PRIMARY KEY (timesheet_id),
				INDEX IX_user_id_start_date (user_id, start_date),
				INDEX IX_next_execution_internal (next_execution, frequency(1)) 
				
				)  $charset_collate";
			$wpdb->query($sql);
			
			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}timesheet_scheduled_archive` (
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				timesheet_id bigint(20) NOT NULL,
				user_id bigint(20) NOT NULL,
				start_date datetime NOT NULL,
				entered_date datetime NOT NULL,
				client_name mediumtext NOT NULL,
				project_name mediumtext NULL,
				monday_hours numeric(12,2) NOT NULL,
				tuesday_hours numeric(12,2) NOT NULL,
				wednesday_hours numeric(12,2) NOT NULL,
				thursday_hours numeric(12,2) NOT NULL,
				friday_hours numeric(12,2) NOT NULL,
				saturday_hours numeric(12,2) NOT NULL,
				sunday_hours numeric(12,2) NOT NULL,
				total_hours numeric(12,2) NOT NULL,
				monday_desc mediumtext NULL,
				tuesday_desc mediumtext NULL,
				wednesday_desc mediumtext NULL,
				thursday_desc mediumtext NULL,
				friday_desc mediumtext NULL,
				saturday_desc mediumtext NULL,
				sunday_desc mediumtext NULL,
				per_diem_days numeric(6,2) NULL,
				hotel_charges numeric(12,2) NULL,
				rental_car_charges numeric(12,2) NULL,
				tolls numeric(12,2) NULL,
				other_expenses numeric(12,2) NULL,
				other_expenses_notes longtext NULL,
				week_complete tinyint(1) NOT NULL,
				marked_complete_by bigint(20) NULL,
				marked_complete_date datetime NULL,
				approved tinyint(1) NOT NULL,
				approved_by bigint(20) NULL,
				approved_date datetime NULL,
				invoiced tinyint(1) NOT NULL,
				invoiced_by bigint(20) NULL,
				invoiced_date datetime NULL,
				invoiceid bigint(20) NULL,
				ClientId bigint(20) NULL,
				mileage bigint(20) NULL,
				EmbargoPendingProjectClose tinyint(1) NULL,
				project_complete tinyint(1) NULL,
				ProjectId bigint(20),
				payrolled tinyint(1) DEFAULT 0,
				payrolled_on datetime,
				payrolled_by bigint(20),
				flight_cost decimal(12,2),
				isPerDiem tinyint(2),
				perdiem_city varchar(255),
				taxi numeric(12,2),
				monday_ot numeric(12,2) NOT NULL,
				tuesday_ot numeric(12,2) NOT NULL,
				wednesday_ot numeric(12,2) NOT NULL,
				thursday_ot numeric(12,2) NOT NULL,
				friday_ot numeric(12,2) NOT NULL,
				saturday_ot numeric(12,2) NOT NULL,
				sunday_ot numeric(12,2) NOT NULL,
				 monday_nb numeric(12,2) NOT NULL,
				 tuesday_nb numeric(12,2) NOT NULL,
				 wednesday_nb numeric(12,2) NOT NULL,
				 thursday_nb numeric(12,2) NOT NULL,
				 friday_nb numeric(12,2) NOT NULL,
				 saturday_nb numeric(12,2) NOT NULL,
				 sunday_nb numeric(12,2) NOT NULL,
				frequency TINYTEXT,
				next_execution datetime,
				expires_on datetime,
				delete_after_expiration tinyint(1),
				 
				PRIMARY KEY (created_on, timesheet_id),
				INDEX IX_user_id_start_date (user_id, start_date),
				INDEX IX_next_execution_internal (next_execution, frequency(1))
				
				)  $charset_collate";
			$wpdb->query($sql);
			
			$db_ver=31;
		}

		if ($db_ver==31) {
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_employee_titles
			(
				title_id bigint(20) NOT NULL AUTO_INCREMENT,
				name mediumtext NULL,
				
				PRIMARY KEY (title_id)
			)  $charset_collate";
			$wpdb->query($sql);
			
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_project_employee_titles
			(
				project_id bigint(20) NOT NULL,
				title_id bigint(20) NOT NULL,
				hourly_rate numeric(12,2) NOT NULL,
				
				PRIMARY KEY (project_id, title_id)
			
			)  $charset_collate";
			$wpdb->query($sql);
			
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_project_employee_titles_archive
			(
				created_by bigint(20),
				created_on datetime,
				action varchar(10),
				project_id bigint(20) NOT NULL,
				title_id bigint(20) NOT NULL,
				hourly_rate numeric(12,2) NOT NULL,
				
				PRIMARY KEY (created_on,action, project_id, title_id)
			
			)  $charset_collate";
			$wpdb->query($sql);
			
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_project_employee_titles_changequeue
			(
				queue_id bigint(20) NOT NULL,
				project_id bigint(20) NOT NULL,
				title_id bigint(20) NOT NULL,
				hourly_rate numeric(12,2) NOT NULL,
				
				PRIMARY KEY (queue_id, title_id)
			
			)  $charset_collate";
			$wpdb->query($sql);
			
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
				ADD max_monthly_bucket numeric(12,2) NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects_archive
				ADD max_monthly_bucket numeric(12,2) NULL";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects_changequeue
				ADD max_monthly_bucket numeric(12,2) NULL";
			$wpdb->query($sql);
			
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_employee_title_join
			(
				user_id bigint(20) NOT NULL,
				title_id bigint(20) NOT NULL,
				PRIMARY KEY (user_id)
			) $charset_collate";
			$wpdb->query($sql);
			
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_project_retainer_money_usage
			(
				project_id bigint(20) not null,
				frequency_start_date datetime not null,
				retainer_id int not null,
				max_frequency_bucket decimal(12,2) not null,
				used_amount decimal(12,2) not null,
				
				PRIMARY KEY (project_id, frequency_start_date)
			) $charset_collate";
			$wpdb->query($sql);
			
			
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_client_projects_changequeue_archive
				(
				archive_created_by bigint(20),
				created_on datetime,
				action varchar(10),
				queue_id bigint not null,
				process_date date not null,
				ProjectId bigint,
				ClientId bigint,
				ProjectName mediumtext,
				IsRetainer tinyint(1),
				MaxHours bigint,
				Active bit(1),
				notes mediumtext,
				BillOnProjectCompletion tinyint(1),
				flat_rate tinyint(1),
				po_number varchar(255),
				sales_person_id bigint,
				technical_sales_person_id bigint,
				close_on_completion tinyint(1),
				retainer_id int,
				hours_included bigint,
				hourly_rate decimal(8,2),
				is_processed bit(1),
				is_deleted bit(1),
				created_by bigint,
				max_monthly_bucket numeric(12,2) NULL,
			primary key (created_on, queue_id),
			index (ProjectId, is_deleted, is_processed)) $charset_collate";
			$wpdb->query($sql);
			
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_project_employee_titles_change_archive
			(	archive_created_by bigint(20),
				created_on datetime,
				action varchar(10),
				queue_id bigint(20) NOT NULL,
				project_id bigint(20) NOT NULL,
				title_id bigint(20) NOT NULL,
				hourly_rate numeric(12,2) NOT NULL,
				
				PRIMARY KEY (created_on, queue_id, title_id)
			
			)  $charset_collate";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects
						ADD COLUMN create_date date";
			$wpdb->query($sql);
			
			$sql = "ALTER TABLE {$wpdb->prefix}timesheet_client_projects_archive
						ADD COLUMN create_date date";
			$wpdb->query($sql);
			
			$sql = "UPDATE {$wpdb->prefix}timesheet_client_projects
						set create_date = now()
					WHERE create_date IS NULL";
			$wpdb->query($sql);
			
			$sql = "UPDATE {$wpdb->prefix}timesheet_client_projects_archive
						set create_date = now()
					WHERE create_date IS NULL";
			$wpdb->query($sql);
			
			$sql = "CREATE TABLE {$wpdb->prefix}timesheet_customer_project_employee_title_override
			(
				user_id bigint(20) NOT NULL,
				project_id bigint(20) NOT NULL,
				title_id bigint(20) NOT NULL,
				expiration_date date NULL,
				PRIMARY KEY (user_id, project_id)
			) $charset_collate";
			$wpdb->query($sql);

			$db_ver=32;
			
		}

		update_site_option( 'timesheet_db_version', $db_ver );

	}

	function get_charset() {
		global $wpdb;

		$charset_collate = '';
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";

		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";

		return $charset_collate;
	}
	
	function get_charset2() {
		global $wpdb;

		$charset_collate = '';
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";

		if ( ! empty($wpdb->collate) ) {
				
			$sql = "select TABLE_COLLATION FROM information_schema.tables where TABLE_NAME = '{$wpdb->prefix}timesheet_emailqueue'";
			$collation = $wpdb->get_var($sql);
		 
			$charset_collate .= " COLLATE $collation";
		}
		return $charset_collate;
	}
}
