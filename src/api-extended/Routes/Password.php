<?php

namespace Domosedov\API\Routes;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Password extends WP_REST_Controller {
	private $plugin_name;

	private $version;

	protected $namespace;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->namespace   = defined( 'API_EXTENDED_NAMESPACE' ) ? API_EXTENDED_NAMESPACE . '/v' . intval( $version ) : 'api/v1';
		$this->rest_base   = 'password';
	}

	public function register_routes() {
		register_rest_route( $this->namespace, "/$this->rest_base/forgot", [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'forgot_password' ],
			'permission_callback' => [ $this, 'forgot_password_permissions_check' ],
			'args'                => [
				'email' => [
					'description'       => __( 'User email' ),
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => [ $this, 'validate_args' ],
					'sanitize_callback' => [ $this, 'sanitize_args' ],
				]
			]
		] );

		register_rest_route( $this->namespace, "/$this->rest_base/reset", [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'create_item' ],
			'permission_callback' => [ $this, 'create_item_permissions_check' ],
			'args'                => [
				'login' => [
					'description'       => __( 'User login' ),
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => [ $this, 'validate_args' ],
					'sanitize_callback' => [ $this, 'sanitize_args' ],
				],
				'resetCode' => [
					'description'       => __( 'Reset password key' ),
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => [ $this, 'validate_args' ],
					'sanitize_callback' => [ $this, 'sanitize_args' ],
				],
				'newPassword' => [
					'description'       => __( 'User new password' ),
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => [ $this, 'validate_args' ],
					'sanitize_callback' => [ $this, 'sanitize_args' ],
				]
			]
		] );

		register_rest_route( $this->namespace, "/$this->rest_base/change", [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'create_item' ],
			'permission_callback' => [ $this, 'create_item_permissions_check' ],
			'args'                => [
				'login' => [
					'description'       => __( 'User login' ),
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => [ $this, 'validate_args' ],
					'sanitize_callback' => [ $this, 'sanitize_args' ],
				],
				'oldPassword' => [
					'description'       => __( 'Reset password key' ),
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => [ $this, 'validate_args' ],
					'sanitize_callback' => [ $this, 'sanitize_args' ],
				],
				'newPassword' => [
					'description'       => __( 'User new password' ),
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
			case 'email':
				return $this->validate_email( $value );
		}

		return $value;
	}

	public function sanitize_args( $value, $request, $param ) {
		switch ( $param ) {
			case 'email':
				return $this->sanitize_email( $value );
		}

		return $value;
	}

	public function forgot_password_permissions_check( $request ) {
		return true;
	}

	/**
	 * Create new user
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function forgot_password( $request ) {
		$email     = $request->get_param( 'email' );

		if ( email_exists( $email ) ) {
			$user = get_user_by( 'email', $email );

			if ( ! empty( $user ) ) {
				$login     = $user->user_login;
				$reset_key = get_password_reset_key( $user );

				if ( ! is_wp_error( $reset_key ) ) {

					$message = __(
						'Hello, %1$s.
						
Someone has requested a password reset for your account.
If it wasn\'t you, then ignore this letter.

Login: %1$s
Reset code: %2$s'
					);

					$is_sent = wp_mail( $email, __( 'Password reset link' ), sprintf($message, $login, $reset_key) );

					if ( empty( $is_sent ) ) {
						return new WP_Error(
							'rest_forgot_password_send_mail_failed',
							__( 'Send mail error.' ),
							[ 'status' => 400 ]
						);
					}
				}
			}
		}

		$response = apply_filters( 'api_extended_forgot_password_response', [
			'message' => __( 'Password reset instructions have been sent to your email.' )
		] );

		return rest_ensure_response( $response );
	}

	public function validate_email( $email ) {
		return is_email( $email );
	}

	public function sanitize_email( $email ) {
		return sanitize_email( $email );
	}
}