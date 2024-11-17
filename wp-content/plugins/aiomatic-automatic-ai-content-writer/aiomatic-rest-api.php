<?php
// Create a new file: aiomatic-rest-api.php

defined('ABSPATH') or die();
require_once dirname(__FILE__) . '/class-aiomatic-bearer-token-manager.php';


class Aiomatic_REST_API {
    
    private static $instance = null;


    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // Assistant Chat Endpoint
        register_rest_route('aiomatic/v1', '/assistant-chat', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_assistant_chat'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'assistant_id' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'thread_id' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
    }

    //THIS CODE WAS ADDED TO ENABLE BEARER TOKENS IF USER HAS PERMISSION TO USE BEARER TOKEN 

// Add this method to the Aiomatic_REST_API class

private function validate_bearer_token($token) {
    // Get stored tokens
    $tokens = get_option('aiomatic_api_tokens', array());
    
    foreach ($tokens as $stored_token => $user_id) {
        if (hash_equals($stored_token, $token)) {
            // Check if user has bearer token permission
            $has_bearer_access = get_user_meta($user_id, 'if_user_has_aibearerapi_access', true);
            if ($has_bearer_access !== '1') {
                return null;
            }
            return $user_id;
        }
    }
    
    return null;
}


// In aiomatic-rest-api.php
public function check_permissions($request) {
    $user_id = null;
    
    // Check for Bearer token authentication
    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : false;
    if ($auth_header && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        $token = $matches[1];
        $tokens = get_option('aiomatic_api_tokens', array());
        
        foreach ($tokens as $stored_token => $token_user_id) {
            if (hash_equals($stored_token, $token)) {
                // Check if user has bearer token permission
                $has_bearer_access = get_user_meta($token_user_id, 'if_user_has_aibearerapi_access', true);
                if ($has_bearer_access !== '1') {
                    return new WP_Error(
                        'rest_forbidden',
                        esc_html__('Bearer token access not enabled for this user.', 'aiomatic'),
                        array('status' => 401)
                    );
                }
                $user_id = $token_user_id;
                wp_set_current_user($user_id);
                break;
            }
        }
        
        if ($user_id === null) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('Invalid bearer token.', 'aiomatic'),
                array('status' => 401)
            );
        }
    }
    
    // If no bearer token, check if user is logged in normally
    if (!$user_id && !is_user_logged_in()) {
        return new WP_Error(
            'rest_forbidden',
            esc_html__('Authentication required.', 'aiomatic'),
            array('status' => 401)
        );
    }

    // Rest of your existing permission checks
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (!isset($aiomatic_Main_Settings['app_id']) || empty($aiomatic_Main_Settings['app_id'])) {
        return new WP_Error(
            'rest_forbidden',
            esc_html__('API key not configured.', 'aiomatic'),
            array('status' => 403)
        );
    }

    // Check user role limits
    $user = wp_get_current_user();
    $user_role = $user->roles[0];
    $limit_check = $this->check_user_limits($user->ID, $user_role);
    if (is_wp_error($limit_check)) {
        return $limit_check;
    }

    return true;
}


/*

//modified when the bearer class was generated, modified code above

public function check_permissions($request) {
    $user_id = null;
    
    // Check for Bearer token authentication
    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : false;
    if ($auth_header && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        $token = $matches[1];
        $user_id = $this->validate_bearer_token($token);
        
        if ($user_id === null) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('Invalid token or insufficient bearer token permissions.', 'aiomatic'),
                array('status' => 401)
            );
        }
        
        // Set the current user
        wp_set_current_user($user_id);
    }
    
    // If no bearer token, check if user is logged in normally
    if (!$user_id && !is_user_logged_in()) {
        return new WP_Error(
            'rest_forbidden',
            esc_html__('You must be logged in to use this endpoint.', 'aiomatic'),
            array('status' => 401)
        );
    }

    // Rest of the permission checks remain the same
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (!isset($aiomatic_Main_Settings['app_id']) || empty($aiomatic_Main_Settings['app_id'])) {
        return new WP_Error(
            'rest_forbidden',
            esc_html__('API key not configured.', 'aiomatic'),
            array('status' => 403)
        );
    }

    // Check user role limits
    $user = wp_get_current_user();
    $user_role = $user->roles[0];
    $limit_check = $this->check_user_limits($user->ID, $user_role);
    if (is_wp_error($limit_check)) {
        return $limit_check;
    }

    return true;
}
    */

