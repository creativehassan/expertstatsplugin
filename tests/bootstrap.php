<?php
/**
 * Offline WordPress stubs + SQLite $wpdb for plugin tests.
 * No live Codeable calls, no credentials.
 *
 * @package wpcable
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', sys_get_temp_dir() . '/wpcable-tests/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'WPCABLE_TEST_ROOT', dirname( __DIR__ ) );

$wpcable_options = array();
$wpcable_filters = array();
$wpcable_assertions = array( 'pass' => 0, 'fail' => 0, 'failures' => array() );

function wpcable_test_option_store() {
	global $wpcable_options;
	return $wpcable_options;
}

function get_option( $key, $default = false ) {
	global $wpcable_options;
	return array_key_exists( $key, $wpcable_options ) ? $wpcable_options[ $key ] : $default;
}

function update_option( $key, $value ) {
	global $wpcable_options;
	$wpcable_options[ $key ] = $value;
	return true;
}

function delete_option( $key ) {
	global $wpcable_options;
	unset( $wpcable_options[ $key ] );
	return true;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function _e( $text, $domain = 'default' ) {
	echo $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html__( $text, $domain );
}

function esc_attr__( $text, $domain = 'default' ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $text ) {
	return $text;
}

function sanitize_key( $key ) {
	$key = strtolower( $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function sanitize_text_field( $text ) {
	return trim( strip_tags( $text ) );
}

function wp_unslash( $value ) {
	return $value;
}

function apply_filters( $tag, $value ) {
	return $value;
}

function date_i18n( $format, $timestamp = false ) {
	return date( $format, false === $timestamp ? time() : $timestamp );
}

function wp_timezone() {
	$string = get_option( 'timezone_string' );
	if ( is_string( $string ) && '' !== $string ) {
		return new DateTimeZone( $string );
	}
	$offset  = (float) get_option( 'gmt_offset', 0 );
	$hours   = (int) $offset;
	$minutes = (int) round( abs( $offset - $hours ) * 60 );
	$sign    = $offset < 0 ? '-' : '+';
	return new DateTimeZone( sprintf( '%s%02d%02d', $sign, abs( $hours ), $minutes ) );
}

function wp_date( $format, $timestamp = null, $timezone = null ) {
	if ( null === $timestamp ) {
		$timestamp = time();
	}
	if ( ! $timezone instanceof DateTimeZone ) {
		$timezone = wp_timezone();
	}
	$dt = new DateTime( '@' . (int) $timestamp );
	$dt->setTimezone( $timezone );
	return $dt->format( $format );
}

function number_format_i18n( $number ) {
	return number_format( (float) $number );
}

function current_user_can( $cap ) {
	return 'manage_options' === $cap;
}

function wp_verify_nonce( $nonce, $action ) {
	return ! empty( $nonce );
}

function wp_create_nonce( $action ) {
	return 'test-nonce';
}

function wp_cache_get( $key ) {
	return false;
}

function wp_cache_set( $key, $value ) {
	return true;
}

function wp_cache_incr( $key ) {
	return 1;
}

function is_ssl() {
	return true;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function plugins_url( $path = '', $plugin = '' ) {
	return 'https://example.test/wp-content/plugins/expertstatsplugin/' . ltrim( $path, '/' );
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

/**
 * Minimal $wpdb using SQLite.
 */
