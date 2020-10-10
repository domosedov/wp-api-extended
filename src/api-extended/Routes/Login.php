<?php

namespace Domosedov\API\Routes;

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
			'callback'            => 'Wp_Api_Extended_Public::login_and_get_token',
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
}