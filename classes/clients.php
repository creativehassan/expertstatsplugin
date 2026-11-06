<?php
/**
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class wpcable_clients {

	public $tables = array();

	public function __construct() {

		global $wpdb;

		$this->tables = array(
			'transactions' => $wpdb->prefix . 'codeable_transcactions',
			'clients'      => $wpdb->prefix . 'codeable_clients',
			'amounts'      => $wpdb->prefix . 'codeable_amounts',
			'tasks'        => $wpdb->prefix . 'codeable_tasks',
		);

	}

	/**
	 * Legacy clients list built from money transactions.
	 * Returns empty totals when transactions are missing instead of fake zeros-as-fact.
	 *
	 * @param string $from_month Month.
	 * @param string $from_year  Year.
	 * @param string $to_month   Month.
	 * @param string $to_year    Year.
	 * @return array
	 */
	public function get_clients( $from_month = '', $from_year = '', $to_month = '', $to_year = '' ) {

		$clients = array(
			'clients'          => array(),
			'totals'           => array(
				'refunds'   => 0,
				'completed' => 0,
				'subtasks'  => 0,
				'tasks'     => 0,
			),
			'available'        => false,
			'unavailable_reason' => __( 'Money transactions are not imported, so revenue-by-client is unavailable — not $0.', 'wpcable' ),
		);

		$wpcable_stats = new wpcable_stats();
		if ( ! $wpcable_stats->has_transaction_data() ) {
			return $clients;
		}

		$firstdate = '';
		$lastdate  = '';

		// get first and last task if no date is set
		if ( $from_month == '' && $from_year == '' ) {
			$get_first_task = $wpcable_stats->get_first_task();
			if ( empty( $get_first_task['dateadded'] ) || ! wpcable_is_valid_datetime( $get_first_task['dateadded'] ) ) {
				return $clients;
			}
			$firstdate = $get_first_task['dateadded'];
		} else {
			$firstdate = $from_year . '-' . $from_month . '-01';
		}
		if ( $to_month == '' && $to_year == '' ) {
			$get_last_task = $wpcable_stats->get_last_task();
			if ( empty( $get_last_task['dateadded'] ) || ! wpcable_is_valid_datetime( $get_last_task['dateadded'] ) ) {
				return $clients;
			}
			$lastdate = $get_last_task['dateadded'];
		} else {
			$lastdate = $to_year . '-' . $to_month . '-' . date( 't', strtotime( $to_year . '-' . $to_month . '-01 23:59:59' ) );
		}

		global $wpdb;

		$query = $wpdb->prepare(
			'
		  SELECT
			*
		  FROM
			' . $this->tables['transactions'] . '
			LEFT JOIN ' . $this->tables['clients'] . '
		  ON
			' . $this->tables['transactions'] . '.client_id = ' . $this->tables['clients'] . '.client_id
			LEFT JOIN ' . $this->tables['amounts'] . '
		  ON
			' . $this->tables['transactions'] . '.task_ID = ' . $this->tables['amounts'] . '.task_ID
		  WHERE
				( `description` = %s OR `description` = %s )
			AND (dateadded BETWEEN %s AND %s)
		',
			'task_completion',
			'partial_refund',
			$firstdate,
			$lastdate
		);

		// check cache
		$cache_key = 'clients_' . $firstdate . '_' . $lastdate;
		$result    = $this->check_cache( $cache_key, $query );

		if ( ! is_array( $result ) || empty( $result ) ) {
			return $clients;
		}

		$clients['available'] = true;
		$clients['unavailable_reason'] = '';

		// loop transactions
		foreach ( $result as $tr ) {

			if ( empty( $tr['client_id'] ) ) {
				continue;
			}

			// init indexes
			if ( ! isset( $clients['clients'][ $tr['client_id'] ]['total_tasks'] ) ) {
				$clients['clients'][ $tr['client_id'] ]['total_tasks'] = 0;
			}
			if ( ! isset( $clients['clients'][ $tr['client_id'] ]['revenue'] ) ) {
				$clients['clients'][ $tr['client_id'] ]['revenue'] = 0;
			}
			if ( ! isset( $clients['clients'][ $tr['client_id'] ]['tasks'] ) ) {
				$clients['clients'][ $tr['client_id'] ]['tasks'] = 0;
			}
			if ( ! isset( $clients['clients'][ $tr['client_id'] ]['subtasks'] ) ) {
				$clients['clients'][ $tr['client_id'] ]['subtasks'] = 0;
			}

			$clients['clients'][ $tr['client_id'] ]['client_id']       = $tr['client_id'];
			$clients['clients'][ $tr['client_id'] ]['revenue']         = ( strpos( $tr['description'], 'refund' ) !== false ? $clients['clients'][ $tr['client_id'] ]['revenue'] : $clients['clients'][ $tr['client_id'] ]['revenue'] + $tr['credit_revenue_amount'] );
			$clients['clients'][ $tr['client_id'] ]['full_name']       = $tr['full_name'];
			$clients['clients'][ $tr['client_id'] ]['role']            = $tr['role'];
			$clients['clients'][ $tr['client_id'] ]['avatar']          = $tr['large'];
			$clients['clients'][ $tr['client_id'] ]['total_tasks']     = $clients['clients'][ $tr['client_id'] ]['total_tasks'] + 1;
			$clients['clients'][ $tr['client_id'] ]['tasks']           = ( strpos( $tr['task_type'], 'subtask' ) === false ? $clients['clients'][ $tr['client_id'] ]['tasks'] + 1 : $clients['clients'][ $tr['client_id'] ]['tasks'] );
			$clients['clients'][ $tr['client_id'] ]['subtasks']        = ( strpos( $tr['task_type'], 'subtask' ) !== false ? $clients['clients'][ $tr['client_id'] ]['subtasks'] + 1 : $clients['clients'][ $tr['client_id'] ]['subtasks'] );
			$clients['clients'][ $tr['client_id'] ]['last_sign_in_at'] = $tr['last_sign_in_at'];
			$clients['clients'][ $tr['client_id'] ]['timezone_offset'] = $tr['timezone_offset'];

			$clients['totals']['refunds']   = ( strpos( $tr['description'], 'refund' ) !== false ? $clients['totals']['refunds'] + 1 : $clients['totals']['refunds'] );
			$clients['totals']['completed'] = ( strpos( $tr['description'], 'refund' ) === false ? $clients['totals']['completed'] + 1 : $clients['totals']['completed'] );
			$clients['totals']['subtasks']  = ( strpos( $tr['task_type'], 'subtask' ) !== false ? $clients['totals']['subtasks'] + 1 : $clients['totals']['subtasks'] );
			$clients['totals']['tasks']     = ( strpos( $tr['task_type'], 'subtask' ) === false ? $clients['totals']['tasks'] + 1 : $clients['totals']['tasks'] );

			$clients['clients'][ $tr['client_id'] ]['transactions'][] = array(
				'id'             => $tr['id'],
				'description'    => $tr['description'],
				'dateadded'      => $tr['dateadded'],
				'fee_percentage' => $tr['fee_percentage'],
				'fee_amount'     => $tr['fee_amount'],
				'task_type'      => $tr['task_type'],
				'task_id'        => $tr['task_id'],
				'task_title'     => $tr['task_title'],
				'parent_task_id' => $tr['parent_task_id'],
				'preferred'      => $tr['preferred'],
				'pro'            => $tr['pro'],
				'revenue'        => $tr['credit_revenue_amount'],
				'is_refund'      => ( strpos( $tr['description'], 'refund' ) !== false ? 1 : 0 ),
			);
		}

		return $clients;

	}

	/**
	 * Clients workspace: join clients to their imported projects (not revenue).
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function get_client_workspace( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'page'      => 1,
			'per_page'  => 25,
			'search'    => '',
			'filter'    => 'all', // all|completed|preferred|stale|lost
			'client_id' => 0,
			'orderby'   => 'last_activity',
			'order'     => 'DESC',
			'stale_days'=> 180,
		);
		$args     = array_merge( $defaults, $args );
		$page     = max( 1, (int) $args['page'] );
		$per_page = (int) $args['per_page'];
		if ( $per_page < 1 || $per_page > 500 ) {
			$per_page = 25;
		}

		$where  = array( '1=1' );
		$params = array();
		$having = array();

		$client_id = (int) $args['client_id'];
		if ( $client_id > 0 ) {
			$where[]  = 'task.client_id = %d';
			$params[] = $client_id;
		}

		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( client.full_name LIKE %s OR CAST(task.client_id AS CHAR) LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$filter = (string) $args['filter'];
		if ( 'completed' === $filter ) {
			$having[] = 'SUM( CASE WHEN task.flag = \'completed\' THEN 1 ELSE 0 END ) >= 1';
		} elseif ( 'preferred' === $filter ) {
			$having[] = 'SUM( CASE WHEN task.preferred = 1 THEN 1 ELSE 0 END ) >= 1';
		} elseif ( 'lost' === $filter ) {
			$having[] = 'SUM( CASE WHEN task.flag = \'lost\' THEN 1 ELSE 0 END ) >= 1';
		} elseif ( 'stale' === $filter ) {
			$cutoff     = time() - ( (int) $args['stale_days'] * DAY_IN_SECONDS );
			$having[]   = 'MAX(task.last_activity) > 0 AND MAX(task.last_activity) < %d';
			$params[]   = $cutoff;
		}

		$where_sql  = implode( ' AND ', $where );
		$having_sql = $having ? ( ' HAVING ' . implode( ' AND ', $having ) ) : '';

		$from_sql = "
			FROM `{$this->tables['tasks']}` AS task
			LEFT JOIN `{$this->tables['clients']}` AS client
				ON client.client_id = task.client_id
			WHERE {$where_sql}
			GROUP BY task.client_id
			{$having_sql}
		";

		$count_sql = 'SELECT COUNT(*) FROM ( SELECT task.client_id ' . $from_sql . ' ) AS grouped_clients';
		if ( $params ) {
			$count_sql = $wpdb->prepare( $count_sql, $params );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		$order_map = array(
			'last_activity' => 'last_activity',
			'full_name'     => 'full_name',
			'task_count'    => 'task_count',
			'completed'     => 'completed_count',
		);
		$orderby = isset( $order_map[ $args['orderby'] ] ) ? $order_map[ $args['orderby'] ] : 'last_activity';
		$order   = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';
		$offset  = ( $page - 1 ) * $per_page;

		$list_sql = "
			SELECT
				task.client_id AS client_id,
				client.full_name AS full_name,
				client.role AS role,
				client.last_sign_in_at AS last_sign_in_at,
				client.timezone_offset AS timezone_offset,
				client.pro AS pro,
				client.medium AS avatar,
				COUNT(task.task_id) AS task_count,
				SUM( CASE WHEN task.flag = 'completed' THEN 1 ELSE 0 END ) AS completed_count,
				SUM( CASE WHEN task.preferred = 1 THEN 1 ELSE 0 END ) AS preferred_count,
				SUM( CASE WHEN task.flag = 'lost' THEN 1 ELSE 0 END ) AS lost_count,
				SUM( CASE WHEN task.state = 'published' THEN 1 ELSE 0 END ) AS published_count,
				SUM( CASE WHEN task.notes IS NOT NULL AND task.notes <> '' THEN 1 ELSE 0 END ) AS notes_count,
				MAX(task.last_activity) AS last_activity
			{$from_sql}
			ORDER BY {$orderby} {$order}, task.client_id {$order}
			LIMIT %d OFFSET %d
		";

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$list_sql    = $wpdb->prepare( $list_sql, $list_params );
		$rows        = $wpdb->get_results( $list_sql, ARRAY_A );
		$items       = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$items[] = $this->hydrate_client_row( $row, ! empty( $args['with_tasks'] ) || $client_id > 0 );
			}
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * One client with projects, stats, and evidence inventory.
	 *
	 * @param int $client_id Client ID.
	 * @return array|null
	 */
	public function get_client_detail( $client_id ) {
		$client_id = (int) $client_id;
		if ( $client_id <= 0 ) {
			return null;
		}

		$result = $this->get_client_workspace(
			array(
				'client_id'  => $client_id,
				'per_page'   => 1,
				'page'       => 1,
				'with_tasks' => true,
			)
		);

		if ( empty( $result['items'][0] ) ) {
			return null;
		}

		return $result['items'][0];
	}

	/**
	 * Expand a grouped client row.
	 *
	 * @param array $row        SQL row.
	 * @param bool  $with_tasks Attach matching tasks.
	 * @return array
	 */
	private function hydrate_client_row( $row, $with_tasks = false ) {
		$client_id = (int) $row['client_id'];
		$last_act  = isset( $row['last_activity'] ) ? (int) $row['last_activity'] : 0;
		$stale     = ( $last_act > 0 ) ? (int) floor( ( time() - $last_act ) / DAY_IN_SECONDS ) : 0;

		$client = array(
			'client_id'       => $client_id,
			'full_name'       => ! empty( $row['full_name'] ) ? $row['full_name'] : sprintf(
				/* translators: %d: client id */
				__( 'Unknown client (%d)', 'wpcable' ),
				$client_id
			),
			'role'            => isset( $row['role'] ) ? $row['role'] : '',
			'last_sign_in_at' => isset( $row['last_sign_in_at'] ) ? $row['last_sign_in_at'] : '',
			'timezone_offset' => isset( $row['timezone_offset'] ) ? $row['timezone_offset'] : '',
			'pro'             => ! empty( $row['pro'] ),
			'avatar'          => isset( $row['avatar'] ) ? $row['avatar'] : '',
			'preferred'       => (int) $row['preferred_count'] > 0,
			'task_stats'      => array(
				'total'       => (int) $row['task_count'],
				'completed'   => (int) $row['completed_count'],
				'preferred'   => (int) $row['preferred_count'],
				'lost'        => (int) $row['lost_count'],
				'published'   => (int) $row['published_count'],
				'with_notes'  => (int) $row['notes_count'],
			),
			'last_activity'   => $last_act,
			'stale_days'      => $stale,
			'tasks'           => array(),
			'financial'       => array(
				'available' => false,
				'label'     => __( 'Financial revenue is blocked until money transactions import successfully. Missing is not $0.', 'wpcable' ),
			),
			'evidence'        => $this->evidence_inventory(),
		);

		$stats = new wpcable_stats();
		if ( $stats->has_transaction_data() ) {
			$client['financial'] = array(
				'available' => true,
				'label'     => __( 'Revenue figures come from imported money transactions.', 'wpcable' ),
			);
		}

		if ( $with_tasks ) {
			$task_repo          = new wpcable_tasks();
			$task_query         = $task_repo->query_tasks(
				array(
					'client_id' => $client_id,
					'per_page'  => 100,
					'page'      => 1,
					'state'     => 'all',
				)
			);
			$client['tasks']    = $task_query['items'];
			$client['evidence'] = $this->evidence_inventory( $client, $client['tasks'] );
		}

		return $client;
	}

	/**
	 * Honest inventory of what is / is not in local records.
	 *
	 * @param array $client Client.
	 * @param array $tasks  Tasks.
	 * @return array
	 */
	public function evidence_inventory( $client = array(), $tasks = array() ) {
		$available = array(
			__( 'Project title, state, kind, and flags', 'wpcable' ),
			__( 'Preferred / promoted / favored / subscribed flags', 'wpcable' ),
			__( 'Last activity timestamp and last_activity_by when present', 'wpcable' ),
			__( 'Client profile fields stored locally (name, role, timezone, avatar)', 'wpcable' ),
			__( 'Your local notes (editable here; not written back to Codeable)', 'wpcable' ),
			__( 'Workroom link (opens Codeable in a new tab, read-only from this plugin)', 'wpcable' ),
		);

		$partial = array(
			__( 'Contractor / client prices when the tasks import stored them — shown as an em dash when empty, never as $0.00 by default', 'wpcable' ),
			__( 'Profile conversion percentages from users/me, which are a different scope than local imported rows', 'wpcable' ),
		);

		$unverified = array(
			__( 'Workroom chat threads', 'wpcable' ),
			__( 'Full task description body', 'wpcable' ),
			__( 'Attached files', 'wpcable' ),
			__( 'Per-task star ratings', 'wpcable' ),
			__( 'Client website URL', 'wpcable' ),
			__( 'Whether another expert was hired globally', 'wpcable' ),
		);

		$price_present = false;
		foreach ( $tasks as $task ) {
			if ( ! empty( $task['value_known'] ) ) {
				$price_present = true;
				break;
			}
		}

		return array(
			'available'    => $available,
			'partial'      => $partial,
			'unverified'   => $unverified,
			'prices_present' => $price_present,
		);
	}


	public function standard_deviation( $sample ) {
		if ( is_array( $sample ) && count( $sample ) > 1 ) {
			$mean = array_sum( $sample ) / count( $sample );
			foreach ( $sample as $key => $num ) {
				$devs[ $key ] = pow( $num - $mean, 2 );
			}

			return sqrt( array_sum( $devs ) / ( count( $devs ) - 1 ) );
		}
	}

	/**
	 * Checks and sets cached data
	 *
	 * @since   0.0.6
	 * @author Justin Frydman
	 *
	 * @param bool $key     The unique cache key
	 * @param bool $query   The query to check
	 *
	 * @return mixed    The raw or cached data
	 * @throws Exception
	 */
	private function check_cache( $key = false, $query = false ) {

		$cache = new wpcable_cache( $key, $query );
		return $cache->check();

	}

}
