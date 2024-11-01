<?php
class time_sheets_db {
	function disable_caching( $wp_query ) {
	   $wp_query->query_vars['cache_results'] = false;
	}

	
	function get_row($sql, $params = NULL) {
		global $wpdb;
		$options = get_option('time_sheets');
		if (isset($options['disable_cacheing'])) {
			add_action( 'parse_query', array($this,'disable_caching') );
		}
		
			
		if ($params == NULL) {
			$var=$wpdb->get_row($sql);
		} else {
			
			$var=$wpdb->get_row(
				$wpdb->prepare($sql,$params)
			);
		}

		return $var;
	}

	function query($sql, $params = NULL){
		global $wpdb;
		$options = get_option('time_sheets');
		if (isset($options['disable_cacheing'])) {
			add_action( 'parse_query', array($this,'disable_caching') );
		}
		
		if ($params == NULL) {
			$wpdb->query($sql);
		} else {
			
			$wpdb->query(
				$wpdb->prepare($sql,$params)
			);
		}

	}

	function get_var($sql, $params = NULL) {
		global $wpdb;
		$options = get_option('time_sheets');
		if (isset($options['disable_cacheing'])) {
			add_action( 'parse_query', array($this,'disable_caching') );
		}
		
		if ($params == NULL) {
			$var = $wpdb->get_var($sql);
		} else {

			$var = $wpdb->get_var(
				$wpdb->prepare($sql,$params)
			);
		}
		return $var;
	}

	function get_results ($sql, $params = NULL) {
		global $wpdb;
		$options = get_option('time_sheets');
		if (isset($options['disable_cacheing'])) {
			add_action( 'parse_query', array($this,'disable_caching') );
		}

		if ($params == NULL) {
			$results = $wpdb->get_results($sql);

		} else {
			
			
			$results = $wpdb->get_results(
				$wpdb->prepare($sql,$params)
			);
		}

		return $results;
	}
}