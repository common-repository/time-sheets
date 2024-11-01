<?php
class time_sheets_client_managers {
	function manage_client_managers() {
		if (isset($_GET['action'])) {
			$this->update_client_manager_list();
		}

		$this->show_client_manager_list();

	}

	function show_client_manager_list() {
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		
		#$entry = new time_sheets_entry();
		$db = new time_sheets_db();
		$folder = plugins_url();

		$sql = "select distinct u.ID, u.display_name, mcu.user_id, u.user_login
			from {$wpdb->users} u
			join {$wpdb->prefix}usermeta um on u.id = um.user_id
			left outer join {$wpdb->prefix}timesheet_manage_client_users mcu on u.ID = mcu.user_id
			where mcu.user_id is not null or mcu.user_id is null and (um.meta_key = '{$wpdb->prefix}capabilities'
			and um.meta_value != 'a:0:{}')
			order by u.display_name";

		$users = $db->get_results($sql);

		echo "<br><table border='1' cellpadding='0' cellspacing='0'><tr><td><B>User</B></td><td><B>User Name</b></td><td><b>Current State</b></td><td><B>Add Permission</B></td><td><B>Remove Permission</B></td></tr>";
		foreach ($users as $user) {
			echo "<tr><td>{$user->display_name}</td>
				<td>{$user->user_login}</td>
				<td>";
				if ($user->ID != $user->user_id) {
					echo "No Access";
				} else {
					echo "Has Access";
				}
				echo "</td>
			<td align='center'>";
			if ($user->ID != $user->user_id) {
				echo "<a href='admin.php?page=time_sheets_client_managers&user_id={$user->ID}&action=add'><img src='{$folder}/time-sheets/check.png' width='15' height='15'></a>";
			}
			echo "</td>
			<td align='center'>";
			if ($user->ID == $user->user_id) {
				echo "<a href='admin.php?page=time_sheets_client_managers&user_id={$user->ID}&action=remove'><img src='{$folder}/time-sheets/x.png' width='15' height='15'></a>";
			}
			echo "</td></tr>";
		}
		echo "</td></tr></table>"; 
	}

	function update_client_manager_list() { 
	
		global $wpdb;
		$db = new time_sheets_db();
		$user = wp_get_current_user();
		$common = new time_sheets_common();

		if ($_GET['action']=='add') {
			$sql = "insert into {$wpdb->prefix}timesheet_manage_client_users (user_id) values (%d)";
			$parms = array(intval($_GET['user_id']));
			$db->query($sql, $parms);
			
			$common->archive_records ('timesheet_manage_client_users', 'timesheet_manage_client_users_archive', 'user_id', intval($_GET['user_id']), '=', 'INSERTED');
			
			
		}

		if ($_GET['action']=='remove') {
			$common->archive_records ('timesheet_manage_client_users', 'timesheet_manage_client_users_archive', 'user_id', intval($_GET['user_id']), '=', 'DELETED');
			
			$sql = "delete from {$wpdb->prefix}timesheet_manage_client_users where user_id = %d";
			$parms = array(intval($_GET['user_id']));
			$db->query($sql, $parms);

		}
		
		echo '<div class="notice notice-success is-dismissible"><p>User updated.</p></div>';
	}

	function client_manager_check() {
		global $wpdb;
		$user = wp_get_current_user();
		$user_id = $user->ID;
		$db = new time_sheets_db();

		$sql = "select count(*) from {$wpdb->prefix}timesheet_manage_client_users where user_id = %d";
		$param = array($user_id);

		$count = $db->get_var($sql, $param);
		
		return $count;
	}
}