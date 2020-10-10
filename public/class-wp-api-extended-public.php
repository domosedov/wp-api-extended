<?php

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;

class Wp_Api_Extended_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	public function register_routes()
    {
        $registerController = new Domosedov\API\Routes\Register($this->plugin_name, $this->version);
        $registerController->register_routes();

	    $resetController = new Domosedov\API\Routes\Password($this->plugin_name, $this->version);
	    $resetController->register_routes();

	    $loginController = new Domosedov\API\Routes\Login($this->plugin_name, $this->version);
	    $loginController->register_routes();
    }

    public function register_user_meta()
    {
    	$args = [
			'type' => 'array',
		    'description' => __('User JWT refresh sessions'),
		    'single' => true,
		    'default' => [],
		    'show_in_rest' => false
	    ];

    	$is_success = register_meta('user', 'jwt_refresh_sessions', $args);

    	if (!$is_success) {
    		return new WP_Error(
			    'register_user_meta_error',
			    __('Can\'t create user meta'),
			    ['status' => 500]
		    );
	    }
    }

    public function add_cors_support()
    {
	    $enable_cors = defined('API_EXTENDED_CORS') ? API_EXTENDED_CORS : false;
	    if ($enable_cors) {
		    $headers = apply_filters('api_extended_auth_cors_allow_headers', 'Access-Control-Allow-Headers, Content-Type, Authorization');
		    header(sprintf('Access-Control-Allow-Headers: %s', $headers));
	    }
    }

    public function determine_current_user($user)
    {
    	if ($user !== false) {
    		return $user;
	    }

	    $rest_api_slug = rest_get_url_prefix();
	    $valid_api_uri = strpos($_SERVER['REQUEST_URI'], $rest_api_slug);
	    if (!$valid_api_uri) {
		    return $user;
	    }

	    $auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : false;

	    /**
	     * Double check for different auth header string (server dependent)
	     */
	    $redirect_auth_header = isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : false;

	    /**
	     * If the $auth header is set, use it. Otherwise attempt to use the $redirect_auth header
	     */
	    $auth_header = $auth_header !== false ? $auth_header : ( $redirect_auth_header !== false ? $redirect_auth_header : null );

	    if (!$auth_header) {
	    	return $user;
	    }

	    list( $token ) = sscanf( $auth_header, 'Bearer %s' );

	    try {
	    	$token = JWT::decode($token, API_EXTENDED_JWT_SECRET, ['HS256']);
	    } catch (Exception $e) {
		    return new WP_Error(
			    'rest_register_create_error',
			    __($e->getMessage()),
			    ['status' => 400]
		    );
	    }

		return $token->data->user->id;
    }

    public static function login_and_get_token($request)
    {
    	$login = $request->get_param( 'login' );
    	$password = $request->get_param( 'password' );
    	$secret_key = defined('API_EXTENDED_JWT_SECRET') ? API_EXTENDED_JWT_SECRET : false;

    	if (!$secret_key) {
		    return new WP_Error(
			    'rest_auth_login_error',
			    __('Cant\'t authenticate user. Bad settings'),
			    ['status' => 403]
		    );
	    }

    	$user = wp_authenticate($login, $password);

    	if (is_wp_error($user)) {
		    return new WP_Error(
			    'rest_auth_login_error',
			    __('Bad credentials'),
			    ['status' => 403]
		    );
	    }

    	$issued_at = time();
    	$not_before = time();
    	$expired_at = $issued_at + DAY_IN_SECONDS;

    	$args = [
    	    'iss' => get_bloginfo('url'),
		    'iat' => $issued_at,
		    'nbf' => $not_before,
		    'exp' => $expired_at,
		    'data' => [
		    	'user' => [
		    		'id' => $user->ID
			    ]
		    ]
	    ];

    	$token = JWT::encode($args, $secret_key);
	    $uuid = Uuid::uuid4();
	    $refresh_token = $uuid->toString();

	    $user_jwt_refresh_sessions = get_user_meta($user->ID, 'jwt_refresh_sessions', true);

	    if (!is_array($user_jwt_refresh_sessions) || empty($user_jwt_refresh_sessions)) {
		    $user_jwt_refresh_sessions = [];
	    }

	    $new_jwt_refresh_session = [
	    	'refresh_token' => $refresh_token,
		    'ip' => 'user ip',
		    'fingerprint' => 'fingerprint 1'
	    ];

	    $user_jwt_refresh_sessions[] = $new_jwt_refresh_session;

	    $is_updated = update_user_meta($user->ID, 'jwt_refresh_sessions', $user_jwt_refresh_sessions);

	    if (!$is_updated) {
		    return new WP_Error(
			    'rest_auth_create_refresh_session_error',
			    __('Can\'t create user jwt refresh session'),
			    ['status' => 500]
		    );
	    }

    	$response = rest_ensure_response([
		    'id' => $user->ID,
		    'login' => $user->user_login,
		    'displayName' => $user->display_name,
		    'token' => $token,
		    'refreshToken' => $refresh_token
	    ]);

    	$response->set_headers([
    		'Set-Cookie' => sprintf('refreshToken=%s; Path=/wp-json; HttpOnly', $refresh_token)
	    ]);

		return $response;
    }
}