// Add these utility methods for managing bearer token access

public static function grant_bearer_token_access($user_id) {
    return update_user_meta($user_id, 'if_user_has_aibearerapi_access', '1');
}

public static function revoke_bearer_token_access($user_id) {
    return delete_user_meta($user_id, 'if_user_has_aibearerapi_access');
}

public static function has_bearer_token_access($user_id) {
    return get_user_meta($user_id, 'if_user_has_aibearerapi_access', true) === '1';
}
    

/*
//THIS WAS MERGED WITH THE ABOVE CHECK PERMISSION METHOD
public function check_permissions($request) {
        // Verify user is logged in
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('You must be logged in to use this endpoint.', 'aiomatic'),
                array('status' => 401)
            );
        }
    
        // Verify API limits
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        if (!isset($aiomatic_Main_Settings['app_id']) || empty($aiomatic_Main_Settings['app_id'])) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('API key not configured.', 'aiomatic'),
                array('status' => 403)
            );
        }

        // Check user role limits
        $user = wp_get_current_user();
        $user_role = $user->roles[0];
        $limit_check = $this->check_user_limits($user->ID, $user_role);
        if (is_wp_error($limit_check)) {
            return $limit_check;
        }

        return true;
    }

    */

    private function check_user_limits($user_id, $user_role) {
        $aiomatic_Limit_Settings = get_option('aiomatic_Limit_Settings', false);
        
        // Get user's current usage
        $current_usage = get_user_meta($user_id, 'aiomatic_api_usage', true);
        if (!$current_usage) {
            $current_usage = 0;
        }

        // Check role-based limits
        $role_limits = apply_filters('aiomatic_role_limits', array(
            'administrator' => -1, // Unlimited
            'editor' => 1000,
            'author' => 500,
            'subscriber' => 100
        ));

        $user_limit = isset($role_limits[$user_role]) ? $role_limits[$user_role] : 0;
        
        // Allow unlimited usage for certain roles
        if ($user_limit === -1) {
            return true;
        }

        // Check if user has exceeded their limit
        if ($current_usage >= $user_limit) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('You have exceeded your API usage limit.', 'aiomatic'),
                array('status' => 403)
            );
        }

        return true;
    }

    public function handle_assistant_chat($request) {
        $assistant_id = $request->get_param('assistant_id');
        $message = $request->get_param('message');
        $thread_id = $request->get_param('thread_id');

        // Get API settings
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
        $appids = array_filter($appids);
        $token = $appids[array_rand($appids)];

        try {
            // Generate chat response using existing function
            $error = '';
            $finish_reason = '';
            $response = aiomatic_generate_text(
                $token,
                'gpt-4', // or get from assistant settings
                $message,
                1000, // available tokens
                0.7, // temperature
                1, // top_p
                0, // presence_penalty
                0, // frequency_penalty
                true, // is_chat
                'assistantChat',
                0, // retry_count
                $finish_reason,
                $error,
                false, // no_internet
                false, // no_embeddings
                false, // stream
                '', // vision_file
                $message, // user_question
                'user', // role
                $assistant_id,
                $thread_id
            );

            if ($response === false) {
                return new WP_Error(
                    'generation_failed',
                    $error,
                    array('status' => 500)
                );
            }

            // Update user usage
            $user_id = get_current_user_id();
            $current_usage = get_user_meta($user_id, 'aiomatic_api_usage', true);
            update_user_meta($user_id, 'aiomatic_api_usage', intval($current_usage) + 1);

            return array(
                'success' => true,
                'response' => $response,
                'thread_id' => $thread_id
            );

        } catch (Exception $e) {
            return new WP_Error(
                'generation_failed',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}

// Initialize the REST API
add_action('init', function() {
    Aiomatic_REST_API::get_instance();
});

    
    