<?php
/**
 * API handler.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Parse Retry-After (delta-seconds or HTTP date) from response headers.
 *
 * @param array $headers Header map.
 * @return int Seconds to wait, 0 if absent/unusable.
 */
function wpcable_http_retry_after_seconds( $headers ) {
	if ( ! is_array( $headers ) ) {
		return 0;
	}
	$raw = '';
	foreach ( $headers as $hk => $hv ) {
		if ( 'retry-after' === strtolower( (string) $hk ) ) {
			$raw = is_array( $hv ) ? (string) reset( $hv ) : (string) $hv;
			break;
		}
	}
	if ( '' === $raw ) {
		return 0;
	}
	if ( preg_match( '/^[0-9]+$/', trim( $raw ) ) ) {
		return (int) $raw;
	}
	$ts = strtotime( $raw );
	if ( false === $ts ) {
		return 0;
	}
	return max( 0, $ts - time() );
}

/**
 * Handles communication with the Codeable API.
 *
 * Tip: To find available endpoints, simply examine the codeable app in your browser
 * and see, which api.codeable.com requests are made on a specific page.
 */
class wpcable_api_calls {

	/**
	 * The auth_token is used by all APIs (except the "login" endpoint) to access
	 * private data of the authenticatd user.
	 *
	 * @var string
	 */
	public $auth_token = '';

	/**
	 * Get the option to know when to stop pulling tasks
	 *
	 * @var int
	 */
	private $tasks_stop_at_page = 0;

	/**
	 * Returns the singleton instance to the API object.
	 *
	 * @return wpcable_api_calls
	 */
	public static function inst() {
		static $_inst = null;

		if ( null === $_inst ) {
			$_inst = new wpcable_api_calls();
		}

		return $_inst;
	}

	/**
	 * Initializes the API handler by loading the previous auth_token from the DB.
	 */
	private function __construct() {
		$this->get_auth_token();
		$this->tasks_stop_at_page = (int) get_option( 'wpcable_tasks_stop_at_page', 0 );
	}

	/**
	 * Generates an auth_token from the given email/password pair.
	 *
	 * @param  string $email    E-Mail address to use for login.
	 * @param  string $password The users password, as entered on app.codeable.com.
	 * @return void
	 */
	public function login( $email, $password ) {
		$args = [
			'email'    => $email,
			'password' => $password,
		];

		$this->auth_token = '';
		$url              = 'users/login';
		$login_call       = $this->request( $url, $args );

		if ( ! is_array( $login_call ) ) {
			$redirect_to = codeable_add_message_param( 'error', 'credentials' );

			wp_safe_redirect( $redirect_to );
			exit;
		}

		// Credential error checking.
		if (
			isset( $login_call['errors'] ) &&
			! empty( $login_call['errors'][0]['message'] ) &&
			'Invalid credentials' === $login_call['errors'][0]['message']
		) {
			$redirect_to = codeable_add_message_param( 'error', 'credentials' );

			wp_safe_redirect( $redirect_to );
			exit;
		}

		if ( empty( $login_call['auth_token'] ) ) {
			$redirect_to = codeable_add_message_param( 'error', 'credentials' );

			wp_safe_redirect( $redirect_to );
			exit;
		}

		$this->set_auth_token( $login_call['auth_token'] );
	}

	/**
	 * Checks, whether we know the auth-token from a previous API call.
	 *
	 * @return bool
	 */
	public function auth_token_known() {
		return !! $this->auth_token;
	}

	/**
	 * Encrypt the auth_token and store it in the options table for future usage.
	 *
	 * @param  string $token The auth_token.
	 * @return void
	 */
	private function set_auth_token( $token ) {
		$iv  = openssl_random_pseudo_bytes( 16 );
		$enc = openssl_encrypt( $token, 'AES-256-CBC', AUTH_SALT, 0, $iv );

		$full_enc = base64_encode( $iv ) . ':' . base64_encode( $enc );

		update_option( 'wpcable_auth_token', $full_enc );

		$this->auth_token = $token;
	}

