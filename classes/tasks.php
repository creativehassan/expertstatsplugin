<?php
/**
 * Backend logic to manage tasks.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class wpcable_tasks {


	/**
	 * List of used DB tables, with prefix.
	 *
	 * @var array
	 */
	public $tables = [];

	/**
	 * Initialize internal properties.
	 */
	public function __construct() {
		global $wpdb;

		$this->tables = [
			'tasks'   => $wpdb->prefix . 'codeable_tasks',
			'clients' => $wpdb->prefix . 'codeable_clients',
		];
	}

	/**
	 * Returns a list of all tasks.
	 *
	 * @return array
	 */
	public function get_tasks() {
		$query = "
			SELECT
				task.*,
				client.full_name AS `client_name`,
				client.medium AS `avatar`
			FROM
				`{$this->tables['tasks']}` AS task
			LEFT JOIN `{$this->tables['clients']}` AS client
				ON client.client_id = task.client_id
			ORDER BY task.last_activity DESC, task.task_id DESC
		";

		// Check cache.
		$cache_key = 'tasks_list';
		$result    = $this->check_cache( $cache_key, $query );

		if ( ! is_array( $result ) ) {
			$result = [];
		}

		$result = array_map( [ $this, 'sanitize_task' ], $result );

		return $result;
	}

	/**
	 * Shared filter args so KPI / toolbar / table COUNT use the same SQL.
	 *
	 * @return array
	 */
	private function query_defaults() {
		return [
			'page'            => 1,
			'per_page'        => 25,
			'search'          => '',
			'state'           => 'all',
			'flag'            => '',
			'preferred'       => false,
			'promoted'        => false,
			'favored'         => false,
			'subscribed'      => false,
			'no_hidden'       => false,
			'has_notes'       => false,
			'paid'            => false,
			'estimated'       => false,
			'lost'            => false,
			'completed'       => false,
			'client_id'       => 0,
			'group_by_client' => false,
			'orderby'         => 'last_activity',
			'order'           => 'DESC',
		];
	}

	/**
	 * WHERE clause + params matching query_tasks filters.
	 *
	 * @param array $args Query args already merged with defaults.
	 * @return array { 0: where_sql, 1: params, 2: client_id }
	 */
	private function task_filter_sql( $args ) {
		global $wpdb;

		$where  = [ '1=1' ];
		$params = [];

		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( task.title LIKE %s OR CAST(task.task_id AS CHAR) LIKE %s OR client.full_name LIKE %s OR task.notes LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$state = (string) $args['state'];
		if ( 'lost' === $state ) {
			$where[]  = '( task.state = %s OR task.flag = %s )';
			$params[] = 'lost';
			$params[] = 'lost';
		} elseif ( 'all' !== $state && in_array( $state, wpcable_known_task_states(), true ) ) {
			$where[]  = 'task.state = %s AND task.flag <> %s';
			$params[] = $state;
			$params[] = 'lost';
		}

		if ( ! empty( $args['flag'] ) ) {
			$where[]  = 'task.flag = %s';
			$params[] = sanitize_key( $args['flag'] );
		}

		if ( ! empty( $args['preferred'] ) ) {
			$where[] = 'task.preferred = 1';
		}
		if ( ! empty( $args['promoted'] ) ) {
			$where[] = 'task.promoted = 1';
		}
		if ( ! empty( $args['favored'] ) ) {
			$where[] = 'task.favored = 1';
		}
		if ( ! empty( $args['subscribed'] ) ) {
			$where[] = 'task.subscribed = 1';
		}
		if ( ! empty( $args['no_hidden'] ) ) {
			$where[] = 'task.hidden = 0';
		}
		if ( ! empty( $args['has_notes'] ) ) {
			$where[] = "task.notes IS NOT NULL AND task.notes <> ''";
		}
		if ( ! empty( $args['paid'] ) ) {
			$where[] = "task.state = 'paid'";
		}
		if ( ! empty( $args['estimated'] ) ) {
			$where[] = "( task.state = 'estimated' OR task.flag = 'estimated' OR task.estimate = 1 )";
		}
		if ( ! empty( $args['lost'] ) ) {
			$where[] = "( task.state = 'lost' OR task.flag = 'lost' )";
		}
		if ( ! empty( $args['completed'] ) ) {
			$where[] = "( task.state = 'completed' OR task.flag = 'completed' )";
		}

		$client_id = (int) $args['client_id'];
		if ( $client_id > 0 ) {
			$where[]  = 'task.client_id = %d';
			$params[] = $client_id;
		}

		return [ implode( ' AND ', $where ), $params, $client_id ];
	}

	/**
	 * COUNT(*) using the same WHERE as query_tasks.
	 *
	 * @param array $args Filter args.
	 * @return int
	 */
	public function count_matching( $args = [] ) {
		global $wpdb;

		$args = array_merge( $this->query_defaults(), $args );
		list( $where_sql, $params ) = $this->task_filter_sql( $args );

		$sql = "SELECT COUNT(*) FROM `{$this->tables['tasks']}` AS task
			LEFT JOIN `{$this->tables['clients']}` AS client
				ON client.client_id = task.client_id
			WHERE {$where_sql}";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Toolbar / KPI counts. Each key uses the same SQL as that filter button.
	 *
	 * @return array
	 */
	public function get_filter_counts() {
		return [
			'all'        => $this->count_matching( [ 'state' => 'all' ] ),
			'published'  => $this->count_matching( [ 'state' => 'published' ] ),
			'completed'  => $this->count_matching( [ 'completed' => true ] ),
			'paid'       => $this->count_matching( [ 'paid' => true ] ),
			'estimated'  => $this->count_matching( [ 'estimated' => true ] ),
			'lost'       => $this->count_matching( [ 'lost' => true ] ),
			'preferred'  => $this->count_matching( [ 'preferred' => true ] ),
			'with_notes' => $this->count_matching( [ 'has_notes' => true ] ),
		];
	}

	/**
	 * Server-side task query with pagination, search, state/flag filters, and optional client grouping.
	 *
	 * @param array $args Query args.
	 * @return array { items, total, page, per_page, grouped, counts }
	 */
	public function query_tasks( $args = [] ) {
		global $wpdb;

		$args     = array_merge( $this->query_defaults(), $args );
		$page     = max( 1, (int) $args['page'] );
		$per_page = (int) $args['per_page'];
		if ( ! in_array( $per_page, [ 25, 50, 100 ], true ) ) {
			$per_page = 25;
		}

		list( $where_sql, $params, $client_id ) = $this->task_filter_sql( $args );

		$allowed_orderby = [
			'last_activity' => 'task.last_activity',
			'task_id'       => 'task.task_id',
			'value'         => 'task.value',
			'title'         => 'task.title',
			'client_name'   => 'client.full_name',
			'state'         => 'task.state',
		];
		$orderby = isset( $allowed_orderby[ $args['orderby'] ] ) ? $allowed_orderby[ $args['orderby'] ] : 'task.last_activity';
		$order   = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$join = "FROM `{$this->tables['tasks']}` AS task
			LEFT JOIN `{$this->tables['clients']}` AS client
				ON client.client_id = task.client_id
			WHERE {$where_sql}";

		$count_sql = "SELECT COUNT(*) {$join}";
		if ( $params ) {
			$count_sql = $wpdb->prepare( $count_sql, $params );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		$grouped = ! empty( $args['group_by_client'] ) && $client_id <= 0;
		$items   = [];

		if ( $grouped ) {
			$client_count_sql = "SELECT COUNT(DISTINCT task.client_id) {$join}";
			if ( $params ) {
				$client_count_sql = $wpdb->prepare( $client_count_sql, $params );
			}
			$total_clients = (int) $wpdb->get_var( $client_count_sql );
			$offset        = ( $page - 1 ) * $per_page;

			$client_ids_sql = "SELECT task.client_id AS client_id, MAX(task.last_activity) AS last_activity
				{$join}
				GROUP BY task.client_id
				ORDER BY last_activity {$order}, task.client_id {$order}
				LIMIT %d OFFSET %d";
			$client_params  = array_merge( $params, [ $per_page, $offset ] );
			$client_ids_sql = $wpdb->prepare( $client_ids_sql, $client_params );
			$client_rows    = $wpdb->get_results( $client_ids_sql, ARRAY_A );

			$ids = [];
			if ( is_array( $client_rows ) ) {
				foreach ( $client_rows as $row ) {
					$ids[] = (int) $row['client_id'];
				}
			}

			if ( $ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$list_sql     = "SELECT task.*, client.full_name AS client_name, client.medium AS avatar
					FROM `{$this->tables['tasks']}` AS task
					LEFT JOIN `{$this->tables['clients']}` AS client
						ON client.client_id = task.client_id
					WHERE task.client_id IN ({$placeholders})";
				// Re-apply the same non-client filters so grouped pages stay consistent.
				$filter_sql = preg_replace( '/task\.client_id = %d/', '1=1', $where_sql );
				if ( $filter_sql && '1=1' !== $filter_sql ) {
					$list_sql .= ' AND (' . $where_sql . ')';
					$list_params = array_merge( $ids, $params );
				} else {
					$list_params = $ids;
				}
				$list_sql .= " ORDER BY client.full_name ASC, task.last_activity {$order}, task.task_id {$order}";
				$list_sql  = $wpdb->prepare( $list_sql, $list_params );
				$raw       = $wpdb->get_results( $list_sql, ARRAY_A );
				$items     = is_array( $raw ) ? array_map( [ $this, 'sanitize_task' ], $raw ) : [];
			}

			return [
				'items'           => $items,
				'total'           => $total,
				'total_clients'   => $total_clients,
				'page'            => $page,
				'per_page'        => $per_page,
				'group_by_client' => true,
				'counts'          => $this->get_state_counts(),
				'filter_counts'   => $this->get_filter_counts(),
				'sync'            => $this->sync_payload(),
			];
		}

		$offset   = ( $page - 1 ) * $per_page;
		$list_sql = "SELECT task.*, client.full_name AS client_name, client.medium AS avatar
			{$join}
			ORDER BY {$orderby} {$order}, task.task_id {$order}
			LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, [ $per_page, $offset ] );
		$list_sql    = $wpdb->prepare( $list_sql, $list_params );
		$raw         = $wpdb->get_results( $list_sql, ARRAY_A );
		$items       = is_array( $raw ) ? array_map( [ $this, 'sanitize_task' ], $raw ) : [];

		return [
			'items'           => $items,
			'total'           => $total,
			'page'            => $page,
			'per_page'        => $per_page,
			'group_by_client' => false,
			'counts'          => $this->get_state_counts(),
			'filter_counts'   => $this->get_filter_counts(),
			'sync'            => $this->sync_payload(),
		];
	}

	/**
	 * In-progress Refresh state for the workspace UI. Read-only metadata.
	 *
	 * @return array
	 */
	private function sync_payload() {
		$step = wpcable_sync_current_step();
		return [
			'running' => wpcable_sync_is_running(),
			'step'    => $step,
			'source'  => $step && ! empty( $step['task'] ) ? (string) $step['task'] : '',
		];
	}

	/**
	 * Single task with client fields.
	 *
	 * @param int $task_id Task ID.
	 * @return array|null
	 */
	public function get_task( $task_id ) {
		global $wpdb;

		$task_id = (int) $task_id;
		if ( $task_id <= 0 ) {
			return null;
		}

		$sql = $wpdb->prepare(
			"SELECT task.*, client.full_name AS client_name, client.medium AS avatar,
				client.role AS client_role, client.last_sign_in_at AS client_last_sign_in_at,
				client.timezone_offset AS timezone_offset, client.pro AS client_pro
			FROM `{$this->tables['tasks']}` AS task
			LEFT JOIN `{$this->tables['clients']}` AS client
				ON client.client_id = task.client_id
			WHERE task.task_id = %d
			LIMIT 1",
			$task_id
		);

		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( ! $row ) {
			return null;
		}

		return $this->sanitize_task( $row );
	}

	/**
	 * KPI counts computed from the local tasks table. Never hardcoded.
	 *
	 * @return array
	 */
	public function get_state_counts() {
		global $wpdb;

		$sql    = "SELECT state, COUNT(*) AS total FROM `{$this->tables['tasks']}` GROUP BY state";
		$states = $wpdb->get_results( $sql, ARRAY_A );

		$flag_sql = "SELECT flag, COUNT(*) AS total FROM `{$this->tables['tasks']}` GROUP BY flag";
		$flags    = $wpdb->get_results( $flag_sql, ARRAY_A );

		$counts = [
			'imported'  => 0,
			'published' => 0,
			'estimated' => 0,
			'hired'     => 0,
			'paid'      => 0,
			'completed' => 0,
			'refunded'  => 0,
			'canceled'  => 0,
			'lost'      => 0,
			'flag_completed' => 0,
			'flag_lost'      => 0,
			'preferred'      => 0,
			'with_notes'     => 0,
		];

		if ( is_array( $states ) ) {
			foreach ( $states as $row ) {
				$key = isset( $row['state'] ) ? $row['state'] : '';
				$num = isset( $row['total'] ) ? (int) $row['total'] : 0;
				$counts['imported'] += $num;
				if ( isset( $counts[ $key ] ) ) {
					$counts[ $key ] = $num;
				}
			}
		}

		if ( is_array( $flags ) ) {
			foreach ( $flags as $row ) {
				if ( 'completed' === $row['flag'] ) {
					$counts['flag_completed'] = (int) $row['total'];
				}
				if ( 'lost' === $row['flag'] ) {
					$counts['flag_lost'] = (int) $row['total'];
				}
			}
		}

		$counts['preferred'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM `{$this->tables['tasks']}` WHERE preferred = 1"
		);
		$counts['with_notes'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM `{$this->tables['tasks']}` WHERE notes IS NOT NULL AND notes <> ''"
		);

		$filter = $this->get_filter_counts();
		$counts['filter']            = $filter;
		$counts['filter_published']  = $filter['published'];
		$counts['filter_completed']  = $filter['completed'];
		$counts['filter_lost']       = $filter['lost'];
		$counts['imported']          = $filter['all'];

		return $counts;
	}

	/**
	 * Latest last_sync among tasks, or 0.
	 *
	 * @return int
	 */
	public function get_last_sync() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT MAX(last_sync) FROM `{$this->tables['tasks']}`" );
	}

	/**
	 * Takes a single DB row as input and sanitizes some values.
	 *
	 * @param  array $task Raw DB value.
	 * @return array Sanitized task.
	 */
	public function sanitize_task( $task ) {
		if ( ! is_array( $task ) ) {
			return [];
		}

		$timezone = get_option( 'timezone_string' );
		if ( $timezone ) {
			date_default_timezone_set( $timezone );
		}

		$task['estimate']      = (bool) $task['estimate'];
		$task['hidden']        = (bool) $task['hidden'];
		$task['promoted']      = (bool) $task['promoted'];
		$task['subscribed']    = (bool) $task['subscribed'];
		$task['favored']       = (bool) $task['favored'];
		$task['preferred']     = (bool) $task['preferred'];
		$task['client_fee']    = (float) $task['client_fee'];
		$task['value']         = (float) $task['value'];
		$task['value_client']  = (float) $task['value_client'];
		$raw_activity          = isset( $task['last_activity'] ) ? $task['last_activity'] : 0;
		$task['last_activity'] = wpcable_normalize_timestamp( $raw_activity );
		$task['activity_valid'] = $task['last_activity'] > 0;
		$task['stale_days']    = wpcable_stale_days( $task['last_activity'] );
		$task['last_sync']     = (int) $task['last_sync'];
		$task['task_id']       = (int) $task['task_id'];
		$task['client_id']     = (int) $task['client_id'];
		$task['notes']         = isset( $task['notes'] ) ? (string) $task['notes'] : '';
		$task['title']         = isset( $task['title'] ) ? (string) $task['title'] : '';
		$task['client_name']   = isset( $task['client_name'] ) && $task['client_name']
			? (string) $task['client_name']
			: sprintf(
				/* translators: %d: client id */
				__( 'Unknown client (%d)', 'wpcable' ),
				$task['client_id']
			);
		$task['avatar']        = isset( $task['avatar'] ) ? (string) $task['avatar'] : '';
		$task['has_notes']     = '' !== trim( $task['notes'] );
		$task['value_known']   = ( $task['value'] > 0 || $task['value_client'] > 0 );
		$task['workroom_url']  = wpcable_workroom_url( $task['task_id'] );
		$task['history']       = wpcable_task_history_unavailable( $task );

		$task['last_activity_date'] = '';
		$task['last_activity_time'] = '';
		if ( $task['activity_valid'] ) {
			$task['last_activity_date'] = date_i18n(
				get_option( 'date_format' ),
				$task['last_activity']
			);
			$task['last_activity_time'] = date_i18n(
				get_option( 'time_format' ),
				$task['last_activity'],
				false
			);
		}

		return apply_filters( 'wpcable_sanitize_task', $task );
	}

	/**
	 * Update the specified task in the DB.
	 * Only local-editable fields (notes/flag/state). Does not write to Codeable.
	 *
	 * @param array $task Task payload.
	 */
	public function update_task( $task ) {
		global $wpdb;
		$wpdb->show_errors();

		if ( ! is_array( $task ) || empty( $task['task_id'] ) ) {
			return;
		}

		$valid_fields = [
			'task_id' => '',
			'state'   => '',
			'notes'   => '',
			'flag'    => '',
		];

		$task = array_intersect_key( $task, $valid_fields );
		$task['task_id'] = (int) $task['task_id'];

		$wpdb->update(
			$this->tables['tasks'],
			$task,
			[ 'task_id' => $task['task_id'] ]
		);
	}

	/**
	 * Checks and sets cached data
	 *
	 * @since   0.0.6
	 * @author Justin Frydman
	 *
	 * @param bool $key     The unique cache key.
	 * @param bool $query   The query to check.
	 *
	 * @return mixed    The raw or cached data.
	 */
	private function check_cache( $key = false, $query = false ) {
		$cache = new wpcable_cache( $key, $query );
		return $cache->check();
	}
}
