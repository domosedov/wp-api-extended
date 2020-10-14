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

	    /**
	     * Получаем заголовок авторизации
	     */
	    $auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : false;

	    /**
	     * Получаем заголовок авторизации в редиректе
	     */
	    $redirect_auth_header = isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : false;

	    /**
	     * Проеверяем, есть ли значение в заголовке авторизации
	     */
	    $auth_header = $auth_header !== false ? $auth_header : ( $redirect_auth_header !== false ? $redirect_auth_header : null );

	    /**
	     * Если нет, то возвращаем false - пользователь не авторизован
	     */
	    if (!$auth_header) {
	    	return $user;
	    }

	    /**
	     * Получаем токен из заголовка
	     */
	    list( $token ) = sscanf( $auth_header, 'Bearer %s' );

	    /**
	     * Пробуем декодировать JWT-токен
	     * Если токен валидный, возвращем ID пользователя и аутентифицируем его,
	     * иначе возвращем ошибку
	     */
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

}
