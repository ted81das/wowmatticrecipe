<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for FlowMattic.
 */
class FlowMattic_Admin {

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {

		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 12 );

		// Add custom query var to handle webhook urls.
		add_filter( 'query_vars', array( $this, 'webhook_query_vars' ) );

		// Add custom endpoint for webhook url to avoid 404.
		add_action( 'init', array( $this, 'webhook_endpoint' ) );

		// Parse http request to see if the webhook url slug is there.
		add_action( 'parse_request', array( $this, 'webhook_parse_request' ), 1 );

		// Customize admin for Chatbot settings.
		add_action( 'admin_init', array( $this, 'customize_admin_for_chatbot' ), 1 );

		// Ajax to save the application Authentication data.
		add_action( 'wp_ajax_flowmattic_save_app_authentication', array( $this, 'flowmattic_save_app_authentication' ) );

		// Ajax to save settings.
		add_action( 'wp_ajax_flowmattic_save_settings', array( $this, 'flowmattic_save_settings' ) );
	}

	/**
	 * Process the ajax to save the application authentications.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function flowmattic_save_app_authentication() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		$authn_data = $_POST;

		$authentication_data = get_option( 'flowmattic_auth_data', array() );

		if ( ! isset( $authentication_data[ $authn_data['workflow_id'] ] ) ) {
			$authentication_data[ $authn_data['workflow_id'] ] = array();
		}

		$authentication_data[ $authn_data['workflow_id'] ][ $authn_data['application'] ] = array(
			'auth_data' => $authn_data['authData'],
		);

		$status = update_option( 'flowmattic_auth_data', $authentication_data, false );

		$reply = array(
			'status' => $status,
		);

		echo wp_json_encode( $reply );

		die();
	}

	/**
	 * Process the ajax to save the settings.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function flowmattic_save_settings() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		$form_data = $_POST;
		$settings  = $form_data['settings'];

		// Get cron array.
		$crons = _get_cron_array();

		foreach ( $crons as $timestamp => $cron ) {
			foreach ( $cron as $hook => $args ) {
				if ( 'flowmattic_task_cleanup_cron' === $hook ) {
					$args = array_values( $args )[0]['args'];

					// Unschedule the cron for cleanup.
					$unschedued = wp_unschedule_event( $timestamp, 'flowmattic_task_cleanup_cron', $args, true );

					if ( ! empty( $cron[ $hook ] ) ) {
						unset( $crons[ $timestamp ][ $hook ] );
					}

					if ( empty( $crons[ $timestamp ] ) ) {
						unset( $crons[ $timestamp ] );
					}
				}
			}
		}

		// Update the cron array.
		_set_cron_array( $crons );

		// Schedule the cron for cleanup.
		wp_schedule_event( time(), 'daily', 'flowmattic_task_cleanup_cron', array( 'clean_before' => $settings['task_clean_interval'] . ' Days' ) );

		$status = update_option( 'flowmattic_settings', $settings, false );

		$reply = array(
			'status' => $status,
		);

		echo wp_json_encode( $reply );

		die();
	}

	/**
	 * Add dynmic webhook vars.
	 *
	 * @access public
	 * @since 1.0
	 * @param array $query_vars Current query vars.
	 * @return array Updated webhook vars.
	 */
	public function webhook_query_vars( $query_vars ) {
		$query_vars[] = 'webhook';
		$query_vars[] = 'capture';

		return $query_vars;
	}

	/**
	 * Add rewrite rule for webhook url.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function webhook_endpoint() {
		add_rewrite_tag( '%webhook%', '([^&]+)', 'capture=' );
		add_rewrite_endpoint( 'webhook', EP_ALL );
		add_rewrite_rule( '^webhook/capture/([^/]*)/?', 'index.php?webhook=$matches[1]&capture=$matches[2]', 'top' );

		// Flush rewrite rules.
		$flush_rules = get_transient( 'flowmattic_integrations' );
		if ( false === $flush_rules ) {
			flush_rewrite_rules();
			set_transient( 'flowmattic_flush_rewrite_rules', 'true', HOUR_IN_SECONDS * 24 );
		}
	}

	/**
	 * Get hearder Authorization.
	 *
	 * @since 2.0
	 * @return string
	 * */
	public function get_authorization_key() {
		$headers = false;

		if ( isset( $_SERVER['Authorization'] ) ) {
			$headers = trim( $_SERVER['Authorization'] );
		} elseif ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) { // Nginx or fast CGI.
			$headers = trim( $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( function_exists( 'apache_request_headers' ) ) {
			$request_headers = apache_request_headers();

			// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization).
			$request_headers = array_combine( array_map( 'ucwords', array_keys( $request_headers ) ), array_values( $request_headers ) );

			if ( isset( $request_headers['Authorization'] ) ) {
				$headers = trim( $request_headers['Authorization'] );
			}
		}

		$authorization_key = '';

		if ( ! empty( $headers ) ) {
			// Get the bearer access token from the header.
			if ( preg_match( '/Bearer\s(\S+)/', $headers, $matches ) ) {
				$authorization_key = $matches[1];
			}

			// Get the basic authorization from the header.
			if ( preg_match( '/Basic\s(\S+)/', $headers, $matches ) ) {
				$authorization_decode = base64_decode( $matches[1] ); // @codingStandardsIgnoreLine
				$authorization_split  = explode( ':', $authorization_decode );
				$authorization_key    = ( 'flowmattic' === $authorization_split[0] ) ? $authorization_split[1] : false;
			}
		}

		return $authorization_key;
	}

	public function check_webhook_url_get_id() {
		$part_after_capture = '';

		// Get the current URL.
		$current_url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

		// Parse the URL into its components.
		$url_components = wp_parse_url( $current_url );

		// Check if the path component exists.
		if ( isset( $url_components['path'] ) ) {
			// Split the path component at the '/' character.
			$path_segments = explode( '/', $url_components['path'] );

			// Check if the URL follows the desired structure.
			if ( count( $path_segments ) >= 4 && $path_segments[1] == 'webhook' && $path_segments[2] == 'capture' ) {
				// Get the part of the URL after 'capture/'.
				$part_after_capture = $path_segments[3];
			}
		}

		return $part_after_capture;
	}

	/**
	 * Proces webhook and redirect to requested url or to home page if no url is provided.
	 *
	 * @since 1.0
	 * @param object $query Current query.
	 * @return void
	 */
	public function webhook_parse_request( $query ) {
		ob_start();
		if ( array_key_exists( 'webhook', $query->query_vars ) && array_key_exists( 'capture', $query->query_vars ) ) {
			$webhook_id = $query->query_vars['webhook'];

			// $webhook_id = $this->check_webhook_url_get_id();
			// if ( '' !== $webhook_id ) {
			$data               = array();
			$processed_response = array();
			$workflow_data      = array();
			$webhook_auth_key   = '';
			$webhook_response   = '';
			$webhook_redirect   = false;
			$is_capturing       = ( get_option( 'webhook-capture-live', false ) === $webhook_id );

			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			// Check if request is for OAuth Connect.
			if ( 'fm_oauth' === $webhook_id ) {
				if ( ! empty( $_POST ) ) {
					$auth_data = $_POST;
				} else {
					$auth_data = isset( $_GET['authData'] ) ? $_GET['authData'] : '';
					if ( '' !== $auth_data ) {
						$auth_data = json_decode( base64_decode( $auth_data ), true );
					}
				}

				if ( ! empty( $auth_data ) ) {
					$connect_id   = isset( $auth_data['connect_id'] ) ? $auth_data['connect_id'] : '';
					$credentials  = isset( $auth_data['credentials'] ) ? json_decode( stripslashes( $auth_data['credentials'] ), true ) : '';
					$connect_data = isset( $auth_data['credentials'] ) ? base64_encode( stripslashes( $auth_data['credentials'] ) ) : '';

					// If token is set to be expired, register a cron to renew it later.
					if ( isset( $credentials['expires_in'] ) ) {
						do_action( 'flowmattic_connect_register_cron', $credentials, $connect_id );
					}

					// Update the data for temp. use in options table.
					update_option( 'fm_auth_data_' . $connect_id, $connect_data );
				}

				// Return default response.
				$response = wp_json_encode(
					array(
						'status'  => 'success',
						'message' => 'Response Captured',
					)
				);

				// Set the request content type.
				header( 'Content-Type: application/json' );

				// Set Access-Control-Allow-Origin header.
				header( 'Access-Control-Allow-Origin: *' );

				// Set User-Agent header.
				header( 'User-Agent: FlowMattic/' . FLOWMATTIC_VERSION );

				die( $response );
			}

			// Get the workflow settings.
			$args     = array(
				'workflow_id' => $webhook_id,
			);
			$workflow = wp_flowmattic()->workflows_db->get( $args );

			if ( $workflow ) {
				$steps    = json_decode( $workflow->workflow_steps, true );
				$settings = json_decode( $workflow->workflow_settings, true );

				// Get the trigger step data.
				$workflow_data = $steps[0];
			}

			// Check if webhook security is enabled, and authenticate the request.
			if ( ! $is_capturing ) {
				// Get the authentication key.
				if ( isset( $workflow_data['webhook_security'] ) && 'Yes' === $workflow_data['webhook_security'] ) {
					$webhook_auth_key = $workflow_data['workflow_auth_key'];
				}

				// Get the custom response.
				if ( isset( $workflow_data['webhook_response'] ) && 'Yes' === $workflow_data['webhook_response'] ) {
					$webhook_response = $workflow_data['webhook_custom_responce'];
				}
			} else {
				$webhook_auth_key = get_option( 'webhook-authentication-key-' . $webhook_id, '' );
				$webhook_response = get_option( 'webhook-response-text-' . $webhook_id, '' );
			}

			// If webhook redirect is set.
			if ( isset( $workflow_data['workflowEndAction'] ) && 'redirect' === $workflow_data['workflowEndAction'] ) {
				$webhook_redirect = true;
			}

			// If authentication key is present, authenticate the request.
			if ( '' !== $webhook_auth_key ) {
				$authorization_key = $this->get_authorization_key();

				if ( $webhook_auth_key !== $authorization_key ) {
					$response = array(
						'status'  => 'error',
						'message' => 'Authentication failed',
					);

					// Set the request content type.
					header( 'Content-Type: application/json' );

					// Set Access-Control-Allow-Origin header.
					header( 'Access-Control-Allow-Origin: *' );

					// Set User-Agent header.
					header( 'User-Agent: FlowMattic/' . FLOWMATTIC_VERSION );

					// Set error code.
					http_response_code( 401 );

					// Throw back the error.
					die( wp_json_encode( $response ) );
				}

				// Delete the authentication key option.
				delete_option( 'webhook-authentication-key-' . $webhook_id );

				// Delete the webhook response key option.
				delete_option( 'webhook-response-text-' . $webhook_id );
			}

			// Remove WordPress query from get request.
			unset( $_GET['q'] );

			// Remove WooCommerce login nonces from request.
			unset( $_REQUEST['woocommerce-login-nonce'] );
			unset( $_REQUEST['_wpnonce'] );
			unset( $_REQUEST['wpnonce'] );
			unset( $_REQUEST['woocommerce-reset-password-nonce'] );

			// Capture all data as JSON.
			$json_data = '';

			if ( isset( $_REQUEST['rawRequest'] ) ) {
				$data   = $_REQUEST;
				$result = json_decode( stripslashes( $data['rawRequest'] ), true );
				$data   = flowmattic_recursive_array( $data, 'rawRequest', $result );
				unset( $data['rawRequest'] );
			} elseif ( ! empty( $_GET ) ) {
				$data = array_merge( $_GET, $_REQUEST );
				unset( $data['q'] );
			} elseif ( empty( $_POST ) && is_array( $_REQUEST ) && ! empty( $_REQUEST ) ) { // @codingStandardsIgnoreLine
				$data = $_REQUEST;
			}

			$json_data    = wp_json_encode( $data );
			$is_mail_hook = false;

			if ( empty( $data ) ) {

				global $wp_filesystem;

				// Check the simple response option.
				$simple_response = ( ! isset( $workflow_data['simple_response'] ) || 'Yes' === $workflow_data['simple_response'] ) ? true : false;

				if ( empty( $wp_filesystem ) ) {
					// Include the WordPress file handling functions.
					require_once ABSPATH . '/wp-admin/includes/file.php';
					WP_Filesystem();
				}

				// Uploaded files.
				$uploaded_files = array();

				// Check if a file is uploaded.
				if ( isset( $_FILES ) && ! empty( $_FILES ) ) {
					// Include the WordPress media handling functions.
					require_once ABSPATH . '/wp-admin/includes/media.php';
					require_once ABSPATH . '/wp-admin/includes/image.php';

					// Get the files.
					$files = $_FILES;

					$uploaded_files['files'] = array();

					// Loop through all files.
					$file_number = 1;
					foreach ( $files as $key => $file ) {
						if ( isset( $file['tmp_name'] ) && '' !== $file['tmp_name'] ) {
							$upload_overrides = array(
								'test_form' => false,
								'test_size' => false,
								'test_type' => false,
								'action'    => 'flowmattic_handle_upload',
							);

							$media = media_handle_sideload( $file, 0, null, $upload_overrides );

							if ( ! is_wp_error( $media ) ) {
								// Assign the URL to the file.
								$uploaded_files['files'][ 'file_' . $file_number ] = array(
									'file_name'     => $file['name'],
									'file_url'      => wp_get_attachment_url( $media ),
									'attachment_id' => $media,
								);
							}

							++$file_number;
						}
					}
				}

				$data = $wp_filesystem->get_contents( 'php://input' );

				if ( '' === $data ) {
					$data = wp_json_encode( $_POST ); // @codingStandardsIgnoreLine
				}

				if ( is_array( json_decode( $data, true ) ) ) {
					$json_data      = $data;
					$response_array = json_decode( $data, true );

					if ( is_array( $response_array ) && isset( $response_array['flowmattic_source'] ) && 'mailhook' === $response_array['flowmattic_source'] ) {
						// Remove the placeholder for mailhook.
						unset( $response_array['flowmattic_source'] );

						$is_mail_hook = true;

						// If attachments, store them in DB and get URL.
						if ( isset( $response_array['attachments'] ) && ! empty( $response_array['attachments'] ) ) {
							$attachments = $response_array['attachments'];

							// Loop through all attachments.
							foreach ( $attachments as $key => $attachment ) {
								$filename     = $attachment['file_name'];
								$base64_file  = $attachment['file_content'];
								$content_type = $attachment['content_type'];

								// Upload the file to WP.
								$wp_attachment = flowmattic_import_file_from_mailhook( $filename, $base64_file, $content_type );

								// Assign the WP file to attachment.
								$attachments[ $key ] = $wp_attachment;

								// If file is CSV, parse it.
								if ( 'text/csv' === $content_type ) {
									$parse_csv = flowmattic_parse_csv( $base64_file );

									// Add the parsed CSV data to response.
									$csv_data_name                    = 'csv_' . $key . '_data';
									$response_array[ $csv_data_name ] = $parse_csv['data'];

									if ( isset( $parse_csv['error'] ) ) {
										$response_array[ $csv_data_name . '_error' ] = $parse_csv['error'];
									}
								}
							}

							// Assign the updated array back to capture data.
							$response_array['attachments'] = wp_json_encode( $attachments );
						}

						// Parse emails from the email message text.
						$email_text = $response_array['message_text'];

						// Get the emails from message text.
						$parse_emails = flowmattic_parse_emails_from_mailhook( $email_text );

						if ( ! empty( $parse_emails ) ) {
							// Assign the emails to capture data.
							$response_array['emails_in_message_array'] = wp_json_encode( $parse_emails['array'] );
							$response_array['emails_in_message_list']  = $parse_emails['list'];
						}

						// Fix HTML encoding.
						$response_array['message_html'] = esc_attr( $response_array['message_html'] );
					}

					// If Freshdesk, simplify the array.
					if ( isset( $response_array['freshdesk_webhook'] ) ) {
						$response_array = $response_array['freshdesk_webhook'];
					}

					// If uploaded files, add them to the response.
					if ( ! empty( $uploaded_files ) ) {
						$response_array['webhook_files'] = $uploaded_files['files'];
					}

					foreach ( $response_array as $key => $value ) {
						if ( is_array( $value ) ) {
							if ( ! $simple_response ) {
								$processed_response[ $key ] = wp_json_encode( $value );
							} else {
								$processed_response = flowmattic_recursive_array( $processed_response, $key, $value );
							}
						} else {
							$processed_response[ $key ] = $value;
						}
					}

					$data = $processed_response;
				} elseif ( false !== strpos( $data, '=' ) ) {
					parse_str( $data, $output );

					// Set the json data.
					$json_data = wp_json_encode( $output );

					// If uploaded files, add them to the response.
					if ( ! empty( $uploaded_files ) ) {
						$output['webhook_files'] = $uploaded_files['files'];
					}

					foreach ( $output as $key => $value ) {
						if ( is_array( $value ) ) {
							if ( ! $simple_response ) {
								$processed_response[ $key ] = wp_json_encode( $value );
							} else {
								$processed_response = flowmattic_recursive_array( $processed_response, $key, $value );
							}
						} else {
							$processed_response[ $key ] = $value;
						}
					}

					$data = $processed_response;
				}
			}

			// If data is not an array, convert it to an array.
			if ( ! is_array( $data ) ) {
				$data = array( 'response' => $data );
			}

			if ( ! $is_mail_hook ) {
				// Get all headers.
				$headers = getallheaders();

				// Add headers to the data.
				$data['headers'] = wp_json_encode( $headers );
			}

			// Assign the json data to the data array.
			if ( '' !== $json_data ) {
				$data['fm_webhook_data'] = $json_data;
			}

			// Add the webhook capture time.
			$data['webhook_captured_at'] = date_i18n( 'd-m-Y H:i:s' );

			// Add workflow ID to workflow data for compatibility.
			$workflow_data['workflow_id'] = $webhook_id;

			// Filter the captured data globally.
			$data = apply_filters( 'flowmattic_webhook_captured_data', $data, $workflow_data );

			// Filter the captured data by webhook.
			$capture_data = apply_filters( 'webhook_captured_data_' . $webhook_id, $data );

			// Fire an action to perform additional data check etc.
			do_action( 'flowmattic_webhook_response_captured', $webhook_id, $capture_data );

			if ( $is_capturing ) {
				update_option( 'webhook-capture-' . $webhook_id, $capture_data, false );
				delete_option( 'webhook-capture-live' );
				delete_option( 'webhook-capture-application' );
			} else {
				// If webhook is not in capturing mode, but the response is not yet captured, set it to be captured.
				if ( ! empty( $workflow_data ) && ! isset( $workflow_data['capturedData'] ) ) {
					update_option( 'webhook-capture-' . $webhook_id, $capture_data, false );
				}

				// Let the server breathe a little.
				// wait for 25 milliseconds.
				usleep( 25000 );

				// Run the workflow.
				$flowmattic_workflow = new FlowMattic_Workflow();
				$flowmattic_workflow->run( $webhook_id, $capture_data );
			}

			// TODO: Implement the termination of current incoming request immediately,
			// to avoid the unwanted delays in response.
			// fastcgi_finish_request(); // @codingStandardsIgnoreLine

			if ( '' !== $webhook_response ) {
				$response = stripslashes( $webhook_response );
				$response = ( ! is_array( json_decode( $response, true ) ) ) ? $response : json_decode( $response, true );
			} else {
				$response = array(
					'status'  => 'success',
					'message' => 'Response Captured',
				);
			}

			$buffer = ob_get_clean();

			// If webhook redirect, redirect to the URL.
			if ( $webhook_redirect ) {
				$redirect_url = isset( $workflow_data['workflowEndActionURL'] ) ? $workflow_data['workflowEndActionURL'] : '';

				// Filter for custom redirect.
				$redirect_url = apply_filters( 'flowmattic_webhook_redirect', $redirect_url, $webhook_id, $capture_data );

				if ( '' !== $redirect_url ) {
					// Redirect to the URL.
					wp_redirect( $redirect_url );
					exit;
				}
			}

			// Filter for custom response.
			$response = apply_filters( 'flowmattic_webhook_response', $response, $webhook_id, $capture_data );

			if ( isset( $capture_data['hub_challenge'] ) ) {
				$response = $capture_data['hub_challenge'];
				die( $response );
			} elseif ( isset( $capture_data['challenge'] ) ) {
				$response = $capture_data['challenge'];
				die( $response );
			} elseif ( is_array( $response ) ) {
					// Set the request content type.
					header( 'Content-Type: application/json' );

					// Set Access-Control-Allow-Origin header.
					header( 'Access-Control-Allow-Origin: *' );

					// Set User-Agent header.
					header( 'User-Agent: FlowMattic/' . FLOWMATTIC_VERSION );

					die( wp_json_encode( $response ) );
			} else {
				die( $response );
			}
		}
	}

	/**
	 * Admin Menu.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function admin_menu() {
		global $submenu;

		if ( ! current_user_can( 'manage_workflows' ) ) {
			return;
		}

		// Set the menu slug.
		$menu_slug = 'flowmattic';

		// Check if integrations have an update available.
		$is_integration_update = flowmattic_is_integration_update_available();
		$update_bubble         = $is_integration_update ? ' <span class="update-plugins count-' . $is_integration_update . '"><span class="update-count">' . $is_integration_update . '</span></span>' : '';

		if ( $is_integration_update ) {
			add_action( 'admin_notices', array( $this, 'integration_update_notice' ) );
		}

		if ( isset( $_GET['page'] ) && false !== strpos( $_GET['page'], 'flowmattic' ) ) {
			$menu_slug = ( isset( $_GET['flowmattic-action'] ) ) ? 'flowmattic' : 'flowmattic-menu';
		}

		$icon = FLOWMATTIC_PLUGIN_URL . 'assets/admin/img/icon-w.svg';

		$menu_title = 'flowmattic-menu' === $menu_slug ? esc_attr__( 'FlowMattic', 'flowmattic' ) : esc_attr__( 'FlowMattic', 'flowmattic' ) . $update_bubble;
		$welcome    = add_menu_page( esc_attr__( 'FlowMattic', 'flowmattic' ), $menu_title, 'manage_workflows', 'flowmattic', array( $this, 'dashboard' ), $icon, '4.222222' );

		$settings            = get_option( 'flowmattic_settings', array() );
		$integrations_access = isset( $settings['integration_page_access'] ) ? $settings['integration_page_access'] : 'yes';

		if ( 'yes' === $integrations_access || current_user_can( 'manage_options' ) ) {
			$integrations = add_submenu_page( $menu_slug, esc_attr__( 'Integrations', 'flowmattic' ), esc_attr__( 'Integrations', 'flowmattic' ) . $update_bubble, 'manage_workflows', 'flowmattic-integrations', array( $this, 'integrations_tab' ) );
			add_action( 'admin_print_scripts-' . $integrations, array( $this, 'admin_scripts' ) );
		}

		$connects    = add_submenu_page( $menu_slug, esc_attr__( 'Connects', 'flowmattic' ), esc_attr__( 'Connects', 'flowmattic' ), 'manage_workflows', 'flowmattic-connects', array( $this, 'connects_tab' ) );
		$custom_apps = add_submenu_page( $menu_slug, esc_attr__( 'Custom Apps', 'flowmattic' ), esc_attr__( 'Custom Apps', 'flowmattic' ), 'manage_workflows', 'flowmattic-custom-apps', array( $this, 'custom_apps_tab' ) );
		$workflow    = add_submenu_page( $menu_slug, esc_attr__( 'Workflows', 'flowmattic' ), esc_attr__( 'Workflows', 'flowmattic' ), 'manage_workflows', 'flowmattic-workflows', array( $this, 'workflows_tab' ) );

		// Add Chatbot menu.
		$chatbot = add_submenu_page( $menu_slug, esc_attr__( 'AI Chatbot', 'flowmattic' ), esc_attr__( 'AI Chatbot', 'flowmattic' ), 'manage_workflows', 'flowmattic-chatbot', array( $this, 'chatbot_tab' ) );

		// Add Variables menu.
		$variables = add_submenu_page( $menu_slug, esc_attr__( 'Variables', 'flowmattic' ), esc_attr__( 'Variables', 'flowmattic' ), 'manage_workflows', 'flowmattic-variables', array( $this, 'variables_tab' ) );

		$task_history = add_submenu_page( $menu_slug, esc_attr__( 'Task History', 'flowmattic' ), esc_attr__( 'History', 'flowmattic' ), 'manage_workflows', 'flowmattic-task-history', array( $this, 'task_history_tab' ) );

		if ( current_user_can( 'manage_options' ) ) {
			$settings = add_submenu_page( $menu_slug, esc_attr__( 'Settings', 'flowmattic' ), esc_attr__( 'Settings', 'flowmattic' ), 'manage_workflows', 'flowmattic-settings', array( $this, 'settings_tab' ) );
			add_action( 'admin_print_scripts-' . $settings, array( $this, 'admin_scripts' ) );

			$status = add_submenu_page( $menu_slug, esc_attr__( 'Status Page', 'flowmattic' ), esc_attr__( 'Status', 'flowmattic' ), 'manage_workflows', 'flowmattic-status', array( $this, 'status_tab' ) );
			add_action( 'admin_print_scripts-' . $status, array( $this, 'admin_scripts' ) );

			$license = add_submenu_page( $menu_slug, esc_attr__( 'License Page', 'flowmattic' ), esc_attr__( 'License', 'flowmattic' ), 'manage_workflows', 'flowmattic-license', array( $this, 'license_tab' ) );
			add_action( 'admin_print_scripts-' . $license, array( $this, 'admin_scripts' ) );
		}

		if ( 'flowmattic' === $menu_slug ) {
			// Change the first menu item name.
			$submenu['flowmattic'][0][0] = esc_attr__( 'Dashboard', 'flowmattic' ); // phpcs:ignore
		}

		add_action( 'admin_print_scripts-' . $welcome, array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_scripts-' . $connects, array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_scripts-' . $custom_apps, array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_scripts-' . $workflow, array( $this, 'workflow_admin_scripts' ) );
		add_action( 'admin_print_scripts-' . $chatbot, array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_scripts-' . $variables, array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_scripts-' . $task_history, array( $this, 'admin_scripts' ) );
		add_action( 'admin_footer-' . $workflow, array( $this, 'enqueue_templates' ), 999 );

		add_action( 'admin_head', array( $this, 'enqueue_global_styles' ), 10 );
	}

	/**
	 * Add integration update notification to admin.
	 *
	 * @access public
	 * @since 3.1.1
	 * @return void
	 */
	public function integration_update_notice() {
		$update_count = flowmattic_is_integration_update_available();
		?>
		<div class="notice notice-warning is-dismissible">
			<p><strong>FlowMattic:</strong> <?php echo sprintf( __( 'You have <strong>%1$d</strong> integration updates available. <a href="%2$s">Click here</a> to update now!', 'flowmattic' ), esc_attr( $update_count ), 'admin.php?page=flowmattic-integrations&tab=update' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Admin global styles for logo and other styling.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function enqueue_global_styles() {
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			jQuery( 'body' ).addClass( 'flowmattic-loaded' );
		} );
		</script>
		<style type="text/css">
		<?php
		// Compatibility with uipress.
		if ( class_exists( 'uipress_controller' ) ) {
			?>
			.flowmattic-wrap {
				margin-left: 20px !important;
				padding-left: 12px;
			}
			.flowmattic-wrap .flowmattic-workflow-header {
				top: 64px !important;
			}
			.fm-workflow-footer {
				top: 82px !important;
			}
			.flowmattic-sidebar-wrapper {
				top: 140px !important;
			}
			<?php
		}
		?>
		.flowmattic_page_flowmattic-workflows #wpfooter {
			left: 60px;
		}
		.toplevel_page_flowmattic img {
			width: 19px;
			height: auto;
		}
		.flowmattic-wrap {
			opacity: 0;
		}
		.fs-notice,
		.stm-plugin-admin-notice,
		.flowmattic-loaded .fm-loading-artboard {
			display: none;
		}
		.flowmattic-loaded .flowmattic-wrap {
			opacity: 1;
		}
		.fm-loading-artboard {
			width: 100%;
			height: 100%;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-direction: row;
			flex-wrap: wrap;
			position: fixed;
			background: #fff;
			z-index: auto;
			top: 0;
			left: 0;
		}
		.fm-loading {
			position: relative;
			z-index: 99999;
		}
		.fm-loading {
			-webkit-animation: rotation 1s infinite;
			animation: rotation 1s infinite;
		}
		.fm-loading.fm-loading .shape {
			width: 12px;
			height: 12px;
			border-radius: 2px;
		}
		.fm-loading .shape {
			position: absolute;
			width: 10px;
			height: 10px;
			border-radius: 1px;
		}
		.fm-loading .shape.shape1 {
			left: 0;
			background-color: #5C6BC0;
		}
		.fm-loading .shape.shape2 {
			right: 0;
			background-color: #8BC34A;
		}
		.fm-loading .shape.shape3 {
			bottom: 0;
			background-color: #FFB74D;
		}
		.fm-loading .shape.shape4 {
			bottom: 0;
			right: 0;
			background-color: #F44336;
		}

		@-webkit-keyframes rotation {
			from {transform: rotate(0deg);}
			to {transform: rotate(360deg);}
		}

		@keyframes rotation {
			from {transform: rotate(0deg);}
			to {transform: rotate(360deg);}
		}
		.fm-loading .shape1 {
			-webkit-animation: fmAnimationshape1 2s linear 0s infinite normal;
			animation: fmAnimationshape1 2s linear 0s infinite normal;
		}

		@-webkit-keyframes fmAnimationshape1 {
			0% {transform: translate(0, 0);}
			25% {transform: translate(0, 18px);}
			50% {transform: translate(18px, 18px);}
			75% {transform: translate(18px, 0);}
		}

		@keyframes fmAnimationshape1 {
			0% {transform: translate(0, 0);}
			25% {transform: translate(0, 18px);}
			50% {transform: translate(18px, 18px);}
			75% {transform: translate(18px, 0);}
		}
		.fm-loading .shape2 {
			-webkit-animation: fmAnimationshape2 2s linear 0s infinite normal;
			animation: fmAnimationshape2 2s linear 0s infinite normal;
		}

		@-webkit-keyframes fmAnimationshape2 {
			0% {transform: translate(0, 0);}
			25% {transform: translate(-18px, 0);}
			50% {transform: translate(-18px, 18px);}
			75% {transform: translate(0, 18px);}
		}
		@keyframes fmAnimationshape2 {
			0% {transform: translate(0, 0);}
			25% {transform: translate(-18px, 0);}
			50% {transform: translate(-18px, 18px);}
			75% {transform: translate(0, 18px);}
		}
		.fm-loading .shape3 {
			-webkit-animation: fmAnimationshape3 2s linear 0s infinite normal;
			animation: fmAnimationshape3 2s linear 0s infinite normal;
		}

		@-webkit-keyframes fmAnimationshape3 {
			0% {transform: translate(0, 0);}
			25% {transform: translate(18px, 0);}
			50% {transform: translate(18px, -18px);}
			75% {transform: translate(0, -18px);}
		}

		@keyframes fmAnimationshape3 {
			0% {transform: translate(0, 0);}
			25% {transform: translate(18px, 0);}
			50% {transform: translate(18px, -18px);}
			75% {transform: translate(0, -18px);}
		}
		.fm-loading .shape4 {
			-webkit-animation: fmAnimationshape4 2s linear 0s infinite normal;
			animation: fmAnimationshape4 2s linear 0s infinite normal;
		}

		@-webkit-keyframes fmAnimationshape4 {
			0% {transform: translate(0, 0);}
			25% {transform: translate(0, -18px);}
			50% {transform: translate(-18px, -18px);}
			75% {transform: translate(-18px, 0);}
		}

		@keyframes fmAnimationshape4 {
			0% {transform: translate(0, 0);}
			25% {transform: translate(0, -18px);}
			50% {transform: translate(-18px, -18px);}
			75% {transform: translate(-18px, 0);}
		}
		</style>
		<?php
	}

	/**
	 * Admin scripts.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function admin_scripts() {
		// Enqueue Open Sans font.
		wp_enqueue_style( 'open-sans-google-font', 'https://fonts.googleapis.com/css?display=swap&family=Open+Sans:100,200,300,400,600,bold', '', FLOWMATTIC_VERSION );

		wp_enqueue_style( 'flowmattic-bootstrap', FLOWMATTIC_PLUGIN_URL . 'assets/admin/css/min/bootstrap.min.css', '', FLOWMATTIC_VERSION );
		wp_enqueue_style( 'flowmattic-bootstrap-select', FLOWMATTIC_PLUGIN_URL . 'assets/admin/css/min/bootstrap-select.min.css', '', FLOWMATTIC_VERSION );
		wp_enqueue_style( 'flowmattic-workflows-admin', FLOWMATTIC_PLUGIN_URL . 'assets/admin/css/min/workflows-admin.min.css', '', FLOWMATTIC_VERSION );
		wp_enqueue_style( 'flowmattic-daterangepicker', FLOWMATTIC_PLUGIN_URL . 'assets/admin/css/min/daterangepicker.min.css', '', FLOWMATTIC_VERSION );
		wp_enqueue_style( 'flowmattic-admin-css', FLOWMATTIC_PLUGIN_URL . 'assets/admin/css/min/flowmattic-admin.min.css', '', FLOWMATTIC_VERSION );

		wp_enqueue_script( 'flowmattic-sweetalert', FLOWMATTIC_PLUGIN_URL . 'assets/admin/min/sweetalert.min.js', array( 'jquery' ), FLOWMATTIC_VERSION, true );
		wp_enqueue_script( 'flowmattic-bootstrap', FLOWMATTIC_PLUGIN_URL . 'assets/admin/min/bootstrap-bundle.min.js', array( 'jquery' ), '4.3.1', true );
		wp_enqueue_script( 'flowmattic-bootstrap-select', FLOWMATTIC_PLUGIN_URL . 'assets/admin/min/bootstrap-select.min.js', array( 'flowmattic-bootstrap' ), FLOWMATTIC_VERSION, true );
		wp_enqueue_script( 'flowmattic-moment', FLOWMATTIC_PLUGIN_URL . 'assets/admin/min/moment.min.js', array( 'jquery' ), FLOWMATTIC_VERSION, true );
		wp_enqueue_script( 'flowmattic-daterangepicker', FLOWMATTIC_PLUGIN_URL . 'assets/admin/min/daterangepicker.min.js', array( 'jquery', 'flowmattic-moment' ), FLOWMATTIC_VERSION, true );
		wp_enqueue_script( 'flowmattic-admin', FLOWMATTIC_PLUGIN_URL . 'assets/admin/min/flowmattic-admin.min.js', array( 'jquery' ), FLOWMATTIC_VERSION, true );

		wp_enqueue_media();

		// Localize script for the flowmattic admin pages.
		wp_localize_script(
			'flowmattic-admin',
			'FMConfig',
			array(
				'ajaxurl'        => admin_url( 'admin-ajax.php' ),
				'chatbotAjax'    => rest_url( 'flowmattic/v1/ajax/' ),
				'chatbot_nonce'  => wp_create_nonce( 'wp_rest' ),
				'workflow_nonce' => wp_create_nonce( 'flowmattic_workflow_nonce' ),
				'tasksAdmin'     => admin_url( 'admin.php?page=flowmattic-task-history' ),
				'oauth_webhook'  => FlowMattic_Webhook::get_url( 'fm_oauth' ),
				'variables'      => wp_flowmattic()->variables->get_vars(),
			)
		);

		?>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				jQuery( '.toplevel_page_flowmattic' ).addClass( 'current' );
			} );
		</script>
		<?php
	}

	/**
	 * Admin scripts.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function workflow_admin_scripts() {
		// Enqueue default admin scripts.
		$this->admin_scripts();

		if ( isset( $_GET['flowmattic-action'] ) ) {

			wp_enqueue_script( 'underscore' );
			wp_enqueue_script( 'fm-backbone', FLOWMATTIC_PLUGIN_URL . 'app/js/backbone.js', array( 'flowmattic-sweetalert' ), FLOWMATTIC_VERSION, true );
			wp_enqueue_script( 'flowmattic-workflow-utils', FLOWMATTIC_PLUGIN_URL . 'app/js/utils.js', array( 'jquery', 'fm-backbone' ), FLOWMATTIC_VERSION, true );
			wp_enqueue_script( 'flowmattic-workflow-collections', FLOWMATTIC_PLUGIN_URL . 'app/js/collections/collection-steps.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );
			wp_enqueue_script( 'flowmattic-workflow-model-view-schedule', FLOWMATTIC_PLUGIN_URL . 'app/js/views/view-schedule.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );
			wp_enqueue_script( 'flowmattic-workflow-model-view-trigger', FLOWMATTIC_PLUGIN_URL . 'app/js/views/view-trigger.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );
			wp_enqueue_script( 'flowmattic-workflow-model-view-action', FLOWMATTIC_PLUGIN_URL . 'app/js/views/view-actions.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );
			wp_enqueue_script( 'flowmattic-workflow-model-view-context-menu', FLOWMATTIC_PLUGIN_URL . 'app/js/views/view-context-menu.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );

			// Enqueue views for other apps.
			do_action( 'flowmattic_enqueue_views' );

			wp_enqueue_script( 'flowmattic-workflow-model-action', FLOWMATTIC_PLUGIN_URL . 'app/js/models/model-action.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );
			wp_enqueue_script( 'flowmattic-workflow-model-view-manager', FLOWMATTIC_PLUGIN_URL . 'app/js/models/model-view-manager.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );
			wp_enqueue_script( 'flowmattic-workflow-app', FLOWMATTIC_PLUGIN_URL . 'app/js/app.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );

			wp_enqueue_script( 'flowmattic-workflow', FLOWMATTIC_PLUGIN_URL . 'assets/admin/min/flowmattic-workflow.min.js', array( 'jquery' ), FLOWMATTIC_VERSION, true );

			wp_localize_script(
				'flowmattic-workflow-app',
				'flowMatticAppConfig',
				array(
					'ajaxurl'        => admin_url( 'admin-ajax.php' ),
					'workflow_nonce' => wp_create_nonce( 'flowmattic_workflow_nonce' ),
				)
			);
		}
	}

	/**
	 * Loads the JS templates for workflow.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function enqueue_templates() {
		if ( isset( $_GET['flowmattic-action'] ) ) {
			foreach ( glob( FLOWMATTIC_PLUGIN_DIR . '/app/templates/*.php', GLOB_NOSORT ) as $filename ) {
				include $filename;
			}

			foreach ( glob( FLOWMATTIC_PLUGIN_DIR . '/inc/apps/*/*.tpl', GLOB_NOSORT ) as $filename ) {
				include $filename;
			}

			foreach ( glob( WP_CONTENT_DIR . '/flowmattic-apps/*/*.tpl', GLOB_NOSORT ) as $filename ) {
				include $filename;
			}
		}
	}

	/**
	 * Loads the dashboard page template.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function dashboard() {
		require_once wp_normalize_path( __DIR__ . '/admin-screens/dashboard.php' );
	}

	/**
	 * Loads the integrations page template.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function integrations_tab() {
		require_once wp_normalize_path( __DIR__ . '/admin-screens/integrations.php' );
	}

	/**
	 * Loads the connects page template.
	 *
	 * @access public
	 * @since 3.0
	 * @return void
	 */
	public function connects_tab() {
		require_once wp_normalize_path( __DIR__ . '/admin-screens/connects.php' );
	}

	/**
	 * Loads the custom apps page template.
	 *
	 * @access public
	 * @since 3.0
	 * @return void
	 */
	public function custom_apps_tab() {
		if ( isset( $_GET['app'] ) && ( 'new' === $_GET['app'] || 'edit' === $_GET['app'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'customAppsNonce' ) ) {
				?>
				<div class="notice notice-error settings-error is-dismissible">
					<p>
						<?php esc_html_e( 'Link you accessed is expired or not valid.', 'flowmattic' ); ?> <a href="<?php echo esc_attr( wp_nonce_url( admin_url( 'admin.php?page=flowmattic-custom-apps' ), 'customAppsNonce' ) ); ?>" class="text-decoration-none"><?php esc_html_e( 'Get back to Custom Apps', 'flowmattic' ); ?></a>
					</p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
				</div>
				<?php
				wp_die();
			}

			$tab = ( isset( $_GET['tab'] ) && ( 'actions' === $_GET['tab'] || 'triggers' === $_GET['tab'] ) ) ? $_GET['tab'] : '';
			if ( '' !== $tab ) {
				require_once wp_normalize_path( __DIR__ . '/admin-screens/custom-app-edit-' . $tab . '.php' );
			} else {
				require_once wp_normalize_path( __DIR__ . '/admin-screens/custom-app-edit.php' );
			}
		} else {
			require_once wp_normalize_path( __DIR__ . '/admin-screens/custom-apps.php' );
		}
	}

	/**
	 * Loads the workflows page template.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function workflows_tab() {
		if ( isset( $_GET['flowmattic-action'] ) && 'new' === $_GET['flowmattic-action'] ) {
			require_once FLOWMATTIC_PLUGIN_DIR . '/app/workflow-app.php';
		} elseif ( isset( $_GET['workflow-id'] ) && isset( $_GET['flowmattic-action'] ) ) {
			require_once FLOWMATTIC_PLUGIN_DIR . '/app/workflow-app.php';
		} else {
			require_once wp_normalize_path( __DIR__ . '/admin-screens/workflows.php' );
		}
	}

	/**
	 * Loads the chatbot page template.
	 *
	 * @access public
	 * @since 4.0
	 * @return void
	 */
	public function chatbot_tab() {
		require_once wp_normalize_path( __DIR__ . '/admin-screens/chatbot.php' );
	}

	/**
	 * Loads the variables page template.
	 *
	 * @access public
	 * @since 4.0
	 * @return void
	 */
	public function variables_tab() {
		require_once wp_normalize_path( __DIR__ . '/admin-screens/variables.php' );
	}

	/**
	 * Loads the task history page template.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function task_history_tab() {
		require_once wp_normalize_path( __DIR__ . '/admin-screens/tasks-history.php' );
	}

	/**
	 * Loads the settings page template.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function settings_tab() {
		require_once wp_normalize_path( __DIR__ . '/admin-screens/settings.php' );
	}

	/**
	 * Loads the status page template.
	 *
	 * @access public
	 * @since 3.0
	 * @return void
	 */
	public function status_tab() {
		require_once wp_normalize_path( __DIR__ . '/admin-screens/status.php' );
	}

	/**
	 * Loads the license page template.
	 *
	 * @access public
	 * @since 3.1.0
	 * @return void
	 */
	public function license_tab() {
		require_once wp_normalize_path( __DIR__ . '/admin-screens/license.php' );
	}

	/**
	 * Set the admin page tabs.
	 *
	 * @static
	 * @access protected
	 * @since 1.0
	 * @param string $title The title.
	 * @param string $page  The page slug.
	 * @param string $icon  The admin menu icon.
	 * @param string $svg   The SVG icon.
	 */
	public static function admin_tab( $title, $page, $icon, $svg = '' ) {

		if ( isset( $_GET['page'] ) ) {
			$active_page = $_GET['page'];
		}

		$link = 'admin.php?page=' . $page;

		if ( $active_page == $page ) {
			$active_tab = ' active';
		} else {
			$active_tab = '';
		}

		if ( 'svg' === $icon ) {
			$icon_html = '<span class="dashicons d-inline-flex align-items-center">' . $svg . '</span>';
		} else {
			$icon_html = '<span class="dashicons dashicons-' . $icon . '"></span>';
		}

		echo '<li class="nav-item"><a href="' . $link . '" class="nav-link position-relative' . $active_tab . '">' . $icon_html . $title . '</a></li>'; // phpcs:ignore.
	}

	/**
	 * Adds the footer.
	 *
	 * @static
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public static function loader() {
		?>
		<div class="fm-loading-artboard">
			<div class="fm-loading">
				<div class="shape shape1"></div>
				<div class="shape shape2"></div>
				<div class="shape shape3"></div>
				<div class="shape shape4"></div>
			</div>
		</div>
		<style type="text/css">
		.update-nag, .updated, .error, .notice, .wp-admin .bwf-notice.notice, .is-dismissible, div#setting-error-tgmpa.notice.settings-error, .fs-notice, div.fs-notice.updated, div.fs-notice.success, div.fs-notice.promotion{ display: none !important; }
		wp-menu-image img {max-width: 16px !important;}
		.wp-core-ui.wp-ui-notification {display: inline-block !important;min-width: 18px !important;height: 18px !important;border-radius: 9px !important;margin: 7px 0 0 2px !important;vertical-align: top !important;font-size: 11px !important;line-height: 1.6 !important;text-align: center !important;}
		</style>
		<?php
	}

	/**
	 * Adds the footer.
	 *
	 * @static
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public static function footer() {
		add_filter(
			'admin_footer_text',
			function( $text ) {
				$text = '<span id="footer-thankyou">
					<span class="flowmattic-thanks">
						' . esc_html_e( 'Thank you for choosing FlowMattic. We are honored and are fully dedicated to making your experience perfect.', 'flowmattic' ) . '
					</span>
				</span>';
				return $text;
			}
		);
	}

	/**
	 * Adds the header.
	 *
	 * @static
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public static function header() {
		?>
		<div class="flowmattic-dashboard-nav d-flex flex-column flex-shrink-0 p-3 bg-light" style="min-height: 100vh;">
			<div class="flowmattic-dashboard-logo">
				<img src="<?php echo FLOWMATTIC_PLUGIN_URL . 'assets/admin/img/icon.svg'; ?>" style="width: 48px;height: 48px;"/>
				<div class="flowmattic-logo-title">
					FlowMattic
					<span class="flowmattic-version">V<?php echo FLOWMATTIC_VERSION; ?></span>
				</div>
			</div>
			<ul class="nav nav-pills flex-column mt-4">
				<?php
				$settings            = get_option( 'flowmattic_settings', array() );
				$integrations_access = isset( $settings['integration_page_access'] ) ? $settings['integration_page_access'] : 'yes';

				self::admin_tab( esc_attr__( 'Dashboard', 'flowmattic' ), 'flowmattic', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="24px" viewBox="0 0 24 24" width="24px" fill="currentColor"><path d="M19,3H5C3.9,3,3,3.9,3,5v14c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2V5C21,3.9,20.1,3,19,3z M5,19V5h6v14H5z M19,19h-6v-7h6V19z M19,10h-6V5h6V10z"/></svg>' );

				if ( 'yes' === $integrations_access || current_user_can( 'manage_options' ) ) {
					// Check if integrations have an update available.
					$is_integration_update = flowmattic_is_integration_update_available();
					$update_bubble         = $is_integration_update ? '  <span class="ms-2 p-2 bg-danger border border-light rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 20px;height: 20px;font-size: 12px;">' . $is_integration_update . '<span class="visually-hidden">' . $is_integration_update . '</span></span>' : '';
					self::admin_tab( esc_attr__( 'Integrations', 'flowmattic' ) . $update_bubble, 'flowmattic-integrations', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="currentColor"><g fill-rule="evenodd"><path d="M0 0h24v24H0z" fill="none"/><path d="M3 3v8h8V3H3zm6 6H5V5h4v4zm-6 4v8h8v-8H3zm6 6H5v-4h4v4zm4-16v8h8V3h-8zm6 6h-4V5h4v4zm-6 4v8h8v-8h-8zm6 6h-4v-4h4v4z"/></g></svg>' );
				}

				self::admin_tab( esc_attr__( 'Connects', 'flowmattic' ), 'flowmattic-connects', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="18px" viewBox="0 0 24 24" width="18px" fill="currentColor"><g><path d="M14,12l-2,2l-2-2l2-2L14,12z M12,6l2.12,2.12l2.5-2.5L12,1L7.38,5.62l2.5,2.5L12,6z M6,12l2.12-2.12l-2.5-2.5L1,12 l4.62,4.62l2.5-2.5L6,12z M18,12l-2.12,2.12l2.5,2.5L23,12l-4.62-4.62l-2.5,2.5L18,12z M12,18l-2.12-2.12l-2.5,2.5L12,23l4.62-4.62 l-2.5-2.5L12,18z"></path></g></svg>' );
				self::admin_tab( esc_attr__( 'Custom Apps', 'flowmattic' ), 'flowmattic-custom-apps', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="24px" viewBox="0 0 24 24" width="24px" fill="currentColor"><g><g><path d="M3,11h8V3H3V11z M5,5h4v4H5V5z"/><path d="M13,3v8h8V3H13z M19,9h-4V5h4V9z"/><path d="M3,21h8v-8H3V21z M5,15h4v4H5V15z"/><polygon points="18,13 16,13 16,16 13,16 13,18 16,18 16,21 18,21 18,18 21,18 21,16 18,16"/></g></g></svg>' );
				self::admin_tab( esc_attr__( 'Workflows', 'flowmattic' ), 'flowmattic-workflows', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 512 512" style="transform: rotate(180deg);"><path d="M384 32a64 64 0 00-57.67 91.73l-70.83 80.82-70.19-80.1A64 64 0 10128 160c1.1 0 2.2 0 3.29-.08L224 265.7v94.91a64 64 0 1064 0v-96.05l91.78-104.71c1.39.09 2.8.15 4.22.15a64 64 0 000-128zM96 96a32 32 0 1132 32 32 32 0 01-32-32zm160 352a32 32 0 1132-32 32 32 0 01-32 32zm128-320a32 32 0 1132-32 32 32 0 01-32 32z"/></svg>' );

				// Add Chatbot tab.
				self::admin_tab( esc_attr__( 'AI Chatbot', 'flowmattic' ), 'flowmattic-chatbot', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="M240-400h320v-80H240v80Zm0-120h480v-80H240v80Zm0-120h480v-80H240v80ZM80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Zm126-240h594v-480H160v525l46-45Zm-46 0v-480 480Z"/></svg>' );

				// Add variables tab.
				self::admin_tab( esc_attr__( 'Variables', 'flowmattic' ), 'flowmattic-variables', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="M120-280v-400h720v400H120Zm80-80h560v-240H200v240Zm0 0v-240 240Z"/></svg>' );

				// Add task history tab.
				self::admin_tab( esc_attr__( 'History', 'flowmattic' ), 'flowmattic-task-history', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="24px" viewBox="0 0 24 24" width="24px" fill="currentColor"><g><path d="M22.69,18.37l1.14-1l-1-1.73l-1.45,0.49c-0.32-0.27-0.68-0.48-1.08-0.63L20,14h-2l-0.3,1.49c-0.4,0.15-0.76,0.36-1.08,0.63 l-1.45-0.49l-1,1.73l1.14,1c-0.08,0.5-0.08,0.76,0,1.26l-1.14,1l1,1.73l1.45-0.49c0.32,0.27,0.68,0.48,1.08,0.63L18,24h2l0.3-1.49 c0.4-0.15,0.76-0.36,1.08-0.63l1.45,0.49l1-1.73l-1.14-1C22.77,19.13,22.77,18.87,22.69,18.37z M19,21c-1.1,0-2-0.9-2-2s0.9-2,2-2 s2,0.9,2,2S20.1,21,19,21z M11,7v5.41l2.36,2.36l1.04-1.79L13,11.59V7H11z M21,12c0-4.97-4.03-9-9-9C9.17,3,6.65,4.32,5,6.36V4H3v6 h6V8H6.26C7.53,6.19,9.63,5,12,5c3.86,0,7,3.14,7,7H21z M10.86,18.91C7.87,18.42,5.51,16.01,5.08,13H3.06c0.5,4.5,4.31,8,8.94,8 c0.02,0,0.05,0,0.07,0L10.86,18.91z"/></g></svg>' );

				if ( current_user_can( 'manage_options' ) ) {
					self::admin_tab( esc_attr__( 'Settings', 'flowmattic' ), 'flowmattic-settings', 'svg', '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" data-reactroot=""><path fill="none" d="M13.87 4.89L13.56 2.44C13.53 2.19 13.32 2 13.06 2H9.95C9.7 2 9.48 2.19 9.45 2.44L9.18 4.59C9.16 4.77 9.03 4.93 8.86 4.99C8.02 5.3 7.22 5.78 6.53 6.4L4.25 5.44C4.02 5.34 3.75 5.43 3.62 5.65L2.06 8.35C1.94 8.57 2 8.85 2.2 9L4.17 10.49C3.96 11.5 3.96 12.53 4.16 13.51H4.17L2.2 15C2 15.15 1.94 15.43 2.07 15.65L3.63 18.35C3.76 18.57 4.03 18.66 4.26 18.56L6.54 17.6L6.53 17.61C6.9 17.94 7.32 18.24 7.77 18.5C8.22 18.76 8.68 18.97 9.16 19.13V19.11L9.47 21.56C9.48 21.81 9.7 22 9.95 22H13.07C13.32 22 13.53 21.81 13.57 21.56L13.84 19.41C13.86 19.23 13.99 19.07 14.16 19.01C15 18.7 15.8 18.22 16.49 17.6L18.77 18.56C19 18.66 19.27 18.57 19.4 18.35L20.96 15.65C21.09 15.43 21.03 15.15 20.83 15L18.86 13.51C19.07 12.5 19.07 11.47 18.87 10.49H18.86L20.81 9C21.01 8.85 21.07 8.57 20.94 8.35L19.38 5.65C19.25 5.43 18.98 5.34 18.75 5.44L16.48 6.4L16.49 6.39C16.12 6.06 15.7 5.76 15.25 5.5C14.8 5.24 14.34 5.03 13.86 4.87" clip-rule="evenodd" fill-rule="evenodd"></path><path stroke-width="1.3" stroke="currentColor" d="M13.87 4.89L13.56 2.44C13.53 2.19 13.32 2 13.06 2H9.95C9.7 2 9.48 2.19 9.45 2.44L9.18 4.59C9.16 4.77 9.03 4.93 8.86 4.99C8.02 5.3 7.22 5.78 6.53 6.4L4.25 5.44C4.02 5.34 3.75 5.43 3.62 5.65L2.06 8.35C1.94 8.57 2 8.85 2.2 9L4.17 10.49C3.96 11.5 3.96 12.53 4.16 13.51H4.17L2.2 15C2 15.15 1.94 15.43 2.07 15.65L3.63 18.35C3.76 18.57 4.03 18.66 4.26 18.56L6.54 17.6L6.53 17.61C6.9 17.94 7.32 18.24 7.77 18.5C8.22 18.76 8.68 18.97 9.16 19.13V19.11L9.47 21.56C9.48 21.81 9.7 22 9.95 22H13.07C13.32 22 13.53 21.81 13.57 21.56L13.84 19.41C13.86 19.23 13.99 19.07 14.16 19.01C15 18.7 15.8 18.22 16.49 17.6L18.77 18.56C19 18.66 19.27 18.57 19.4 18.35L20.96 15.65C21.09 15.43 21.03 15.15 20.83 15L18.86 13.51C19.07 12.5 19.07 11.47 18.87 10.49H18.86L20.81 9C21.01 8.85 21.07 8.57 20.94 8.35L19.38 5.65C19.25 5.43 18.98 5.34 18.75 5.44L16.48 6.4L16.49 6.39C16.12 6.06 15.7 5.76 15.25 5.5C14.8 5.24 14.34 5.03 13.86 4.87"></path><path stroke-width="1.3" stroke="currentColor" fill="none" d="M11.51 16C13.7191 16 15.51 14.2091 15.51 12C15.51 9.79086 13.7191 8 11.51 8C9.30086 8 7.51 9.79086 7.51 12C7.51 14.2091 9.30086 16 11.51 16Z"></path></svg>' );
					self::admin_tab( esc_attr__( 'Status', 'flowmattic' ), 'flowmattic-status', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" height="48" viewBox="0 96 960 960" width="48" fill="currentColor"><path d="M180 936q-24.75 0-42.375-17.625T120 876V276q0-24.75 17.625-42.375T180 216h600q14 0 25.5 6t18.5 14l-44 44v-4H180v600h600V533l60-60v403q0 24.75-17.625 42.375T780 936H180Zm281-168L239 546l42-42 180 180 382-382 42 42-424 424Z"/></svg>' );
					self::admin_tab( esc_attr__( 'License', 'flowmattic' ), 'flowmattic-license', 'svg', '<svg xmlns="http://www.w3.org/2000/svg" height="48" viewBox="0 -960 960 960" width="48" fill="currentColor"><path d="M220-80q-24.75 0-42.375-17.625T160-140v-434q0-24.75 17.625-42.375T220-634h70v-96q0-78.85 55.606-134.425Q401.212-920 480.106-920T614.5-864.425Q670-808.85 670-730v96h70q24.75 0 42.375 17.625T800-574v434q0 24.75-17.625 42.375T740-80H220Zm0-60h520v-434H220v434Zm260.168-140Q512-280 534.5-302.031T557-355q0-30-22.668-54.5t-54.5-24.5Q448-434 425.5-409.5t-22.5 55q0 30.5 22.668 52.5t54.5 22ZM350-634h260v-96q0-54.167-37.882-92.083-37.883-37.917-92-37.917Q426-860 388-822.083 350-784.167 350-730v96ZM220-140v-434 434Z"/></svg>' );
				}
				?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Customize admin for Chatbot.
	 *
	 * @access public
	 * @since 4.0
	 * @return void
	 */
	public function customize_admin_for_chatbot() {
		if ( isset( $_GET['page'] ) && 'flowmattic-chatbot' === $_GET['page'] ) {
			// Hide Admin Bar.
			add_filter( 'show_admin_bar', '__return_false' );

			// Hide Admin Menu via CSS.
			add_action(
				'admin_head',
				function() {
					echo '<style>
					#adminmenumain, #wpadminbar { display: none; }
					#wpcontent, #wpfooter { margin-left: 0; }
					.notice, .error, .warning, .fs-notice,
					.stm-plugin-admin-notice,
					.update-nag, .updated, .is-dismissible, div#setting-error-tgmpa.notice.settings-error, .fs-notice, div.fs-notice.updated, div.fs-notice.success, div.fs-notice.promotion,
					.flowmattic-loaded .fm-loading-artboard {
						display: none;
					}
				</style>';
				}
			);

			// Enqueue the color picker CSS and JavaScript.
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

			// Enqueue WP Media.
			wp_enqueue_media();

			// Enqueue the chatbot js.
			wp_enqueue_script( 'flowmattic-chatbot', FLOWMATTIC_PLUGIN_URL . 'assets/admin/min/flowmattic-chatbot.min.js', array( 'jquery' ), wp_rand(), true );

			// Enqueue the chatbot admin js.
			wp_enqueue_script( 'flowmattic-chatbot-admin', FLOWMATTIC_PLUGIN_URL . 'assets/admin/min/flowmattic-chatbot-admin.min.js', array( 'jquery' ), wp_rand(), true );

			// Enqueue the chatbot css.
			wp_enqueue_style( 'flowmattic-chatbot', FLOWMATTIC_PLUGIN_URL . 'assets/admin/css/min/chatbot-admin.min.css', array(), wp_rand() );
		}
	}
}
