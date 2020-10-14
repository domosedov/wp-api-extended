<?php

namespace Domosedov\API\Routes;

use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;
use Wp_Api_Extended_Public;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Login extends WP_REST_Controller {

	private $plugin_name;

	private $version;

	protected $namespace;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->namespace   = defined( 'API_EXTENDED_NAMESPACE' ) ? API_EXTENDED_NAMESPACE . '/v' . intval( $version ) : 'api/v1';
		$this->rest_base   = 'login';
	}

	public function register_routes() {
		register_rest_route( $this->namespace, "/$this->rest_base", [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [$this, 'login_and_get_token'],
			'args'                => [
				'login' => [
					'description'       => __( 'User login' ),
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => [ $this, 'validate_args' ],
					'sanitize_callback' => [ $this, 'sanitize_args' ],
				],
				'password' => [
					'description'       => __( 'User password' ),
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => [ $this, 'validate_args' ],
					'sanitize_callback' => [ $this, 'sanitize_args' ],
				]
			]
		] );
	}

	public function validate_args( $value, $request, $param ) {
		switch ( $param ) {
			case 'login':
			case 'password':
				return !empty($value);
		}

		return $value;
	}

	public function sanitize_args( $value, $request, $param ) {
		switch ( $param ) {
			case 'login':
				return sanitize_text_field( $value );
		}

		return $value;
	}

    public function login_and_get_token($request)
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
        $expired_at = $issued_at + (15000 * MINUTE_IN_SECONDS);

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
            'Set-Cookie' => sprintf('refreshToken=%s; Domain=react-client.local; Path=/wp-json; HttpOnly', $refresh_token),
	        'X-Foo' => 'bar=42'
        ]);

        return $response;
    }
}