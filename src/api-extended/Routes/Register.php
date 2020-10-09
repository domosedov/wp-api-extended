<?php

namespace Domosedov\API\Routes;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

class Register extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'api/v1';
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
                ]
            ]
        ]);
    }

    public function validate_args($value, $request, $param)
    {
        return $value;
    }

    public function sanitize_args($value, $request, $param)
    {
        return $value;
    }

    public function create_item_permissions_check($request)
    {
        return true;
    }

    public function create_item($request)
    {
        $email = $request->get_param('email');
        $login = $request->get_param('login');
        $password = $request->get_param('password');

        $login_is_valid = validate_username($login);
        $email_is_valid = is_email($email);
        $password_is_valid = $this->validate_password($password);

        if (!$login_is_valid) {
            return new WP_Error(
                'rest_user_invalid_login',
                __('This login is bad.'),
                ['status' => 400]
            );
        }

        if (!$email_is_valid) {
            return new WP_Error(
                'rest_email_invalid_login',
                __('This email is bad.'),
                ['status' => 400]
            );
        }

        if (!$password_is_valid) {
            return new WP_Error(
                'rest_password_invalid_login',
                __('This password is bad.'),
                ['status' => 400]
            );
        }

        if (username_exists($login) || email_exists($email)) {
            return new WP_Error(
                'rest_user_invalid_username',
                __('This login or email already exists.'),
                ['status' => 400]
            );
        }

        $args = [
            'user_login' => $login,
            'user_email' => $email,
            'user_pass' => $password,
        ];

        $user_id = wp_insert_user($args);

        if (is_wp_error($user_id)) {
            return new WP_Error(
                'rest_user_create_error',
                __('Cant\'t create a user.'),
                ['status' => 400]
            );
        }

        return rest_ensure_response([
            'message' => 'success',
            'id' => $user_id,
        ]);
    }

    public function validate_password($password)
    {
        if (empty($password)) {
            return false;
        }

        if ( false !== strpos( $password, '\\' ) ) {
            return false;
        }

        return true;
    }
}