	/**
	 * Read the encrypted auth_token from the options table and decrypt it.
	 *
	 * @return void
	 */
	private function get_auth_token() {
		$this->auth_token = '';

		$value = get_option( 'wpcable_auth_token' );

		if ( $value ) {
			list ( $iv, $enc ) = explode( ':', $value );

			$iv    = base64_decode( $iv );
			$enc   = base64_decode( $enc );
			$token = openssl_decrypt( $enc, 'AES-256-CBC', AUTH_SALT, 0, $iv );

			$this->auth_token = $token;
		}
	}

	/**
	 * Returns the users profile details.
	 *
	 * @return array
	 */
	public function self() {
		$url        = 'users/me';
		$login_call = $this->request( $url, [], 'get' );

		if ( ! is_array( $login_call ) ) {
			return false;
		}

		unset( $login_call['auth_token'] );

		return $login_call;
	}

	/**
	 * Get a batch of transactions of the current user.
	 *
	 * @param  int $page Pagination offset. First $page = 1.
	 * @return array
	 */
	public function transactions_page( $page = 1 ) {
		$url  = 'users/me/transactions';
		$args = [ 'page' => $page ];

		$transactions = $this->request( $url, $args, 'get' );

		return $transactions;
	}

	/**
	 * Get a batch of up to 20 tasks of the current user.
	 *
	 * @param  string $filter Task-Filter, [pending|active|archived|preferred].
	 * @param  int    $page   Pagination offset. First page is $page = 1.
	 * @return array
	 */
	public function tasks_page( $filter = 'preferred', $page = 1 ) {
		if ( 'hidden_tasks' === $filter ) {
			$url = 'users/me/hidden_tasks/';
			$num = 50;
		} else {
			$url = 'users/me/tasks/' . $filter;
			$num = 20;
		}

		$args = [
			'page'     => $page,
			'per_page' => $num,
		];

		// Stop at the next page before it does the call
		if ( $this->tasks_stop_at_page
		     && ( $this->tasks_stop_at_page + 1 ) === $page ) {
			return [];
		}

		$tasks = $this->request( $url, $args, 'get' );

		return $tasks;
	}

	/**
	 * Documented read-only GET on the existing working host (api.codeable.io).
	 * Does not change host to api.codeable.com, does not follow redirects
	 * (avoids leaking the Bearer token cross-domain), and never uses the
	 * undocumented singular-task collection.
	 *
	 * @param string $path  Relative path, e.g. tasks/123 or tasks/123/comments.
	 * @param array  $query Query args (limit/offset for comments).
	 * @return array {status, body, headers, link, url, error}
	 */
	public function get_with_meta( $path, $query = array() ) {
		$path = ltrim( (string) $path, '/' );
		$out  = array(
			'status'      => 0,
			'body'        => null,
			'headers'     => array(),
			'link'        => '',
			'url'         => '',
			'error'       => '',
			'path'        => $path,
			'retry_after' => 0,
		);

		if ( ! preg_match( '#^tasks/[A-Za-z0-9_-]+(/comments(/[A-Za-z0-9_-]+)?)?$#', $path ) ) {
			$out['error']  = 'path_not_allowlisted';
			$out['status'] = 0;
			return $out;
		}

		$full_url   = 'https://api.codeable.io/' . $path;
		$out['url'] = $full_url;

		$pre = apply_filters( 'wpcable_pre_http_get', null, $path, $query, $full_url );
		if ( is_array( $pre ) ) {
			$merged = array_merge( $out, $pre, array( 'path' => $path, 'url' => isset( $pre['url'] ) ? $pre['url'] : $full_url ) );
			if ( empty( $merged['retry_after'] ) ) {
				$merged['retry_after'] = wpcable_http_retry_after_seconds( isset( $merged['headers'] ) ? $merged['headers'] : array() );
			}
			return $merged;
		}

		if ( ! $this->auth_token_known() ) {
			$out['error']  = 'disconnected';
			$out['status'] = 0;
			return $out;
		}

		$request_args = array(
			'method'      => 'GET',
			'redirection' => 0,
			'timeout'     => 30,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $this->auth_token,
			),
		);

