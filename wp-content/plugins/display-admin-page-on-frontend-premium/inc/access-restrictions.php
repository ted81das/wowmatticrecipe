<?php
if (!class_exists('WPFA_Access_Restrictions')) {

	class WPFA_Access_Restrictions {

		static private $instance = false;

		private function __construct() {
			
		}

		function maybe_start_learning_mode(){
			if( !VG_Admin_To_Frontend_Obj()->is_master_user()){
				return;
			}
			// Toggle the learning mode when they click on the option in the settings page
			if (!empty($_GET['wpfa_learning_mode_toggle']) && !empty($_GET['wpfa_nonce']) && wp_verify_nonce($_GET['wpfa_nonce'], 'wpfa') ) {
				$is_active = VG_Admin_To_Frontend_Obj()->get_settings('access_restrictions_learning_mode_active');
				VG_Admin_To_Frontend_Obj()->update_option('access_restrictions_learning_mode_active', $is_active ? false : current_time('timestamp', true) );
				$redirect_to = esc_url( remove_query_arg(array('wpfa_learning_mode_toggle', 'wpfa_nonce')));
				wp_safe_redirect($redirect_to);
				exit();
			}

			if( wp_doing_ajax() || wp_doing_cron() ){
				return;
			}

			// If this is an admin page and the learning mode is inactive or it's a network admin page, exist (don't auto allow the page)
			if( ! VG_Admin_To_Frontend_Obj()->get_settings('access_restrictions_learning_mode_active') || is_network_admin() ){
				return;
			}

			// If the learning mode is active, but the 10 minutes timeframe expired, auto disable the learning mode and exist
			$ten_minutes_ago_timestamp = current_time('timestamp', true) - (MINUTE_IN_SECONDS * 10);
			if( (int) VG_Admin_To_Frontend_Obj()->get_settings('access_restrictions_learning_mode_active') < $ten_minutes_ago_timestamp ){
				VG_Admin_To_Frontend_Obj()->update_option('access_restrictions_learning_mode_active', false );
				return;
			}

			$url = VG_Admin_To_Frontend_Obj()->get_current_url();
			$url_path = VG_Admin_To_Frontend_Obj()->prepare_loose_url($url);
			$this->whitelist_urls(array($url_path));
		}
		function remove_frame_options_header(){
			remove_action('admin_init', 'send_frame_options_header');
		}
		function init() {
			if (is_admin()) {
				add_action('admin_init', array($this, 'remove_frame_options_header'), 9);
				add_action('admin_init', array($this, 'apply_page_blacklist'));
				add_action('admin_head', array($this, 'enforce_frontend_dashboard'), 1);
				add_filter('vg_plugin_sdk/settings/' . VG_Admin_To_Frontend::$textname . '/before_saving', array($this, 'modify_whitelisted_urls_before_saving'));
				add_action('wp_frontend_admin/quick_settings/after_save', array($this, 'whitelist_pages_after_creation'));
				add_action('admin_init', array($this, 'whitelist_existing_urls'));
				add_action('admin_init', array($this, 'maybe_start_learning_mode'), 9);

				if (dapof_fs()->can_use_premium_code__premium_only()) {
					add_action('pre_get_users', array($this, 'filter_users_list__premium_only'));
					add_filter('editable_roles', array($this, 'filter_user_roles_profile_editor__premium_only'));
					add_filter('signup_user_meta', array($this, 'filter_user_roles_create__premium_only'));
				}
			} else {
				add_action('wp', array($this, 'maybe_redirect_to_login_page'));
				add_action('wp', array($this, 'maybe_redirect_to_custom_page_if_zero_sites'));
				add_action('wp_head', array($this, 'redirect_to_main_window_from_iframe'), 1);
			}
			remove_action('login_init', 'send_frame_options_header');
			if (!is_network_admin()) {
				add_action('init', array($this, 'allow_iframes_on_origin'));
				add_action('login_init', array($this, 'allow_iframes_on_origin'));
			}
			add_action('wp_frontend_admin/frontend_page_created', array($this, 'whitelist_pages_after_creation'));
			if (dapof_fs()->can_use_premium_code__premium_only()) {
				add_filter('login_url', array($this, 'maybe_filter_login_url__premium_only'), 80, 2);
			}
			// Allow admins to register users
			if (dapof_fs()->is_plan('platform', true)) {
				if (is_multisite() && is_admin()) {
					if (VG_Admin_To_Frontend_Obj()->get_settings('allow_main_site_admins_backend')) {
						add_filter('vg_admin_to_frontend/skip_frontend_dashboard_enforcement', array($this, 'allow_main_site_admins_backend'));
					}
					if (VG_Admin_To_Frontend_Obj()->get_settings('allow_admins_register_users')) {
						add_filter('map_meta_cap', array($this, 'admin_users_caps'), 1, 4);
						remove_all_filters('enable_edit_any_user_configuration');
						add_filter('enable_edit_any_user_configuration', '__return_true');
						add_filter('admin_head', array($this, 'edit_permission_check'), 1, 4);
					}
				}
			}
		}

		function allow_main_site_admins_backend($skip) {
			if (is_multisite()) {
				$user_id = get_current_user_id();
				$user = get_userdata($user_id);
				$user_belongs_to_blogs = get_blogs_of_user($user_id);
				$first_blog = end($user_belongs_to_blogs);
				if (count($user_belongs_to_blogs) === 1 && $first_blog->userblog_id === 1 && VG_Admin_To_Frontend_Obj()->user_has_any_role(array('administrator'), $user)) {
					$skip = true;
				}
			}
			return $skip;
		}

		/**
		 * Checks that both the editing user and the user being edited are
		 * members of the blog and prevents the super admin being edited.
		 */
		function edit_permission_check() {
			global $profileuser;

			$screen = get_current_screen();
			$current_user = wp_get_current_user();

			if (!is_super_admin($current_user->ID) && in_array($screen->base, array('user-edit', 'user-edit-network'))) { // editing a user profile
				if (is_super_admin($profileuser->ID)) { // trying to edit a superadmin while less than a superadmin
					wp_die(__('You do not have permission to edit this user.'));
				} elseif (!( is_user_member_of_blog($profileuser->ID, get_current_blog_id()) && is_user_member_of_blog($current_user->ID, get_current_blog_id()) )) { // editing user and edited user aren't members of the same blog
					wp_die(__('You do not have permission to edit this user.'));
				}
			}
		}

		/**
		 * Allow admins to register users
		 */
		function admin_users_caps($caps, $cap, $user_id, $args) {

			foreach ($caps as $key => $capability) {

				if ($capability != 'do_not_allow') {
					continue;
				}

				switch ($cap) {
					case 'edit_user':
					case 'edit_users':
						$caps[$key] = 'edit_users';
						break;
					case 'delete_user':
					case 'delete_users':
						$caps[$key] = 'delete_users';
						break;
					case 'create_users':
						$caps[$key] = $cap;
						break;
				}
			}

			return $caps;
		}

		function maybe_filter_login_url__premium_only($login_url, $redirect) {
			$wpfa_login_page_url = VG_Admin_To_Frontend_Obj()->get_login_url();
			if (strpos($login_url, 'wp-login.php') !== false && !empty($wpfa_login_page_url)) {
				$login_url = esc_url(add_query_arg('redirect_to', $redirect, $wpfa_login_page_url));
			}
			return $login_url;
		}

		function filter_user_roles_create__premium_only($meta) {
			if (is_user_logged_in() && !VG_Admin_To_Frontend_Obj()->is_master_user() && isset($meta['new_role'])) {
				$roles = get_editable_roles();
				if (!isset($roles[$meta['new_role']])) {
					$meta['new_role'] = 'subscriber';
				}
			}
			return $meta;
		}

		function filter_user_roles_profile_editor__premium_only($editable_roles) {
			if (!function_exists('wp_get_current_user')) {
				return $editable_roles;
			}
			if (!is_admin() || is_network_admin() || VG_Admin_To_Frontend_Obj()->is_master_user()) {
				return $editable_roles;
			}

			$roles = wp_roles();
			$allowed_roles = null;
			foreach ($roles->roles as $role_key => $role) {
				if (!VG_Admin_To_Frontend_Obj()->user_has_any_role(array($role_key))) {
					continue;
				}
				$option_key = 'user_roles_visible_for_' . $role_key;
				$value = VG_Admin_To_Frontend_Obj()->get_settings($option_key, '');
				if (empty($value)) {
					continue;
				}
				$allowed_roles = array_flip(array_map('trim', explode(',', $value)));
				if ($allowed_roles) {
					$editable_roles = array_intersect_key($editable_roles, $allowed_roles);
				}
			}

			return $editable_roles;
		}

		function filter_users_list__premium_only($query) {
			global $pagenow;
			// Some plugins might make get_users() queries before the current user is set
			if (!function_exists('wp_get_current_user')) {
				return;
			}
			if (is_admin() && 'users.php' == $pagenow && !is_network_admin() && !VG_Admin_To_Frontend_Obj()->is_master_user()) {
				$roles = wp_roles();
				$allowed_roles = null;
				foreach ($roles->roles as $role_key => $role) {
					if (!VG_Admin_To_Frontend_Obj()->user_has_any_role(array($role_key))) {
						continue;
					}
					$option_key = 'user_roles_visible_for_' . $role_key;
					$value = VG_Admin_To_Frontend_Obj()->get_settings($option_key, '');
					if (empty($value)) {
						continue;
					}
					$allowed_roles = array_map('trim', explode(', ', $value));
					if ($allowed_roles) {
						break;
					}
				}
				if ($allowed_roles) {
					$query->set('role__in', $allowed_roles);
				}
			}
		}

		function modify_whitelisted_urls_before_saving($options) {
			if (empty($options['whitelisted_admin_urls'])) {
				return $options;
			}

			if (is_array($options['whitelisted_admin_urls'])) {
				$urls = $options['whitelisted_admin_urls'];
			} else {
				$urls = array_map('trim', preg_split('/\r\n|\r|\n/', $options['whitelisted_admin_urls']));
			}
			foreach ($urls as $index => $url) {
				$urls[$index] = remove_query_arg(array('post', 'token', '_wpnonce', 'user_id', 'wp_http_referer', 's', 'updated', 'page_id', 'trid'), html_entity_decode($url));
			}

			$all_urls = serialize($urls);
			// Automatically whitelist post-related URLs if they whitelisted the list of posts or post editor
			// The allowed URLs are the trash action, editor for existing posts, and create new post
			if (preg_match('/(edit\.php|post-new\.php)/', $all_urls) && VG_Admin_To_Frontend_Obj()->is_master_user()) {
				foreach ($urls as $allowed_url) {
					preg_match('/(edit\.php|post-new\.php)\?post_type = ([^&#]+)/', $allowed_url, $matches);
					if (empty($matches) || empty($matches[2])) {
						continue;
					}
					$post_type = $matches[2];
					$urls[] = admin_url('post-new.php?post_type=' . $post_type);
					$urls[] = admin_url('edit.php?post_type=' . $post_type);
				}
				if (strpos($all_urls, 'post.php?action=edit') === false) {
					$urls[] = admin_url('post.php?action=edit');
				}
				if (strpos($all_urls, 'post.php?action=trash"') === false) {
					$urls[] = admin_url('post.php?action=trash');
				}
				if (strpos($all_urls, 'post.php"') === false) {
					$urls[] = admin_url('post.php');
				}
				if (strpos($all_urls, 'post.php?get-post-lock=1"') === false) {
					$urls[] = admin_url('post.php?get-post-lock=1');
				}
			}
			// Automatically whitelist term-related URLs if they whitelisted the list of taxonomy terms [^"]
			if (VG_Admin_To_Frontend_Obj()->is_master_user()) {
				foreach ($urls as $allowed_url) {
					preg_match('/edit-tags\.php\?taxonomy=([^&#]+)/', $allowed_url, $matches);
					if (empty($matches) || empty($matches[1])) {
						continue;
					}
					$allowed_taxonomy_key = $matches[1];
					$urls[] = admin_url('term.php?taxonomy=' . $allowed_taxonomy_key);
				}
			}

			// WooCommerce: Whitelist the individual attribute listing page when we manually whitelist the product attributes page
			if (function_exists('WC') && strpos($all_urls, '/edit.php?post_type=product&page=product_attributes') !== false && VG_Admin_To_Frontend_Obj()->is_master_user()) {
				$urls[] = admin_url('edit-tags.php?taxonomy=pa_{any_parameter_value}&post_type=product');
			}
			if (strpos($all_urls, '/revision.php') !== false && VG_Admin_To_Frontend_Obj()->is_master_user()) {
				$urls[] = admin_url('revision.php?action=restore');
			}
			$options['whitelisted_admin_urls'] = implode(PHP_EOL, array_unique($urls));
			$options['whitelisted_admin_urls'] = str_replace(array('%7B', '%7D'), array('{', '}'), $options['whitelisted_admin_urls']);
			return $options;
		}

		function whitelist_pages_after_creation() {
			$this->whitelist_all_existing_urls();
		}

		function whitelist_existing_urls() {
			global $wpdb;
			if (!VG_Admin_To_Frontend_Obj()->is_master_user()) {
				return;
			}
			
			if (empty($_GET['wpfa_auto_whitelist_urls']) || empty($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'wpfa')) {
				return;
			}

			$this->whitelist_all_existing_urls();

			$redirect_to = remove_query_arg(array('_wpnonce', 'wpfa_auto_whitelist_urls') );
			wp_safe_redirect(esc_url($redirect_to));
			exit();
		}

		function whitelist_all_existing_urls(){			
			global $wpdb;
			// Only administrators can whitelist urls
			if (!current_user_can('manage_options')) {
				return;
			}			

			$existing_pages = $wpdb->get_col("SELECT post_content FROM $wpdb->posts WHERE post_content LIKE '%[vg_display_admin_page%' AND post_status = 'publish'");

			
			$elementor_pages = $wpdb->get_col("SELECT meta_value FROM $wpdb->postmeta pm LEFT JOIN $wpdb->posts p ON pm.post_id = p.ID WHERE meta_key = '_elementor_data' AND meta_value LIKE '%[vg_display_admin_page%' AND p.post_status = 'publish'");
			$existing_pages = array_merge( $existing_pages, $elementor_pages );
			if (empty($existing_pages)) {
				return;
			}
			$pages = implode('<br>', $existing_pages);

			preg_match_all('/\[vg_display_admin_page page_url=(\'|")([^\'"]+)(\'|")/', $pages, $matches);

			if (!empty($matches[2])) {
				$this->whitelist_urls($matches[2]);
			}
		}

		function whitelist_urls($urls) {
			$urls = array_diff( $urls, array( 'wp-admin URL'));
			$whitelisted_urls = array_map('trim', preg_split('/\r\n|\r|\n/', VG_Admin_To_Frontend_Obj()->get_settings('whitelisted_admin_urls', '')));
			$whitelisted_urls = array_unique(array_merge($whitelisted_urls, $urls));

			$prepared_urls = $this->modify_whitelisted_urls_before_saving(array(
				'whitelisted_admin_urls' => $whitelisted_urls
			));
			if (empty($prepared_urls['whitelisted_admin_urls'])) {
				$prepared_urls['whitelisted_admin_urls'] = array();
			}

			if (is_array($prepared_urls['whitelisted_admin_urls'])) {
				$prepared_urls['whitelisted_admin_urls'] = implode(PHP_EOL, $prepared_urls['whitelisted_admin_urls']);
			}
			VG_Admin_To_Frontend_Obj()->update_option('whitelisted_admin_urls', $prepared_urls['whitelisted_admin_urls']);
		}

		function maybe_redirect_to_custom_page_if_zero_sites() {
			if (!empty($_GET['vgfa_redirect_zero_sites'])) {
				return;
			}
			if (VG_Admin_To_Frontend_Obj()->is_master_user()) {
				return;
			}
			if (!is_user_logged_in() || !is_singular()) {
				return;
			}
			if (!VG_Admin_To_Frontend_Obj()->is_wpfa_page() && !WPFA_Global_Dashboard_Obj()->is_global_dashboard()) {
				return;
			}

			$redirect_to = VG_Admin_To_Frontend_Obj()->get_settings('redirect_to_page_when_zero_sites', '');
			if (empty($redirect_to)) {
				return;
			}

			$sites = WPFA_Global_Dashboard_Obj()->get_manageable_sites();
			if ($sites) {
				return;
			}

			wp_redirect(esc_url(add_query_arg('vgfa_redirect_zero_sites', 1, trailingslashit($redirect_to))));
			exit();
		}

		/**
		 * If we have defined a login page and the inline login form is not used
		 * We check if the current page has our shortcode and redirect the page to the login page
		 * 
		 * @return null
		 */
		function maybe_redirect_to_login_page() {
			if (is_user_logged_in() || !is_singular()) {
				return;
			}
			$login_page_url = VG_Admin_To_Frontend_Obj()->get_login_url();
			if (empty($login_page_url)) {
				return;
			}
			$current_url = VG_Admin_To_Frontend_Obj()->get_current_url();
			if (strpos($current_url, $login_page_url) !== false || strpos($current_url, 'wp-signup.php') !== false) {
				return;
			}

			if (!VG_Admin_To_Frontend_Obj()->is_wpfa_page() && !WPFA_Global_Dashboard_Obj()->is_global_dashboard()) {
				return;
			}

			if (!apply_filters('wp_frontend_admin/can_redirect_page_to_login', true, $login_page_url, $current_url)) {
				return;
			}

			$current_slug = basename(parse_url($current_url, PHP_URL_PATH));
			$allowed_slugs = array_map('basename', array_map('trim', explode(',', VG_Admin_To_Frontend_Obj()->get_settings('extra_public_pages_slugs', ''))));
			if (!empty($current_slug) && !empty($allowed_slugs) && in_array($current_slug, $allowed_slugs, true)) {
				return;
			}

			// Compatibility with Nextend Social Login plugin. Allow to load the social proxy page without login because it's part of the social signup/login flow
			if (class_exists('NextendSocialLogin') && NextendSocialLogin::$settings->get('proxy-page') && get_queried_object_id() === (int) NextendSocialLogin::$settings->get('proxy-page')) {
				return;
			}

			if (!empty($_GET['vgfa_redirect_to_login'])) {
				return;
			}

			wp_redirect(esc_url(add_query_arg('vgfa_redirect_to_login', 1, $login_page_url)));
			exit();
		}

		function redirect_to_main_window_from_iframe() {
			$is_disabled = apply_filters('vg_admin_to_frontend/open_frontend_pages_in_main_window', VG_Admin_To_Frontend_Obj()->get_settings('disable_frontend_to_main_window', false));
			if ($is_disabled) {
				return;
			}
			if (wp_doing_ajax() || !empty($_POST)) {
				return;
			}
			if (!is_user_logged_in()){
				return;
			}
			// If the iframe loaded a frontend page (not wp-admin), open it in the main window
			?>
			<script>
				try {
			window.onmessage = (event) => {
				var rawData = event.data;

				if (!rawData || typeof rawData !== 'string' || typeof rawData.indexOf === 'undefined' || rawData.indexOf('{') < 0) {
					return true;
				}

				try {
					var data = JSON.parse(rawData);
				} catch (e) {
					return true;
				}

				if( data.functionName === 'wpfaSetIframeState'){	
					var parentUrl = data.arguments.url;			
					var keywordsAllowed = data.arguments.allowed_frontend_keywords_in_iframe;
					// If the BB builder loaded inside our iframe, redirect to the parent window in a way compatible with CORS
					if (window.parent !== window && window.location.href.indexOf('?fl_builder') > -1) {
						window.parent.postMessage(JSON.stringify({
							'functionName': 'vgfaNavigateTo',
							'arguments': window.location.href,
							'iframeId': null
						}), '*');
					} else if (parentUrl !== window.location.href) {

						var keywordFound = false;
						keywordsAllowed.forEach(function (keyword) {
							if (keyword && (window.location.href.indexOf(keyword) > -1 || parentUrl.indexOf(keyword) > -1)) {
								keywordFound = true;
							}
						});
						// Redirect if it is an url
						if (!keywordFound && window.location.href.indexOf('http') > -1) {								
							window.parent.postMessage(JSON.stringify({
								'functionName': 'vgfaNavigateTo',
								'arguments': window.location.href.split('#')[0],
								'iframeId': null
							}), '*');
						}
					}
				}
			};

				} finally {
				}
			</script>
			<?php
		}

		/**
		 * Redirect whitelisted pages to the frontend dashboard only if they're 
		 * viewed outside the iframe (frontend dashboard)
		 * @return null
		 */
		function enforce_frontend_dashboard() {
			$is_blacklisted = $this->is_page_blacklisted();
			$skip_frontend_dashboard_enforcement = apply_filters('vg_admin_to_frontend/skip_frontend_dashboard_enforcement', $is_blacklisted || !is_user_logged_in() || !is_admin() || current_user_can(VG_Admin_To_Frontend_Obj()->master_capability()) || wp_doing_ajax() || wp_doing_cron(), $is_blacklisted);
			if ($skip_frontend_dashboard_enforcement) {
				return;
			}

			$whitelisted_capability = VG_Admin_To_Frontend_Obj()->get_settings('whitelisted_user_capability');
			if (current_user_can($whitelisted_capability)) {
				return;
			}

			$url = remove_query_arg(array('post'), VG_Admin_To_Frontend_Obj()->get_current_url());
			$url_path = VG_Admin_To_Frontend_Obj()->get_admin_url_without_base($url);

			$backend_urls_forced_outside_iframe = VG_Admin_To_Frontend_Obj()->get_backend_urls_forced_frontend();
			foreach ($backend_urls_forced_outside_iframe as $backend_url_allowed_outside_iframe) {
				if (strpos($url_path, $backend_url_allowed_outside_iframe) !== false) {
					return;
				}
			}
			$redirect_to = WPFA_Global_Dashboard_Obj()->get_frontend_dashboard_page_url_for_backend_url($url_path);

			if (empty($redirect_to)) {
				return;
			}
			// We must remove the "page" arg because it's a special query var that makes WP load the wrong 404 page
			$redirect_to = remove_query_arg(array('post_type', 'page'), add_query_arg(array_merge($_GET, array('vgfa_frontend_url' => 1)), $redirect_to));
			?>
			<script>
				// If it's not an iframe and it is an URL, redirect the parent window to the frontend url
				if (window === window.parent && window.location.href.indexOf('http') > -1) {
					window.parent.location.href = <?php echo json_encode(esc_url_raw($redirect_to)); ?>;
				}
			</script>
			<?php
		}

		function is_page_blacklisted() {
			$whitelisted = VG_Admin_To_Frontend_Obj()->get_settings('whitelisted_admin_urls');
			$whitelisted_capability = VG_Admin_To_Frontend_Obj()->get_settings('whitelisted_user_capability');
			$restrictions_are_enabled = VG_Admin_To_Frontend_Obj()->get_settings('enable_wpadmin_access_restrictions');

			if (!is_user_logged_in() || empty($restrictions_are_enabled) || empty($whitelisted) || empty($whitelisted_capability) || (!empty($whitelisted_capability) && current_user_can($whitelisted_capability)) || VG_Admin_To_Frontend_Obj()->is_master_user() || strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
				return apply_filters('vg_admin_to_frontend/is_page_blacklisted', false, $whitelisted, $whitelisted_capability, $restrictions_are_enabled);
			}

			// Don't apply the access restrictions if the user is administrator
			// and we're viewing the settings page, so the admin can change settings
			// and not be locked out.
			if (is_admin() && current_user_can('manage_options') && (!empty($_GET['page']) && $_GET['page'] === 'vg_admin_to_frontend')) {
				return apply_filters('vg_admin_to_frontend/is_page_blacklisted', false, $whitelisted, $whitelisted_capability, $restrictions_are_enabled);
			}

			if (wp_doing_ajax()) {
				// Allow ajax requests from users with low capabilities because it's secure by default
				// We apply the blacklist on ajax requests from users with administrator capabilities only
				// as an extra layer of security
				if (!current_user_can('manage_options')) {
					return apply_filters('vg_admin_to_frontend/is_page_blacklisted', false, $whitelisted, $whitelisted_capability, $restrictions_are_enabled);
				}

				// We allow ajax requests coming from the frontend
				$url = wp_get_referer();
				$admin_url = admin_url();
				if (strpos($url, $admin_url) === false) {
					return apply_filters('vg_admin_to_frontend/is_page_blacklisted', false, $whitelisted, $whitelisted_capability, $restrictions_are_enabled);
				}
			}
			if (empty($url)) {
				$url = VG_Admin_To_Frontend_Obj()->get_current_url();
			}
			$url_path = VG_Admin_To_Frontend_Obj()->prepare_loose_url($url);
			$whitelisted_urls = array_map('trim', preg_split('/\r\n|\r|\n/', $whitelisted));

			$allowed = false;
			if (!empty($url_path)) {
				foreach ($whitelisted_urls as $whitelisted_url) {
					if (preg_match('/(any_parameter_value|any_single_number|any_numbers|any_letter|any_letters)/', $whitelisted_url)) {
						$whitelisted_url_loose = VG_Admin_To_Frontend_Obj()->prepare_loose_url($whitelisted_url);
						$whitelisted_url_loose = str_replace(array('%7B', '%7D'), array('{', '}'), $whitelisted_url_loose);
						$whitelisted_url_literal = preg_quote($whitelisted_url_loose, '/');
						$whitelisted_url_regex = str_replace('\{any_parameter_value\}', '([A-Za-z0-9-_]+)', $whitelisted_url_literal);
						$whitelisted_url_regex = str_replace('\{any_single_number\}', '\d', $whitelisted_url_regex);
						$whitelisted_url_regex = str_replace('\{any_numbers\}', '\d+', $whitelisted_url_regex);
						$whitelisted_url_regex = str_replace('\{any_letter\}', '[a-zA-Z]', $whitelisted_url_regex);
						$whitelisted_url_regex = str_replace('\{any_letters\}', '[a-zA-Z]+', $whitelisted_url_regex);
						$whitelisted_url_regex = '/' . $whitelisted_url_regex . '/';
						if (preg_match($whitelisted_url_regex, $url_path)) {
							$allowed = true;
							break;
						}
					} elseif (strpos($whitelisted_url, '/' . $url_path) !== false || $whitelisted_url === $url_path) {
						$allowed = true;
						break;
					}
				}
			}

			if ($allowed) {
				return apply_filters('vg_admin_to_frontend/is_page_blacklisted', false, $whitelisted, $whitelisted_capability, $restrictions_are_enabled);
			}

			return apply_filters('vg_admin_to_frontend/is_page_blacklisted', true, $whitelisted, $whitelisted_capability, $restrictions_are_enabled);
		}

		function apply_page_blacklist() {
			$is_blacklisted = $this->is_page_blacklisted();
			if (!$is_blacklisted) {
				return;
			}
			if (wp_doing_ajax()) {
				die('0');
			}

			$default_url = add_query_arg(array('vgfa_blacklisted_url' => 1), VG_Admin_To_Frontend_Obj()->get_settings('redirect_to_frontend', home_url('/')));
			$redirect_to = VG_Admin_To_Frontend_Obj()->get_settings('blacklisted_page_url', $default_url);
			wp_redirect(esc_url_raw($redirect_to));
			exit();
		}

		function remove_customizer_frame_headers() {
			global $wp_customize;

			if ($wp_customize && $wp_customize->is_preview() && !is_admin() && current_user_can('customize')) {
				remove_filter('wp_headers', array($wp_customize, 'filter_iframe_security_headers'));
			}
		}

		function allow_iframes_on_origin() {
			if (headers_sent()) {
				return;
			}
			// Don't add the http header if the h5p plugin is activated and this URL is the h5p embed page
			$current_url = VG_Admin_To_Frontend_Obj()->get_current_url();
			$h5p_url_base = admin_url('admin-ajax.php?action=h5p_embed&id=');
			if (class_exists('H5P_Plugin') && has_action('wp_ajax_nopriv_h5p_embed') && strpos($current_url, $h5p_url_base) !== false) {
				return;
			}
			$is_customizer_request = !empty($_GET['customize_changeset_uuid']) && !is_admin() && current_user_can('customize');
			$is_beaver_builder_editor = isset($_GET['fl_builder']) && class_exists('FLBuilderLoader') && !is_admin() && current_user_can('edit_posts');
			$is_dashboard_site = WPFA_Global_Dashboard_Obj()->is_global_dashboard();
			$is_elementor_editor = ! is_admin() && ! empty($_GET['elementor-preview']) && current_user_can('edit_post', (int) $_GET['elementor-preview']);
			if (is_admin() || $is_dashboard_site || $is_customizer_request || $is_beaver_builder_editor || $is_elementor_editor) {
				$host = VG_Admin_To_Frontend_Obj()->get_settings('root_domain');
				if (empty($host)) {
					$info = parse_url(get_site_url(1));
					$host = $info['host'];
				}
				if (strpos($host, 'www.') === 0) {
					$host = str_replace('www.', '', $host);
				}

				$current_site_info = parse_url(get_site_url());
				$current_site_host = $current_site_info['host'];

				$hosts = array_unique(array($current_site_host, $host, '*.' . $host));

				header("Content-Security-Policy: frame-ancestors 'self' " . implode(' ', $hosts));
				add_action('wp_loaded', array($this, 'remove_customizer_frame_headers'), 20);
			}
		}

		/**
		 * Creates or returns an instance of this class.
		 */
		static function get_instance() {
			if (null == WPFA_Access_Restrictions::$instance) {
				WPFA_Access_Restrictions::$instance = new WPFA_Access_Restrictions();
				WPFA_Access_Restrictions::$instance->init();
			}
			return WPFA_Access_Restrictions::$instance;
		}

		function __set($name, $value) {
			$this->$name = $value;
		}

		function __get($name) {
			return $this->$name;
		}

	}

}

if (!function_exists('WPFA_Access_Restrictions_Obj')) {

	function WPFA_Access_Restrictions_Obj() {
		return WPFA_Access_Restrictions::get_instance();
	}

}
WPFA_Access_Restrictions_Obj();