class wpcable_test_wpdb {
	public $prefix = 'wp_';
	public $last_error = '';
	public $insert_id = 0;
	public $show_errors_flag = false;
	private $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->create_schema();
	}

	public function show_errors() {
		$this->show_errors_flag = true;
	}

	private function create_schema() {
		$this->pdo->exec(
			'CREATE TABLE wp_codeable_tasks (
				task_id INTEGER PRIMARY KEY,
				client_id INTEGER NOT NULL,
				title TEXT NOT NULL,
				estimate INTEGER DEFAULT 0,
				hidden INTEGER DEFAULT 0,
				promoted INTEGER DEFAULT 0,
				subscribed INTEGER DEFAULT 0,
				favored INTEGER DEFAULT 0,
				preferred INTEGER DEFAULT 0,
				client_fee REAL DEFAULT 17.5,
				state TEXT DEFAULT "",
				kind TEXT DEFAULT "",
				value REAL DEFAULT 0,
				value_client REAL DEFAULT 0,
				last_activity INTEGER DEFAULT 0,
				last_activity_by TEXT DEFAULT "",
				last_sync INTEGER DEFAULT 0,
				flag TEXT DEFAULT "",
				notes TEXT DEFAULT ""
			)'
		);
		$this->pdo->exec(
			'CREATE TABLE wp_codeable_clients (
				client_id INTEGER PRIMARY KEY,
				full_name TEXT NOT NULL,
				role TEXT,
				last_sign_in_at TEXT,
				pro INTEGER,
				timezone_offset INTEGER,
				tiny TEXT,
				small TEXT,
				medium TEXT,
				large TEXT,
				last_sync INTEGER DEFAULT 0
			)'
		);
		$this->pdo->exec(
			'CREATE TABLE wp_codeable_transcactions (
				id INTEGER PRIMARY KEY,
				description TEXT,
				dateadded TEXT,
				fee_percentage REAL,
				fee_amount REAL,
				task_type TEXT,
				task_id INTEGER,
				task_title TEXT,
				parent_task_id INTEGER,
				preferred INTEGER,
				client_id INTEGER,
				last_sync INTEGER DEFAULT 0
			)'
		);
		$this->pdo->exec(
			'CREATE TABLE wp_codeable_amounts (
				task_id INTEGER PRIMARY KEY,
				client_id INTEGER,
				credit_revenue_id INTEGER,
				credit_revenue_amount INTEGER,
				credit_fee_id INTEGER,
				credit_fee_amount INTEGER,
				credit_user_id INTEGER,
				credit_user_amount INTEGER,
				debit_cost_id INTEGER,
				debit_cost_amount INTEGER,
				debit_user_id INTEGER,
				debit_user_amount INTEGER
			)'
		);
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	public function prepare( $query, $args = null ) {
		if ( null === $args ) {
			return $query;
		}
		$params = func_get_args();
		array_shift( $params );
		if ( isset( $params[0] ) && is_array( $params[0] ) ) {
			$params = $params[0];
		}
		foreach ( $params as $param ) {
			$query = preg_replace_callback(
				'/%[dfs]/',
				function () use ( $param ) {
					if ( is_int( $param ) || is_float( $param ) ) {
						return (string) $param;
					}
					return $this->pdo->quote( (string) $param );
				},
				$query,
				1
			);
		}
		return $query;
	}

	public function get_results( $query, $output = ARRAY_A ) {
		try {
			$stmt = $this->pdo->query( $this->mysql_to_sqlite( $query ) );
			$rows = $stmt->fetchAll( PDO::FETCH_ASSOC );
			if ( ARRAY_A === $output ) {
				return $rows;
			}
			$objects = array();
			foreach ( $rows as $row ) {
				$objects[] = (object) $row;
			}
			return $objects;
		} catch ( Exception $e ) {
			$this->last_error = $e->getMessage();
			throw $e;
		}
	}

	public function get_var( $query ) {
		$stmt = $this->pdo->query( $this->mysql_to_sqlite( $query ) );
		$value = $stmt->fetchColumn();
		return false === $value ? null : $value;
	}

	public function get_row( $query, $output = ARRAY_A ) {
		$stmt = $this->pdo->query( $this->mysql_to_sqlite( $query ) );
		$row  = $stmt->fetch( PDO::FETCH_ASSOC );
		if ( ! $row ) {
			return null;
		}
		return ARRAY_A === $output ? $row : (object) $row;
	}

	public function query( $query ) {
		return $this->pdo->exec( $this->mysql_to_sqlite( $query ) );
	}

	public function insert( $table, $data ) {
		$cols = array_keys( $data );
		$placeholders = implode( ',', array_fill( 0, count( $cols ), '?' ) );
		$sql = 'INSERT INTO ' . $table . ' (' . implode( ',', $cols ) . ') VALUES (' . $placeholders . ')';
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute( array_values( $data ) );
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return 1;
	}

	public function update( $table, $data, $where ) {
		$set = array();
		$values = array();
		foreach ( $data as $k => $v ) {
			$set[] = "$k = ?";
			$values[] = $v;
		}
		$w = array();
		foreach ( $where as $k => $v ) {
			$w[] = "$k = ?";
			$values[] = $v;
		}
		$sql = 'UPDATE ' . $table . ' SET ' . implode( ',', $set ) . ' WHERE ' . implode( ' AND ', $w );
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute( $values );
		return $stmt->rowCount();
	}

	public function print_error() {
		return $this->last_error;
	}

	private function mysql_to_sqlite( $sql ) {
		$sql = str_replace( '`', '"', $sql );
		$sql = str_replace( 'CAST(task.task_id AS CHAR)', 'CAST(task.task_id AS TEXT)', $sql );
		$sql = str_replace( 'CAST(task.client_id AS CHAR)', 'CAST(task.client_id AS TEXT)', $sql );
		return $sql;
	}
}

