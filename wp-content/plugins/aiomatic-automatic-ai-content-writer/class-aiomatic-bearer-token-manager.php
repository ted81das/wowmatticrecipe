
<?php
/**

- Bearer Token Manager for Aiomatic REST API
- 
- @package Aiomatic
- @subpackage API
- @since 1.0.0
*/

defined('ABSPATH') or die();

if (!class_exists('Aiomatic_Bearer_Token_Manager')):

class Aiomatic_Bearer_Token_Manager {
/**
* Instance of this class.
*
* @since 1.0.0
* @var object
*/
private static $instance = null;

/**
 * Return an instance of this class.
 *
 * @since 1.0.0
 * @return object A single instance of this class.
 */
public static function get_instance() {
    if (self::$instance === null) {
        self::$instance = new self();
    }
    return self::$instance;
}

/**
 * Constructor.
 *
 * @since 1.0.0
 */
public function __construct() {
    // Add bearer token fields to user profile
    add_action('show_user_profile', array($this, 'add_bearer_token_fields'));
    add_action('edit_user_profile', array($this, 'add_bearer_token_fields'));

    // Save bearer token fields
    add_action('personal_options_update', array($this, 'save_bearer_token_fields'));
    add_action('edit_user_profile_update', array($this, 'save_bearer_token_fields'));

    // Add AJAX handlers
    add_action('wp_ajax_generate_aiomatic_bearer_token', array($this, 'generate_bearer_token'));
    add_action('wp_ajax_revoke_aiomatic_bearer_token', array($this, 'revoke_bearer_token'));

    // Add admin scripts
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
}

/**
 * Enqueue admin scripts and styles.
 *
 * @since 1.0.0
 * @param string $hook The current admin page.
 */
public function enqueue_admin_scripts($hook) {
    if (!in_array($hook, array('profile.php', 'user-edit.php'))) {
        return;
    }

    wp_enqueue_style(
        'aiomatic-bearer-token-manager',
        plugins_url('aiomatic-automatic-ai-content-writer/css/bearer-token-manager.css', dirname(__FILE__)),
        array(),
        AIOMATIC_VERSION
    );

    wp_enqueue_script(
        'aiomatic-bearer-token-manager',
        plugins_url('aiomatic-automatic-ai-content-writer/js/bearer-token-manager.js', dirname(__FILE__)),
        array('jquery'),
        AIOMATIC_VERSION,
        true
    );

    wp_localize_script('aiomatic-bearer-token-manager', 'aiomaticBearerToken', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aiomatic_bearer_token'),
        'confirmRevoke' => __('Are you sure you want to revoke this token?', 'aiomatic'),
        'generateError' => __('Error generating token.', 'aiomatic'),
        'revokeError' => __('Error revoking token.', 'aiomatic')
    ));
}

/**
 * Add bearer token fields to user profile.
 *
 * @since 1.0.0
 * @param WP_User $user User object.
 */
