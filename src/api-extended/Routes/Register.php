<?php

namespace Domosedov\API\Routes;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Register extends WP_REST_Controller
{
    private $plugin_name;

    private $version;

    protected $namespace;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->namespace = defined('API_EXTENDED_NAMESPACE') ? API_EXTENDED_NAMESPACE . '/v' . intval($version) : 'api/v1';
        $this->rest_base = 'register';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, "/$this->rest_base", [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'create_item_permissions_check'],
            'args' => [
                'email' => [
                    'description' => __('User email'),
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => [$this, 'validate_args'],
                    'sanitize_callback' => [$this, 'sanitize_args'],
                ],
                'login' => [
                    'description' => __('User login'),
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => [$this, 'validate_args'],
                    'sanitize_callback' => [$this, 'sanitize_args'],
                ],
                'password' => [
                    'description' => __('User password'),
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => [$this, 'validate_args'],
                    'sanitize_callback' => [$this, 'sanitize_args'],
                ],
                'firstName' => [
                    'description' => __('User password'),
                    'type' => 'string',
                    'required' => false,
                    'validate_callback' => [$this, 'validate_args'],
                    'sanitize_callback' => [$this, 'sanitize_args'],
                ],
                'lastName' => [
                    'description' => __('User password'),
                    'type' => 'string',
                    'required' => false,
                    'validate_callback' => [$this, 'validate_args'],
                    'sanitize_callback' => [$this, 'sanitize_args'],
                ],
            ]
        ]);
    }

    public function validate_args($value, $request, $param)
    {
        switch ($param) {
            case 'login':
                return $this->validate_login($value);
            case 'email':
                return $this->validate_email($value);
            case 'password':
                return $this->validate_password($value);
            case 'firstName':
            case 'lastName':
                return !empty($value);
        }
        return $value;
    }

    public function sanitize_args($value, $request, $param)
    {
        switch ($param) {
            case 'login':
                return $this->sanitize_login($value);
            case 'email':
                return $this->sanitize_email($value);
            case 'password':
                return $this->validate_password($value);
            case 'firstName':
            case 'lastName':
                return sanitize_text_field($value);
        }
        return $value;
    }

    public function create_item_permissions_check($request)
    {
        return true;
    }

    /**
     * Create new user
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function create_item($request)
    {
        $email = $request->get_param('email');
        $login = $request->get_param('login');
        $password = $request->get_param('password');

        $first_name = $request->get_param('firstName');
        $last_name = $request->get_param('lastName');

        if (username_exists($login)) {
            return new WP_Error(
                'rest_register_invalid_login',
                __('This login already exists.'),
                ['status' => 400]
            );
        }

        if (email_exists($email)) {
            return new WP_Error(
                'rest_register_invalid_email',
                __('This email already exists.'),
                ['status' => 400]
            );
        }

        $args = [
            'user_login' => $login,
            'user_email' => $email,
            'user_pass' => $password,
        ];

        if (!empty($first_name)) {
            $args['first_name'] = $first_name;
        }

        if (!empty($last_name)) {
            $args['last_name'] = $last_name;
        }

        $user_id = wp_insert_user($args);

        if (is_wp_error($user_id)) {
            return new WP_Error(
                'rest_register_create_error',
                __('Cant\'t register a new user.'),
                ['status' => 400]
            );
        }

        $response = apply_filters('api_extended_login_response', [
            'message' => __('success'),
            'id' => $user_id,
            'login' => $login,
            'args' => $request->get_params()
        ]);

        return rest_ensure_response($response);
    }

    public function validate_login($login)
    {
        return validate_username($login);
    }

    public function sanitize_login($login)
    {
        return sanitize_text_field($login);
    }

    public function validate_email($email)
    {
        return is_email($email);
    }

    public function sanitize_email($email)
    {
        return sanitize_email($email);
    }

    public function validate_password($password)
    {
        if (empty($password)) {
            return false;
        }

        return $password;
    }

    public function sanitize_password($password)
    {
        return $password;
    }
}