		$url = $full_url;
		if ( ! empty( $query ) && is_array( $query ) ) {
			$url = add_query_arg( $query, $url );
		}
		$out['url'] = $url;

		$response = wp_remote_request( $url, $request_args );
		if ( is_wp_error( $response ) ) {
			$out['error']  = $response->get_error_message();
			$out['status'] = 0;
			return $out;
		}

		$out['status'] = (int) wp_remote_retrieve_response_code( $response );
		$raw_body      = wp_remote_retrieve_body( $response );
		$decoded       = json_decode( $raw_body, true );
		$out['body']   = ( null === $decoded ) ? $raw_body : $decoded;

		$headers = array();
		if ( isset( $response['headers'] ) ) {
			if ( is_object( $response['headers'] ) && method_exists( $response['headers'], 'getAll' ) ) {
				$headers = $response['headers']->getAll();
			} elseif ( is_array( $response['headers'] ) ) {
				$headers = $response['headers'];
			}
		}
		$out['headers'] = $headers;
		$link           = '';
		foreach ( array( 'link', 'Link' ) as $hk ) {
			if ( ! empty( $headers[ $hk ] ) ) {
				$link = is_array( $headers[ $hk ] ) ? implode( ', ', $headers[ $hk ] ) : (string) $headers[ $hk ];
				break;
			}
		}
		$out['link'] = $link;
		$out['retry_after'] = wpcable_http_retry_after_seconds( $headers );

		return $out;
	}

	/**
	 * Set off an API call too api.codeable.com and return the result as array.
	 *
	 * @param  string $url     API endpoint.
	 * @param  array  $args    Additional URL params or post data.
	 * @param  string $method  Request method [GET|POST].
	 * @param  array  $headers Optional HTTP headers.
	 * @return array
	 */
	private function request( $url, $args = [], $method = 'POST', $headers = [] ) {
		$response_body = false;

		set_time_limit( 300 );

		$method       = strtoupper( $method );
		$request_args = [ 'method' => $method ];
		$url          = 'https://api.codeable.io/' . ltrim( $url, '/' );

		if ( ! empty( $args ) ) {
			if ( 'GET' === $method ) {
				$url = add_query_arg( $args, $url );
			} else {
				$request_args['body'] = $args;
			}
		}

		$request_args['headers'] = $headers;

		if ( $this->auth_token_known() ) {
			$request_args['headers']['Authorization'] = 'Bearer ' . $this->auth_token;
		}

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			trigger_error(
				sprintf(
					'Request failed with error %1$s: %2$s',
					$response->get_error_code(), $response->get_error_message()
				),
				E_USER_ERROR
			);
			return false;
		}

		$response_body = json_decode( $response['body'], true );

		if ( ! is_array( $response_body ) ) {
			return false;
		}

		if ( isset( $response['headers'] ) && is_object( $response['headers'] ) && method_exists( $response['headers'], 'getAll' ) ) {
			$full_header = $response['headers']->getAll();
			if ( isset( $full_header['auth-token'] ) && ! empty( $full_header['auth-token'] ) ) {
				$response_body['auth_token'] = $full_header['auth-token'];
			}
		}

		if ( is_array( $response_body ) && ! empty( $response_body['errors'] ) ) {
			if ( false !== array_search( 'Invalid login credentials', $response_body['errors'], true ) ) {
				// The auth_token expired or login failed: Clear the token!
				// Next time the user visits the settings page, they need to login again.
				codeable_api_logout();
				return false;
			}
		}

		return $response_body;
	}
}