function wpcable_assert( $condition, $message ) {
	global $wpcable_assertions;
	if ( $condition ) {
		$wpcable_assertions['pass']++;
		echo "  PASS  $message\n";
	} else {
		$wpcable_assertions['fail']++;
		$wpcable_assertions['failures'][] = $message;
		echo "  FAIL  $message\n";
	}
}

function wpcable_assert_equals( $expected, $actual, $message ) {
	$ok = $expected === $actual;
	if ( ! $ok ) {
		$message .= ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')';
	}
	wpcable_assert( $ok, $message );
}

function wpcable_load_plugin_classes() {
	require_once WPCABLE_TEST_ROOT . '/classes/object_cache.php';
	require_once WPCABLE_TEST_ROOT . '/functions/helpers.php';
	require_once WPCABLE_TEST_ROOT . '/classes/import_health.php';
	require_once WPCABLE_TEST_ROOT . '/classes/estimate.php';
	require_once WPCABLE_TEST_ROOT . '/classes/stats.php';
	require_once WPCABLE_TEST_ROOT . '/classes/tasks.php';
	require_once WPCABLE_TEST_ROOT . '/classes/clients.php';
	require_once WPCABLE_TEST_ROOT . '/classes/opportunities.php';
}

function wpcable_seed_fixture_clients( $wpdb ) {
	$wpdb->insert(
		'wp_codeable_clients',
		array(
			'client_id'       => 101,
			'full_name'       => 'Fixture Client Alpha',
			'role'            => 'client',
			'last_sign_in_at' => '2024-01-15 10:00:00',
			'pro'             => 0,
			'timezone_offset' => 0,
			'medium'          => '',
			'large'           => '',
			'last_sync'       => time(),
		)
	);
	$wpdb->insert(
		'wp_codeable_clients',
		array(
			'client_id'       => 202,
			'full_name'       => 'Fixture Client Beta',
			'role'            => 'client',
			'last_sign_in_at' => '2023-06-01 10:00:00',
			'pro'             => 1,
			'timezone_offset' => 0,
			'medium'          => '',
			'large'           => '',
			'last_sync'       => time(),
		)
	);
}

function wpcable_seed_fixture_tasks( $wpdb ) {
	$now   = time();
	$stale = $now - ( 200 * DAY_IN_SECONDS );
	$rows  = array(
		array( 1, 101, 'Alpha site rebuild', 'completed', 'completed', 1, 1500, $stale, 'Local follow-up note' ),
		array( 2, 101, 'Alpha maintenance', 'completed', 'completed', 1, 800, $stale, '' ),
		array( 3, 101, 'Alpha new idea', 'published', '', 0, 0, $now, '' ),
		array( 4, 202, 'Beta landing page', 'published', '', 0, 0, $now, '' ),
		array( 5, 202, 'Beta lost pitch', 'lost', 'lost', 0, 0, $stale, '' ),
	);
	foreach ( $rows as $row ) {
		$wpdb->insert(
			'wp_codeable_tasks',
			array(
				'task_id'          => $row[0],
				'client_id'        => $row[1],
				'title'            => $row[2],
				'state'            => $row[3],
				'flag'             => $row[4],
				'preferred'        => $row[5],
				'value'            => $row[6],
				'value_client'     => $row[6] ? $row[6] * 1.2 : 0,
				'last_activity'    => $row[7],
				'last_activity_by' => 'Fixture User',
				'notes'            => $row[8],
				'kind'             => 'task',
				'last_sync'        => $now,
			)
		);
	}
	// Extra published rows for pagination (synthetic IDs 10-34).
	for ( $i = 10; $i <= 34; $i++ ) {
		$wpdb->insert(
			'wp_codeable_tasks',
			array(
				'task_id'       => $i,
				'client_id'     => 202,
				'title'         => 'Fixture filler project ' . $i,
				'state'         => 'published',
				'flag'          => '',
				'preferred'     => 0,
				'value'         => 0,
				'value_client'  => 0,
				'last_activity' => $now - $i,
				'notes'         => '',
				'kind'          => 'task',
				'last_sync'     => $now,
			)
		);
	}
}