public function add_bearer_token_fields($user) {
    // Check if current user can manage tokens
    if (!current_user_can('manage_options') && $user->ID !== get_current_user_id()) {
        return;
    }

    $has_bearer_access = get_user_meta($user->ID, 'if_user_has_aibearerapi_access', true);
    $tokens = get_option('aiomatic_api_tokens', array());
    $user_tokens = array_keys(array_filter($tokens, function($uid) use ($user) {
        return $uid == $user->ID;
    }));
    ?>
    <h3><?php _e('Aiomatic API Bearer Tokens', 'aiomatic'); ?></h3>
    <table class="form-table">
        <tr>
            <th>
                <label for="aiomatic_bearer_access"><?php _e('Enable Bearer Token Access', 'aiomatic'); ?></label>
            </th>
            <td>
                <?php if (current_user_can('manage_options')): ?>
                    <input type="checkbox" name="aiomatic_bearer_access" id="aiomatic_bearer_access"
                           value="1" <?php checked($has_bearer_access, '1'); ?>>
                    <span class="description"><?php _e('Allow this user to use bearer tokens for API access', 'aiomatic'); ?></span>
                <?php else: ?>
                    <p><?php echo $has_bearer_access === '1' ? __('Enabled', 'aiomatic') : __('Disabled', 'aiomatic'); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php if ($has_bearer_access === '1'): ?>
        <tr>
            <th><?php _e('Active Tokens', 'aiomatic'); ?></th>
            <td>
                <div id="aiomatic-tokens-list" data-user-id="<?php echo esc_attr($user->ID); ?>">
                    <?php if (!empty($user_tokens)): ?>
                        <?php foreach ($user_tokens as $token): ?>
                            <div class="token-item">
                                <code><?php echo esc_html($token); ?></code>
                                <button type="button" class="button button-small revoke-token"
                                        data-token="<?php echo esc_attr($token); ?>">
                                    <?php _e('Revoke', 'aiomatic'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-tokens"><?php _e('No active tokens', 'aiomatic'); ?></p>
                    <?php endif; ?>
                </div>
                <button type="button" class="button" id="generate-token">
                    <?php _e('Generate New Token', 'aiomatic'); ?>
                </button>
                <span class="spinner" style="float: none;"></span>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <?php
}

/**
 * Save bearer token fields.
 *
 * @since 1.0.0
 * @param int $user_id User ID.
 * @return bool|void
 */
public function save_bearer_token_fields($user_id) {
    if (!current_user_can('manage_options')) {
        return false;
    }

    $has_bearer_access = isset($_POST['aiomatic_bearer_access']) ? '1' : '0';
    update_user_meta($user_id, 'if_user_has_aibearerapi_access', $has_bearer_access);

    // If access is revoked, remove all tokens for this user
    if ($has_bearer_access === '0') {
        $tokens = get_option('aiomatic_api_tokens', array());
        foreach ($tokens as $token => $token_user_id) {
            if ($token_user_id == $user_id) {
                unset($tokens[$token]);
            }
        }
        update_option('aiomatic_api_tokens', $tokens);
    }
}

/**
 * Generate new bearer token.
 *
 * @since 1.0.0
 */
public function generate_bearer_token() {
    check_ajax_referer('aiomatic_bearer_token', 'nonce');

    $user_id = intval($_POST['user_id']);

    if (!current_user_can('manage_options') && get_current_user_id() !== $user_id) {
        wp_send_json_error(array('message' => __('Unauthorized', 'aiomatic')));
    }

    // Check if user has bearer access
    if (get_user_meta($user_id, 'if_user_has_aibearerapi_access', true) !== '1') {
        wp_send_json_error(array('message' => __('Bearer token access not enabled', 'aiomatic')));
    }

    $token = bin2hex(random_bytes(32));
    $tokens = get_option('aiomatic_api_tokens', array());
    $tokens[$token] = $user_id;
    update_option('aiomatic_api_tokens', $tokens);

    wp_send_json_success(array('token' => $token));
}

/**
 * Revoke bearer token.
 *
 * @since 1.0.0
 */
public function revoke_bearer_token() {
    check_ajax_referer('aiomatic_bearer_token', 'nonce');

    $token = sanitize_text_field($_POST['token']);
    $tokens = get_option('aiomatic_api_tokens', array());

    if (!isset($tokens[$token])) {
        wp_send_json_error(array('message' => __('Token not found', 'aiomatic')));
    }

    $user_id = $tokens[$token];
    if (!current_user_can('manage_options') && get_current_user_id() !== $user_id) {
        wp_send_json_error(array('message' => __('Unauthorized', 'aiomatic')));
    }

    unset($tokens[$token]);
    update_option('aiomatic_api_tokens', $tokens);

    wp_send_json_success();
    
}


}

endif;

// Initialize the Bearer Token Manager
add_action('init', function() {
Aiomatic_Bearer_Token_Manager::get_instance();
});

