<?php
/**
 * Add application including triggers and actions to the applications.
 *
 * @access public
 * @since 1.0
 * @param string $slug        Application slug.
 * @param array  $application Application settings.
 * @return void
 */
function flowmattic_add_application( $slug, $application ) {
	if ( '' !== $slug && ! empty( $application ) ) {
		FlowMattic_Applications::add_application( $slug, $application );
	}
}

/**
 * Add custom app including triggers and actions to the applications.
 *
 * @access public
 * @since 3.0
 * @param array $custom_app Custom application settings.
 * @return void
 */
function flowmattic_add_custom_app( $custom_app ) {
	if ( ! empty( $custom_app ) ) {
		$custom_app->needs_connect = true;
		FlowMattic_Custom_Apps::add_custom_app( $custom_app );
	}
}

/**
 * Add connect for external app.
 *
 * @access public
 * @since 3.0
 * @param string $slug     Application slug.
 * @param array  $settings Connect settings.
 * @return void
 */
function flowmattic_add_connect( $slug, $settings ) {
	if ( '' !== $slug && ! empty( $settings ) ) {
		FlowMattic_Connects::add_connect( $slug, $settings );
	}
}

/**
 * Get stored connects for external app.
 *
 * @access public
 * @since 3.0
 * @param string $slug Connect slug.
 * @return array
 */
function flowmattic_get_connects( $slug = 'all' ) {
	return FlowMattic_Connects::get_connect( $slug );
}

/**
 * Generate random string.
 *
 * @access public
 * @since 1.0
 * @param int $length Application settings.
 * @return string
 */
function flowmattic_random_string( $length = 10 ) {
	$characters        = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$characters_length = strlen( $characters );
	$random_string     = '';

	for ( $i = 0; $i < $length; $i++ ) {
		$random_string .= $characters[ wp_rand( 0, $characters_length - 1 ) ];
	}

	return $random_string;
}

/**
 * Convert array to associative array based on the key and value pairs.
 *
 * @access public
 * @since 1.0
 * @param array  $ref_array        Reference array.
 * @param string $ref_key          Reference array key.
 * @param array  $processing_array Array to be processed.
 * @return array
 */
function flowmattic_recursive_array( $ref_array, $ref_key, $processing_array ) {
	if ( is_array( $processing_array ) || is_object( $processing_array ) ) {
		$processing_array = (array) $processing_array;

		foreach ( $processing_array as $key => $value ) {
			$key         = str_replace( array( '*', chr( 0 ) ), '', $key );
			$new_ref_key = $ref_key . '_' . $key;

			if ( is_array( $value ) || is_object( $value ) ) {
				$value = (array) $value;
				if ( isset( $value[0] ) && ( is_array( $value[0] ) || is_object( $value[0] ) ) ) {
					$ref_array[ $new_ref_key ] = wp_json_encode( $value );
				} else {
					$ref_array = flowmattic_recursive_array( $ref_array, $new_ref_key, $value );
				}
			} else {
				$ref_array[ $new_ref_key ] = ( $value ) ? trim( $value ) : $value;
			}
		}
	}

	return $ref_array;
}

/**
 * Get contact form 7 forms list.
 *
 * @since 1.0
 * @access public
 * @return $forms Contact form 7 forms list.
 */
function flowmattic_get_contact_form_list() {
	$args = array(
		'post_type'      => 'wpcf7_contact_form',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	);

	$cf7_forms = array(
		'any' => esc_attr__( 'Any Form', 'flowmattic' ),
	);

	// @codingStandardsIgnoreLine
	if ( $data = get_posts( $args ) ) {
		foreach ( $data as $key ) {
			$cf7_forms[ $key->ID ] = $key->post_title;
		}
	}

	return $cf7_forms;
}

/**
 * Get WPForms forms list.
 *
 * @since 1.0
 * @access public
 * @return $forms WPForms forms list.
 */
function eewpb_get_wpform_list() {
	$args = array(
		'post_type'      => 'wpforms',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	);

	$wpforms = array(
		'any' => esc_attr__( 'Any Form', 'flowmattic' ),
	);

	// @codingStandardsIgnoreLine
	if ( $data = get_posts( $args ) ) {
		foreach ( $data as $key ) {
			$wpforms[ $key->ID ] = $key->post_title;
		}
	}

	return $wpforms;
}

/**
 * Get attachment values.
 *
 * @since 1.0
 * @access public
 * @param int $attachment_id Attachment ID.
 * @return array Attachment details.
 */
function flowmattic_get_attachment( $attachment_id ) {

	$attachment = get_post( $attachment_id );

	return array(
		'title'       => $attachment->post_title,
		'src'         => $attachment->guid,
		'caption'     => $attachment->post_excerpt,
		'description' => $attachment->post_content,
		'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
		'href'        => get_permalink( $attachment->ID ),
	);
}

/**
 * Decode the HTML entities for the given string.
 *
 * @since 1.0
 * @access public
 * @param string $to_decode String to decode.
 * @return string HTML entities decoded string.
 */
function flowmattic_decode_html_entities( $to_decode ) {
	if ( is_array( $to_decode ) ) {
		// If this is array, look through it and decode html entities if any.
		foreach ( $to_decode as $key => $value ) {
			$to_decode[ $key ] = html_entity_decode( $value );
		}
	} else {
		$to_decode = html_entity_decode( $to_decode );
	}

	return $to_decode;
}

/**
 * Get the workflow from database and extract the capture data from all steps.
 *
 * @since 1.0
 * @access public
 * @param string $workflow_id Workflow ID.
 * @return array All steps captured data.
 */
function flowmattic_get_workflow_captures( $workflow_id ) {
	$workflow_captures = array();

	$args = array(
		'workflow_id' => $workflow_id,
	);

	$workflow = wp_flowmattic()->workflows_db->get( $args );

	if ( ! empty( $workflow ) ) {
		$steps = ( ! is_array( $workflow ) ) ? json_decode( $workflow->workflow_steps, true ) : $workflow['workflow_steps'];

		foreach ( $steps as $key => $step ) {
			if ( isset( $step['capturedData'] ) ) {
				$workflow_captures[ $step['stepID'] ] = $step['capturedData'];
			}

			// In case the application is not set, skip the loop.
			if ( ! isset( $step['application'] ) ) {
				continue;
			}

			if ( 'router' === $step['application'] ) {
				$router_steps = $step['routerSteps'];
				$step_ids     = array();

				foreach ( $router_steps as $route_letter => $route_actions ) {
					$route_key = $key;
					$tag       = 'route' . $route_letter;

					foreach ( $route_actions as $rkey => $route_action ) {
						++$route_key;

						if ( ! isset( $route_action['stepID'] ) ) {
							continue;
						}

						$capture   = isset( $route_action['capturedData'] ) ? $route_action['capturedData'] : array();
						$route_tag = $tag . '.' . $route_action['application'] . $route_key;

						// Set the workflow capture.
						$workflow_captures[ $route_action['stepID'] ] = $capture;

						// Set the step id for internal task.
						$step_ids[] = array(
							'tag'    => $route_tag,
							'stepID' => $route_action['stepID'],
						);
					}
				}

				// Set the router as workflow capture.
				$workflow_captures['routers'][ $step['stepID'] ] = $step_ids;
			}
		}

		return $workflow_captures;
	} else {
		return array();
	}
}

/**
 * Get an array of all user data
 *
 * @since 1.0
 * @access public
 * @param  string|int $user_id User ID.
 * @return array merged array of user meta and data
 */
function flowmattic_get_user_data( $user_id ) {
	$user_data = (array) get_userdata( $user_id )->data;
	$user_meta = array_map(
		function( $item ) {
			return $item[0];
		},
		(array) get_user_meta( $user_id )
	);

	return array_merge( $user_data, $user_meta );
}

/**
 * Get the authentication data for the specified application and workflow.
 *
 * @since 1.0
 * @access public
 * @param  string $application Application slug.
 * @param  string $workflow_id Workflow ID.
 * @return array Authentication data for the application.
 */
function flowmattic_get_auth_data( $application, $workflow_id ) {
	$authentication_data = get_option( 'flowmattic_auth_data', array() );

	if ( isset( $authentication_data[ $workflow_id ] ) && ! empty( $authentication_data[ $workflow_id ] ) ) {
		if ( isset( $authentication_data[ $workflow_id ][ $application ] ) ) {
			return $authentication_data[ $workflow_id ][ $application ];
		}
	}

	return array();
}

/**
 * Update the authentication data for the specified application and workflow.
 *
 * @since 1.0
 * @access public
 * @param  string $application Application slug.
 * @param  string $workflow_id Workflow ID.
 * @param  array  $auth_data   Authentication data.
 * @return void.
 */
function flowmattic_update_auth_data( $application, $workflow_id, $auth_data ) {
	$authentication_data = get_option( 'flowmattic_auth_data', array() );

	if ( isset( $authentication_data[ $workflow_id ] ) && ! empty( $authentication_data[ $workflow_id ] ) ) {
		if ( isset( $authentication_data[ $workflow_id ][ $application ] ) ) {
			$authentication_data[ $workflow_id ][ $application ] = $auth_data;
			update_option( 'flowmattic_auth_data', $authentication_data, false );
		}
	}
}

/**
 * Output the license activated form.
 *
 * @since 1.0
 * @access public
 * @param Object $license License object.
 * @return void
 */
function flowmattic_license_activated_form( $license ) {
	$license_status = isset( $license->is_valid ) && $license->is_valid ? 'Valid' : 'Invalid';

	// If license is expired, update the status.
	if ( is_string( $license ) && false !== strpos( $license, 'expired' ) ) {
		$license_status = 'Expired';
	}
	?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="FlowMattic_el_deactivate_license"/>
	<div class="el-license-container">
		<p><h4 class="el-license-title fw-bold"><?php esc_html_e( 'License Info', 'flowmattic' ); ?></h4></p>
		<ul class="el-license-info list-group">
			<li class="list-group-item m-0">
				<div class="d-flex">
					<span class="el-license-info-title w-50"><?php esc_html_e( 'Status', 'flowmattic' ); ?></span>
					<?php if ( isset( $license->is_valid ) && $license->is_valid ) : ?>
						<span class="el-license-valid badge bg-success d-inline-flex align-items-center"><?php esc_html_e( 'Valid', 'flowmattic' ); ?></span>
					<?php else : ?>
						<span class="el-license-valid badge bg-danger d-inline-flex align-items-center"><?php echo esc_attr( $license_status ); ?></span>
					<?php endif; ?>
				</div>
			</li>
			<li class="list-group-item m-0">
				<div class="d-flex">
					<span class="el-license-info-title w-50"><?php esc_html_e( 'License Type', 'flowmattic' ); ?></span>
					<?php echo isset( $license->license_title ) ? $license->license_title : 'Invalid'; ?>
				</div>
			</li>
			<li class="list-group-item m-0">
				<div class="d-flex">
					<span class="el-license-info-title w-50"><?php esc_html_e( 'License Valid Till', 'flowmattic' ); ?></span>
					<?php
					if ( isset( $license->license_title ) && false !== strpos( $license->license_title, 'Lifetime' ) ) {
						echo 'LIFETIME';
					} else {
						echo isset( $license->expire_date ) ? date_i18n( 'd F, Y', strtotime( $license->expire_date ) ) : 'Invalid';
					}

					if ( ! empty( $license->expire_renew_link ) ) {
						?>
						<a target="_blank" class="el-blue-btn" href="<?php echo $license->expire_renew_link; ?>">Renew</a>
						<?php
					}
					?>
				</div>
			</li>
			<li class="list-group-item m-0">
				<div class="d-flex">
					<span class="el-license-info-title w-50"><?php esc_html_e( 'Support Valid Till', 'flowmattic' ); ?></span>
					<?php
					if ( isset( $license->license_title ) && false !== strpos( $license->license_title, 'Lifetime' ) ) {
						echo 'LIFETIME';
					} else {
						echo isset( $license->support_end ) ? date_i18n( 'd F, Y', strtotime( $license->support_end ) ) : 'Invalid';
					}

					if ( ! empty( $license->support_renew_link ) ) {
						?>
						<a target="_blank" class="el-blue-btn" href="<?php echo $license->support_renew_link; ?>">Renew</a>
						<?php
					}
					?>
				</div>
			</li>
			<li class="list-group-item m-0">
				<?php
				$license_key = get_option( 'flowmattic_license_Key', '' );
				$license_key = ( isset( $license->license_key ) && '' !== $license->license_key ) ? $license->license_key : ( '' !== $license_key ? $license_key : '' );
				?>
				<div class="d-flex">
					<span class="el-license-info-title w-50"><?php esc_html_e( 'Your License Key', 'flowmattic' ); ?></span>
					<span class="el-license-key fw-bold"><?php echo esc_attr( substr( $license_key, 0, 9 ) . 'XXXXXXXX-XXXXXXXX' . substr( $license_key, -9 ) ); ?></span>
				</div>
			</li>
		</ul>
		<div class="el-license-active-btn">
			<?php wp_nonce_field( 'el-license' ); ?>
			<p class="submit">
				<button type="submit" name="submit" id="submit" class="btn btn-danger d-inline-flex align-items-center">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" data-reactroot="">
						<path stroke-linejoin="round" stroke-linecap="round" stroke-width="1" stroke="#fff" d="M8 10V5C8 3.34315 9.34315 2 11 2H13C14.6569 2 16 3.34315 16 5V6"></path>
						<rect stroke-linejoin="round" stroke-linecap="round" stroke-width="1" stroke="#fff" fill="none" rx="2" height="12" width="14" y="10" x="5"></rect>
						<path stroke-linejoin="round" stroke-linecap="round" stroke-width="1" stroke="#fff" d="M12 17L12 15"></path>
						<circle stroke-width="1" stroke="#fff" fill="none" r="1" cy="18" cx="12"></circle>
					</svg>
					<span class="ps-2"><?php echo __( 'Deactivate License', 'flowmattic' ); ?></span>
				</button>
			</p>
		</div>
	</div>
</form>
	<?php
}

/**
 * Output the license form to enter license key and activate.
 *
 * @since 1.0
 * @access public
 * @return void
 */
function flowmattic_license_form() {
	$license_key = get_option( 'flowmattic_license_Key', '' );
	// $license_key = ( '' !== $license_key ) ? esc_attr( substr( $license_key, 0, 9 ) . 'XXXXXXXX-XXXXXXXX' . substr( $license_key, -9 ) ) : 'xxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx';
	?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="FlowMattic_el_activate_license"/>
	<div class="el-license-container">
		<p><h4 class="el-license-title fw-bold"><?php esc_html_e( 'Activate License', 'flowmattic' ); ?></h4></p>
		<?php
		$license_message = get_option( 'flowmattic_license_message', '' );
		if ( $license_message ) {
			?>
			<div class="alert alert-danger d-flex align-items-center" role="alert">
				<svg class="me-2" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" data-reactroot="">
					<path fill="#842029" d="M12 2L2 22H22L12 2Z" undefined="1"></path><path fill="#842029" d="M12 19C12.2761 19 12.5 18.7761 12.5 18.5C12.5 18.2239 12.2761 18 12 18C11.7239 18 11.5 18.2239 11.5 18.5C11.5 18.7761 11.7239 19 12 19Z" undefined="1"></path>
					<path stroke-linejoin="round" stroke-linecap="round" stroke-miterlimit="10" stroke-width="1" stroke="#fff" d="M12 19C12.2761 19 12.5 18.7761 12.5 18.5C12.5 18.2239 12.2761 18 12 18C11.7239 18 11.5 18.2239 11.5 18.5C11.5 18.7761 11.7239 19 12 19Z"></path>
					<path stroke-linejoin="round" stroke-linecap="round" stroke-miterlimit="10" stroke-width="1" stroke="#fff" d="M12 10V15"></path>
				</svg>
				<div><?php echo esc_html( $license_message ); ?></div>
			</div>
			<?php
			// delete_option( 'flowmattic_license_message' );
		}
		?>
		<div class="p-3 border rounded mb-3 bg-light">
			<div class="el-license-field mb-3">
				<label class="form-label" for="flowmattic_license_key"><?php esc_html_e( 'License code', 'flowmattic' ); ?></label>
				<input type="text" class="regular-text code form-control" name="flowmattic_license_key" size="50" value="<?php echo $license_key; ?>" required="required">
			</div>
			<div class="el-license-field mb-3">
				<label class="form-label" for="flowmattic_license_key"><?php esc_html_e( 'Email Address', 'flowmattic' ); ?></label>
				<?php
				$purchase_email = get_option( 'flowmattic_license_email', get_bloginfo( 'admin_email' ) );
				?>
				<input type="text" class="regular-text code form-control" name="el_license_email" size="50" value="<?php echo $purchase_email; ?>" placeholder="" required="required" aria-describedby="emailHelp">
				<div id="emailHelp" class="form-text"><small><?php esc_html_e( 'Email used to purchase the plugin', 'flowmattic' ); ?></small></div>
			</div>
			<div class="el-license-active-btn">
				<?php wp_nonce_field( 'el-license' ); ?>
				<button type="submit" name="submit" id="submit" class="btn btn-primary d-inline-flex align-items-center">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" data-reactroot="">
						<path stroke-linejoin="round" stroke-linecap="round" stroke-width="1" stroke="#fff" d="M8 5C8 3.34315 9.34315 2 11 2H13C14.6569 2 16 3.34315 16 5V10H8V5Z"></path>
						<rect stroke-linejoin="round" stroke-linecap="round" stroke-width="1" stroke="#fff" fill="none" rx="2" height="12" width="14" y="10" x="5"></rect>
						<path stroke-linejoin="round" stroke-linecap="round" stroke-width="1" stroke="#fff" d="M12 17L12 15"></path>
						<circle stroke-width="1" stroke="#fff" fill="none" r="1" cy="18" cx="12"></circle>
					</svg>
					<span class="ps-2"><?php echo __( 'Activate License', 'flowmattic' ); ?></span>
				</button>
			</div>
		</div>
		<p class="fs-6 fw-bold mb-3"><?php esc_html_e( 'Enter your license key here, to activate the product, and get full feature updates and premium support.', 'flowmattic' ); ?></p>
		<ul class="list-group list-group-flush m-0">
			<li class="list-group-item ps-1"><svg class="me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" data-reactroot="">
<path fill="#333" d="M3.29289 3.29289C3.68342 2.90237 4.31658 2.90237 4.70711 3.29289L12.7071 11.2929C13.0976 11.6834 13.0976 12.3166 12.7071 12.7071L4.70711 20.7071C4.31658 21.0976 3.68342 21.0976 3.29289 20.7071C2.90237 20.3166 2.90237 19.6834 3.29289 19.2929L10.5858 12L3.29289 4.70711C2.90237 4.31658 2.90237 3.68342 3.29289 3.29289Z" clip-rule="evenodd" fill-rule="evenodd" undefined="1"></path>
<path fill="#333" d="M11.2929 3.29289C11.6834 2.90237 12.3166 2.90237 12.7071 3.29289L20.7071 11.2929C21.0976 11.6834 21.0976 12.3166 20.7071 12.7071L12.7071 20.7071C12.3166 21.0976 11.6834 21.0976 11.2929 20.7071C10.9024 20.3166 10.9024 19.6834 11.2929 19.2929L18.5858 12L11.2929 4.70711C10.9024 4.31658 10.9024 3.68342 11.2929 3.29289Z" clip-rule="evenodd" fill-rule="evenodd" undefined="1"></path>
</svg><?php echo sprintf( __( 'You will find your license key at the license tab in <a href="%s" target="_blank">account page</a> on our website.', 'flowmattic' ), 'https://flowmattic.com/account/#licenses' ); ?></li>
			<li class="list-group-item ps-1"><svg class="me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" data-reactroot="">
<path fill="#333" d="M3.29289 3.29289C3.68342 2.90237 4.31658 2.90237 4.70711 3.29289L12.7071 11.2929C13.0976 11.6834 13.0976 12.3166 12.7071 12.7071L4.70711 20.7071C4.31658 21.0976 3.68342 21.0976 3.29289 20.7071C2.90237 20.3166 2.90237 19.6834 3.29289 19.2929L10.5858 12L3.29289 4.70711C2.90237 4.31658 2.90237 3.68342 3.29289 3.29289Z" clip-rule="evenodd" fill-rule="evenodd" undefined="1"></path>
<path fill="#333" d="M11.2929 3.29289C11.6834 2.90237 12.3166 2.90237 12.7071 3.29289L20.7071 11.2929C21.0976 11.6834 21.0976 12.3166 20.7071 12.7071L12.7071 20.7071C12.3166 21.0976 11.6834 21.0976 11.2929 20.7071C10.9024 20.3166 10.9024 19.6834 11.2929 19.2929L18.5858 12L11.2929 4.70711C10.9024 4.31658 10.9024 3.68342 11.2929 3.29289Z" clip-rule="evenodd" fill-rule="evenodd" undefined="1"></path>
</svg><?php esc_html_e( 'Enter your license key and the email used to purchase the plugin.', 'flowmattic' ); ?></li>
			<li class="list-group-item ps-1"><svg class="me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" data-reactroot="">
<path fill="#333" d="M3.29289 3.29289C3.68342 2.90237 4.31658 2.90237 4.70711 3.29289L12.7071 11.2929C13.0976 11.6834 13.0976 12.3166 12.7071 12.7071L4.70711 20.7071C4.31658 21.0976 3.68342 21.0976 3.29289 20.7071C2.90237 20.3166 2.90237 19.6834 3.29289 19.2929L10.5858 12L3.29289 4.70711C2.90237 4.31658 2.90237 3.68342 3.29289 3.29289Z" clip-rule="evenodd" fill-rule="evenodd" undefined="1"></path>
<path fill="#333" d="M11.2929 3.29289C11.6834 2.90237 12.3166 2.90237 12.7071 3.29289L20.7071 11.2929C21.0976 11.6834 21.0976 12.3166 20.7071 12.7071L12.7071 20.7071C12.3166 21.0976 11.6834 21.0976 11.2929 20.7071C10.9024 20.3166 10.9024 19.6834 11.2929 19.2929L18.5858 12L11.2929 4.70711C10.9024 4.31658 10.9024 3.68342 11.2929 3.29289Z" clip-rule="evenodd" fill-rule="evenodd" undefined="1"></path>
</svg><?php esc_html_e( 'Once the license is activated, you\'ll be able to receive automatic updates and unlock premium features.', 'flowmattic' ); ?></li>
		</ul>
	</div>
</form>
	<?php
}

/**
 * Action to activate the license.
 *
 * @since 1.0
 * @access public
 * @return void
 */
function flowmattic_action_activate_license() {

	check_admin_referer( 'el-license' );

	$license_key   = ! empty( $_POST['flowmattic_license_key'] ) ? $_POST['flowmattic_license_key'] : '';
	$license_email = ! empty( $_POST['el_license_email'] ) ? $_POST['el_license_email'] : '';

	update_option( 'flowmattic_license_key', $license_key );
	update_option( 'flowmattic_license_email', $license_email, false );
	update_option( '_site_transient_update_plugins', '', false );

	// Delete transient for integrations.
	delete_transient( 'flowmattic_integrations' );

	// Delete transient for workflow templates.
	delete_transient( 'flowmattic_workflow_templates' );

	// Delete transient for license response check.
	delete_transient( 'flowmattic_license_response_check' );

	// Activate the license.
	$license = wp_flowmattic()->check_license();

	set_transient( 'flowmattic_license_response_check', $license, HOUR_IN_SECONDS * 24 );

	wp_safe_redirect( admin_url( 'admin.php?page=flowmattic-license' ) );
}

/**
 * Action to deactivate the license.
 *
 * @since 1.0
 * @access public
 * @return void
 */
function flowmattic_action_deactivate_license() {
	check_admin_referer( 'el-license' );
	$message = '';
	if ( FlowMattic_Updater::RemoveLicenseKey( FLOWMATTIC_PLUGIN_FILE, $message ) ) {
		update_option( 'flowmattic_license_key', '' );
		update_option( '_site_transient_update_plugins', '', false );

		// Delete transient for integrations.
		delete_transient( 'flowmattic_integrations' );

		// Delete transient for workflow templates.
		delete_transient( 'flowmattic_workflow_templates' );

		// Delete transient for license response check.
		delete_transient( 'flowmattic_license_response_check' );

	}
	wp_safe_redirect( admin_url( 'admin.php?page=flowmattic-license' ) );
}

add_action( 'admin_post_FlowMattic_el_deactivate_license', 'flowmattic_action_deactivate_license' );
add_action( 'admin_post_FlowMattic_el_activate_license', 'flowmattic_action_activate_license' );

/**
 * Redirect to dashboard on plugin activation.
 *
 * @since 1.0
 * @access public
 * @param string $plugin Plugin slug.
 * @return void
 */
function flowmattic_activation_redirect( $plugin ) {
	if ( plugin_basename( FLOWMATTIC_PLUGIN_FILE ) === $plugin ) {
		exit( wp_safe_redirect( admin_url( 'admin.php?page=flowmattic' ) ) );
	}
}
add_action( 'activated_plugin', 'flowmattic_activation_redirect' );

/**
 * Listen to the custom actions for workflow.
 *
 * @access public
 * @since 1.0
 * @param string $workflow_id Workflow ID.
 * @param array  $data        Action data.
 * @return void
 */
function flowmattic_custom_action_trigger( $workflow_id, $data ) {
	$workflow_live_id = get_option( 'webhook-capture-live', false );
	$submited_data    = $data;
	$simple_entry     = array();

	foreach ( $data as $key => $value ) {
		if ( is_array( $value ) ) {
			$simple_entry = flowmattic_recursive_array( $simple_entry, $key, $value );
		} else {
			$simple_entry[ $key ] = $value;
		}
	}

	$submited_data = $simple_entry;

	if ( $workflow_live_id ) {
		update_option( 'webhook-capture-' . $workflow_live_id, $submited_data, false );
		delete_option( 'webhook-capture-live' );

		// Do not execute workflow if capture data in process.
		return;
	}

	// Run the workflow.
	$flowmattic_workflow = new FlowMattic_Workflow();
	$flowmattic_workflow->run( $workflow_id, $submited_data );
}
add_action( 'flowmattic_trigger_workflow', 'flowmattic_custom_action_trigger', 99, 2 );

/**
 * Register custom cron schedule for hourly.
 *
 * @access public
 * @since 1.0.3
 * @param array $schedules Cron schedules.
 * @return array
 */
function flowmattic_custom_hourly_cron_schedule( $schedules ) {
	$schedules['flowmattic_hourly'] = array(
		'interval' => 3590,
		'display'  => __( 'Once every hour' ),
	);

	for ( $i = 1; $i < 60; $i++ ) {
		$interval = 'flowmattic_every_' . $i . '_minutes';

		// Adds per minute to the existing schedules.
		$schedules[ $interval ] = array(
			'interval' => ( $i * 60 ),
			'display'  => 'Every ' . $i . ' Minutes ( FlowMattic )',
		);
	}

	// Schedules for polling.
	$schedules['flowmattic_every_120_minutes'] = array(
		'interval' => 120 * 60,
		'display'  => 'Every 2 Hours',
	);
	$schedules['flowmattic_every_180_minutes'] = array(
		'interval' => 180 * 60,
		'display'  => 'Every 3 Hours',
	);
	$schedules['flowmattic_every_360_minutes'] = array(
		'interval' => 360 * 60,
		'display'  => 'Every 6 Hours',
	);
	$schedules['flowmattic_every_720_minutes'] = array(
		'interval' => 720 * 60,
		'display'  => 'Every 12 Hours',
	);

	return $schedules;
}

add_filter( 'cron_schedules', 'flowmattic_custom_hourly_cron_schedule' );

/* Create FlowMattic Workflow Manager User Role */
if ( get_role( 'contributor' ) ) {
	add_role(
		'flowmattic_workflow_manager', // System name of the role.
		__( 'FlowMattic - Workflow Manager' ), // Display name of the role.
		array(
			'read'          => true,
			'edit_posts'    => true,
			'publish_posts' => true,
			'delete_posts'  => true,
		)
	);
}

/**
 * Upgrade the administrator Role
 *
 * @access public
 * @since 1.3.0
 * @updated 4.1.8
 * @return void
 */
function flowmattic_admin_level_up() {
	// If using ajax, don't execute!
	if ( ! defined( 'DOING_AJAX' ) ) {
		// Retrieve the user's roles.
		$user_roles = wp_get_current_user()->roles;

		// Check if the 'administrator' role is among the assigned roles.
		if ( in_array( 'administrator', $user_roles, true ) ) {

			// Retrieve the Administrator role.
			$administrator_role = get_role( 'administrator' );

			// Add capability to the Administrator role if it exists.
			if ( $administrator_role ) {
				$administrator_role->add_cap( 'manage_workflows' );
			}

			// Retrieve the Workflow manager role.
			$workflow_manager_role = get_role( 'flowmattic_workflow_manager' );

			// Add capability to the Workflow manager role if it exists.
			if ( $workflow_manager_role ) {
				$workflow_manager_role->add_cap( 'manage_workflows' );
			}
		}
	}
}
add_action( 'admin_init', 'flowmattic_admin_level_up' );

/**
 * Parse webhook data for WhatsApp.
 *
 * @access public
 * @since 1.4.0
 * @param array $webhook_data Webhook data from WhatsApp webhook.
 * @return array
 */
function flowmattic_whatsapp_webhook_response( $webhook_data ) {
	$json_decode_data = json_decode( $webhook_data, true );
	$response_data    = array();

	foreach ( $json_decode_data[0]['value'] as $key => $value ) {
		if ( 'messages' === $key ) {
			$response_data = $value[0];

			$response_data['text'] = $response_data['text']['body'];

			if ( isset( $response_data['button'] ) ) {
				$response_data['button_payload'] = ( isset( $response_data['button'][0]['payload'] ) ) ? $response_data['button'][0]['payload'] : '';
				$response_data['button_text']    = ( isset( $response_data['button'][0]['text'] ) ) ? $response_data['button'][0]['text'] : '';
				unset( $response_data['button'] );
			}
		}
	}

	return $response_data;
}

/**
 * Ajax to handle workflow export file download.
 *
 * @access public
 * @since 1.4.0
 * @return void
 */
function flowmattic_export_workflow() {
	check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

	$workflow_id = isset( $_POST['workflowID'] ) ? $_POST['workflowID'] : '';

	// Get the workflow.
	$args     = array(
		'workflow_id' => $workflow_id,
	);
	$workflow = wp_flowmattic()->workflows_db->get( $args );

	$workflow_steps = json_decode( $workflow->workflow_steps, true );

	foreach ( $workflow_steps as $key => $step ) {
		// Remove captured data from export file.
		unset( $step['capturedData'] );

		foreach ( $step as $data_key => $data_value ) {
			if ( ! is_array( $data_value ) ) {
				$data_value        = stripslashes( $data_value );
				$step[ $data_key ] = $data_value;
			}
		}

		$workflow_steps[ $key ] = $step;
	}

	$data = array(
		'workflow_name'     => $workflow->workflow_name,
		'workflow_steps'    => $workflow_steps,
		'workflow_settings' => json_decode( $workflow->workflow_settings, true ),
	);

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=flowmattic-workflow-' . $workflow_id . '.json' );
	header( 'Expires: 0' );

	echo wp_json_encode( $data );

	die();
}

add_action( 'wp_ajax_flowmattic_export_workflow', 'flowmattic_export_workflow' );

/**
 * Ajax to handle workflow import.
 *
 * @access public
 * @since 1.4.0
 * @return void
 */
function flowmattic_import_workflow() {
	check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

	$workflow_data = isset( $_POST['workflowData'] ) ? $_POST['workflowData'] : '';

	$workflow_id = flowmattic_random_string();

	// If workflow is imported, append _IMPORTED, if AI generated, append _AI.
	$workflow_name = isset( $_POST['ai_workflow'] ) ? $workflow_data['workflow_name'] . '_AI' : $workflow_data['workflow_name'] . '_IMPORTED';

	$data = array(
		'workflow_id'       => $workflow_id,
		'workflow_name'     => $workflow_name,
		'workflow_steps'    => is_array( $workflow_data['workflow_steps'] ) ? $workflow_data['workflow_steps'] : json_decode( $workflow_data['workflow_steps'], true ),
		'workflow_settings' => is_array( $workflow_data['workflow_settings'] ) ? $workflow_data['workflow_settings'] : json_decode( $workflow_data['workflow_settings'], true ),
	);

	// Set the imported workflow as draft.
	$data['workflow_settings']['status'] = 'off';

	// Set the workflow access to blank.
	$data['workflow_settings']['user_email'] = '';

	// Set the imported time.
	$data['workflow_settings']['time'] = date_i18n( 'd-m-Y h:i:s A' );

	$workflow_db = wp_flowmattic()->workflows_db;
	$status      = $workflow_db->insert( $data );

	$reply = array(
		'status'      => $status,
		'workflow_id' => $workflow_id,
	);

	echo wp_json_encode( $reply );

	die();
}

add_action( 'wp_ajax_flowmattic_import_workflow', 'flowmattic_import_workflow' );

/**
 * Ajax to handle workflow template import.
 *
 * @access public
 * @since 3.1.0
 * @return void
 */
function flowmattic_import_workflow_template() {
	check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

	$template_id   = isset( $_POST['template_id'] ) ? $_POST['template_id'] : '';
	$template_name = isset( $_POST['template_name'] ) ? $_POST['template_name'] : '';
	$license_key   = get_option( 'flowmattic_license_key', '' );

	$site_url    = site_url();
	$server_host = 'https://flowmattic.com/workflows/';

	$args = array(
		'license'     => $license_key,
		'site'        => $site_url,
		'method'      => 'downloadWorkflow',
		'template_id' => $template_id,
		'nonce'       => wp_create_nonce( 'workflow-templates' ),
	);

	$request       = wp_remote_get( $server_host . '?' . http_build_query( $args ) );
	$request       = wp_remote_retrieve_body( $request );
	$workflow_data = json_decode( $request, true );
	$workflow_id   = flowmattic_random_string();

	$data = array(
		'workflow_id'       => $workflow_id,
		'workflow_name'     => ( '' !== $template_name ) ? $template_name : $workflow_data['workflow_name'] . '_IMPORTED',
		'workflow_steps'    => is_array( $workflow_data['workflow_steps'] ) ? $workflow_data['workflow_steps'] : json_decode( $workflow_data['workflow_steps'], true ),
		'workflow_settings' => is_array( $workflow_data['workflow_settings'] ) ? $workflow_data['workflow_settings'] : json_decode( $workflow_data['workflow_settings'], true ),
	);

	// Set the imported workflow as draft.
	$data['workflow_settings']['status'] = 'off';

	// Set the workflow access to blank.
	$data['workflow_settings']['user_email'] = '';

	// Set the imported time.
	$data['workflow_settings']['time'] = date_i18n( 'd-m-Y h:i:s A' );

	$workflow_db = wp_flowmattic()->workflows_db;
	$status      = $workflow_db->insert( $data );

	$reply = array(
		'status'      => $status,
		'workflow_id' => $workflow_id,
	);

	echo wp_json_encode( $reply );

	die();
}

add_action( 'wp_ajax_flowmattic_import_workflow_template', 'flowmattic_import_workflow_template' );

/**
 * Ajax to handle workflow clone.
 *
 * @access public
 * @since 2.0
 * @return void
 */
function flowmattic_clone_workflow() {
	check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

	$workflow_id = isset( $_POST['workflowID'] ) ? $_POST['workflowID'] : '';

	// Get the workflow.
	$args     = array(
		'workflow_id' => $workflow_id,
	);
	$workflow = wp_flowmattic()->workflows_db->get( $args );

	$workflow_steps = json_decode( $workflow->workflow_steps, true );

	foreach ( $workflow_steps as $key => $step ) {
		foreach ( $step as $data_key => $data_value ) {
			if ( ! is_array( $data_value ) ) {
				$data_value        = stripslashes( $data_value );
				$step[ $data_key ] = $data_value;
			}
		}

		$workflow_steps[ $key ] = $step;
	}

	$data = array(
		'workflow_id'       => flowmattic_random_string(),
		'workflow_name'     => $workflow->workflow_name . '_CLONED',
		'workflow_steps'    => $workflow_steps,
		'workflow_settings' => json_decode( $workflow->workflow_settings, true ),
	);

	// Set the cloneed workflow as draft.
	$data['workflow_settings']['status'] = 'off';

	// Set the cloned time.
	$data['workflow_settings']['time'] = date_i18n( 'd-m-Y h:i:s A' );

	$workflow_db = wp_flowmattic()->workflows_db;
	$status      = $workflow_db->insert( $data );

	$reply = array(
		'status' => $status,
	);

	echo wp_json_encode( $reply );

	die();
}

add_action( 'wp_ajax_flowmattic_clone_workflow', 'flowmattic_clone_workflow' );

/**
 * Check if the integration is disabled for user level.
 *
 * @access public
 * @since 1.0
 * @param array $application Application slug.
 * @return bool
 */
function flowmattic_is_app_disabled( $application ) {
	$settings = get_option( 'flowmattic_settings', array() );

	if ( isset( $settings[ 'disable-app-' . $application ] ) && $application === $settings[ 'disable-app-' . $application ] ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Register add_action for each workflow if it is using plugin actions as trigger.
 *
 * @access public
 * @since 2.0
 * @return void
 */
function flowmattic_execute_hooks() {
	$workflow_hooks = get_option( 'flowmattic_workflow_hooks', array() );
	if ( ! empty( $workflow_hooks ) ) {
		foreach ( $workflow_hooks as $workflow_id => $capture_hook ) {
			add_action( $capture_hook, 'flowmattic_capture_plugin_action_data', 10, 10 );
		}
	}
}

/**
 * Execute the action set in the plugin actions trigger..
 *
 * @access public
 * @since 2.0
 * @return array
 */
function flowmattic_capture_plugin_action_data() {
	$num_args = func_num_args();
	$args     = func_get_args();

	if ( ! $num_args ) {
		return false;
	}

	$final_capture = array();

	$live_workflow_id = get_option( 'webhook-capture-live', false );

	// Get all workflow hooks registered.
	$workflow_hooks = get_option( 'flowmattic_workflow_hooks', array() );

	// Loop through workflow hooks and if current action is fired, execute the workflow.
	if ( ! empty( $workflow_hooks ) ) {
		foreach ( $workflow_hooks as $workflow_id => $capture_hook ) {
			// Check if the current action is fired.
			if ( did_action( $capture_hook ) ) {
				if ( 1 === $num_args ) {
					$final_capture = is_array( $args ) ? $args[0] : $args;
				} else {
					for ( $i = 0; $i < $num_args; $i++ ) {
						$final_capture[ 'arg-' . $i ] = $args[ $i ];
					}
				}

				// Check if live capturing is in place, and that matches the capturing workflow.
				if ( $workflow_id === $live_workflow_id ) {

					update_option( 'webhook-capture-' . $workflow_id, $final_capture, false );
					delete_option( 'webhook-capture-live' );

					// If it is in capturing mode, skip the workflow execution by returning data here.
					return $final_capture;
				}

				// Run the workflow.
				$flowmattic_workflow = new FlowMattic_Workflow();
				$flowmattic_workflow->run( $workflow_id, $final_capture );
			}
		}
	}
}

add_action( 'init', 'flowmattic_execute_hooks' );

// Remove FluentCRM global search script from the workflows editor.
if ( isset( $_GET['page'] ) && 'flowmattic-workflows' === $_GET['page'] ) {
	add_filter( 'fluentcrm_disable_global_search', '__return_true' );
}

/**
 * Save the files from mailhook to WP.
 *
 * @access public
 * @since 2.2.0
 * @param string $filename     Filename with extension.
 * @param string $base64_file  Base64 encoded file.
 * @param string $content_type Content type.
 */
function flowmattic_import_file_from_mailhook( $filename, $base64_file, $content_type ) {

	// Upload dir.
	$upload_dir  = wp_upload_dir();
	$upload_path = str_replace( '/', DIRECTORY_SEPARATOR, $upload_dir['path'] ) . DIRECTORY_SEPARATOR;

	$file_decode = base64_decode( $base64_file ); // @codingStandardsIgnoreLine

	// Save the file in the uploads directory.
	$upload_file = file_put_contents( $upload_path . $filename, $file_decode ); // @codingStandardsIgnoreLine

	$attachment = array(
		'post_mime_type' => $content_type,
		'post_title'     => $filename,
		'post_content'   => '',
		'post_status'    => 'inherit',
		'guid'           => $upload_dir['url'] . '/' . $filename,
	);

	// Insert attachment for use in WP.
	$file_id = wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $filename );

	// Return the file attachment post ID.
	return array(
		'file_id'      => $file_id,
		'file_name'    => $filename,
		'file_url'     => wp_get_attachment_url( $file_id, 'full' ),
		'content_type' => $content_type,
	);
}

/**
 * Parse the CSV data.
 *
 * @access public
 * @since 2.2.0
 * @param string $encoded_csv_data Base64 encoded CSV file data.
 */
function flowmattic_parse_csv( $encoded_csv_data ) {
	$csv_content = trim( base64_decode( $encoded_csv_data ) ); // @codingStandardsIgnoreLine
	$csv_data    = array_map( 'str_getcsv', explode( "\n", $csv_content ) );

	try {
		// Read csv headers.
		$headers = $csv_data[0];
		unset( $csv_data[0] );

		// Initialize CSV array.
		$csv_json = array();

		foreach ( $csv_data as $key => $row ) {
			$csv_json[] = array_combine( $headers, $row );
		}

		return array(
			'data' => wp_json_encode( $csv_json ),
		);
	} catch ( Error $e ) {
		return array(
			'data'  => wp_json_encode( $csv_data ),
			'error' => $e->getMessage(),
		);
	}
}

/**
 * Parse the CSV data by URL.
 *
 * @access public
 * @since 3.2.0
 * @param string $csv_file    CSV file URL.
 * @param bool   $has_headers If the csv file has headers.
 */
function flowmattic_parse_csv_file( $csv_file, $has_headers = false ) {
	$request       = wp_remote_get( $csv_file );
	$response_code = wp_remote_retrieve_response_code( $request );

	if ( 200 === $response_code ) {
		$csv_content = wp_remote_retrieve_body( $request );
		$csv_data    = array_map( 'str_getcsv', explode( "\n", $csv_content ) );

		try {
			// Initialize CSV array.
			$csv_json = array();

			if ( $has_headers ) {
				// Read csv headers.
				$headers = $csv_data[0];
				unset( $csv_data[0] );

				foreach ( $csv_data as $key => $row ) {
					if ( ! empty( $row ) && ( count( $row ) === count( $headers ) ) ) {
						$is_col_empty = 0;
						foreach ( $row as $row_index => $row_val ) {
							if ( '' === trim( $row_val ) ) {
								++$is_col_empty;
							}
						}

						$is_row_empty = count( $row ) === $is_col_empty ? true : false;

						if ( ! $is_row_empty ) {
							$csv_json[] = array_combine( $headers, $row );
						}
					}
				}
			} else {
				$headers = $csv_data[0];
				foreach ( $csv_data as $key => $row ) {
					if ( ! empty( $row ) && ( count( $row ) === count( $headers ) ) ) {
						$is_col_empty = 0;
						foreach ( $row as $row_index => $row_val ) {
							if ( '' === trim( $row_val ) ) {
								++$is_col_empty;
							}
						}

						$is_row_empty = count( $row ) === $is_col_empty ? true : false;

						if ( ! $is_row_empty ) {
							$csv_json[] = $row;
						}
					}
				}
			}

			return array(
				'status' => 'success',
				'data'   => wp_json_encode( $csv_json ),
			);
		} catch ( Error $e ) {
			return array(
				'status' => 'error',
				'data'   => $csv_data,
				'error'  => $e->getMessage(),
			);
		}
	} else {
		return array(
			'status'        => 'error',
			'response_code' => $response_code,
		);
	}
}

/**
 * Save the files from mailhook to WP.
 *
 * @access public
 * @since 2.2.0
 * @param string $email_text  Email in text.
 */
function flowmattic_parse_emails_from_mailhook( $email_text ) {
	// Test string for checking email.
	$test_patt = "/(?:[a-z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+=?^_`{|}~-]+)*|\"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*\")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/";

	// Convert text to lower case.
	$email_text = strtolower( $email_text );

	// Comapare using preg_match_all() method.
	preg_match_all( $test_patt, $email_text, $valid );

	$parsed_emails = array();
	if ( ! empty( $valid ) ) {
		$emails = array_unique( $valid[0] );

		foreach ( $emails as $email ) {
			$parsed_emails[] = array( $email );
		}

		return array(
			'array' => $parsed_emails,
			'list'  => implode( ',', $emails ),
		);
	}

	return array();
}

/**
 * Get the attachment absolute path from its url
 *
 * @access public
 * @since 2.2.0
 * @param string $url the attachment url to get its absolute path.
 *
 * @return bool|string It returns the absolute path of an attachment.
 */
function flowmattic_attachment_url_to_path( $url ) {
	$parsed_url = wp_parse_url( $url );
	if ( empty( $parsed_url['path'] ) ) {
		return false;
	}

	$file = ABSPATH . ltrim( $parsed_url['path'], '/' );
	if ( file_exists( $file ) ) {
		return $file;
	}

	return false;
}

/**
 * Remove the unwanted CSS and JS scripts from 3rd party plugins on FlowMattic admin pages to avoid conflicts.
 *
 * @access public
 * @since 3.0
 * @param string $hook Current page hook prefix.
 *
 * @return void
 */
function flowmattic_dequeue_unwanted_scripts( $hook ) {
	global $wp_scripts, $wp_styles;

	// Get the current screen object.
	$current_screen = get_current_screen();

	// Continue only if current screen is for FlowMattic.
	if ( ! isset( $current_screen->id ) || false === strpos( $current_screen->id, 'flowmattic' ) ) {
		return;
	}

	// Loop through all registered scripts.
	foreach ( $wp_scripts->registered as $handle => $script ) {
		// Check if the script is not from FlowMattic plugin and from WP defaults.
		if ( ! empty( $script->src ) && false !== strpos( $script->src, 'wp-content' ) ) {
			if ( false === strpos( $script->src, 'plugins/flowmattic' ) && false === strpos( $script->src, 'flowmattic-apps' ) && false === strpos( $script->src, 'plugins/query-monitor' ) ) {
				wp_dequeue_script( $handle );
				wp_deregister_script( $handle );
			}
		}
	}

	// Loop through all registered styles.
	foreach ( $wp_styles->registered as $handle => $style ) {
		// Check if the style is not from FlowMattic plugin and from WP defaults.
		if ( ! empty( $style->src ) && false !== strpos( $style->src, 'wp-content' ) ) {
			if ( false === strpos( $style->src, 'plugins/flowmattic' ) && false === strpos( $style->src, 'flowmattic-apps' ) && false === strpos( $style->src, 'plugins/query-monitor' ) ) {
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			}
		}
	}

	if ( false !== strpos( $hook, 'flowmattic' ) ) {
		// Remove jQuery modal scripts.
		wp_deregister_style( 'jquery-modal' );

		// Disable the emoji's.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	}
}
add_action( 'admin_enqueue_scripts', 'flowmattic_dequeue_unwanted_scripts', 99 );
add_action( 'admin_print_scripts', 'flowmattic_dequeue_unwanted_scripts', 100 );
add_action( 'admin_footer', 'flowmattic_dequeue_unwanted_scripts', 100 );

/**
 * Custom alternative to download_url to avoid conflicts with server config.
 *
 * @access public
 * @since 3.0
 * @param string $url File URL to download.
 *
 * @return string File.
 */
function flowmattic_download_url( $url ) {
	if ( ! function_exists( 'wp_tempnam' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	// Temporary file to store the downloaded content.
	$tmp_file = wp_tempnam( $url );

	// Attempt to download using wp_remote_get.
	$response = wp_remote_get( $url, array( 'timeout' => 300 ) );

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = wp_remote_retrieve_body( $response );
		file_put_contents( $tmp_file, $body );
		return $tmp_file;
	}

	// If wp_remote_get fails, use cURL as a fallback.
	if ( function_exists( 'curl_init' ) ) {
		// @codingStandardsIgnoreStart
		$ch = curl_init();
		$fp = fopen( $tmp_file, 'wb' );

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_FILE, $fp );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 300 );

		$success = curl_exec( $ch );

		curl_close( $ch );
		fclose( $fp );

		// @codingStandardsIgnoreEnd

		if ( $success ) {
			return $tmp_file;
		}
	}

	// If both methods fail, return an error.
	return new WP_Error( 'download_failed', __( 'Download failed.' ) );
}

/**
 * Get the integrations.
 *
 * @access public
 * @since 3.0
 * @param string $type Requested integration type.
 *
 * @return object Current live integrations.
 */
function flowmattic_get_integrations( $type = 'integrations' ) {
	$all_integrations = get_transient( 'flowmattic_integrations' );
	$license_key      = get_option( 'flowmattic_license_key', '' );

	if ( ( false === $all_integrations || isset( $all_integrations->message ) ) && '' !== $license_key ) {
		$site_url    = site_url();
		$server_host = FLOWMATTIC_UPDATES_SERVER . '/integration/';

		$args = array(
			'license' => $license_key,
			'site'    => $site_url,
			'type'    => $type,
		);

		$request          = wp_remote_get( $server_host . '?' . http_build_query( $args ) );
		$request_body     = wp_remote_retrieve_body( $request );
		$all_integrations = json_decode( $request_body );

		if ( ! $all_integrations ) {
			$all_integrations = $request_body;
		}

		set_transient( 'flowmattic_integrations', $all_integrations, HOUR_IN_SECONDS * 24 );
	}

	return (array) $all_integrations;
}

/**
 * Get the integrations.
 *
 * @access public
 * @since 3.1.0
 *
 * @return object Get workflow templates.
 */
function flowmattic_get_workflow_templates() {
	$all_workflow_templates = get_transient( 'flowmattic_workflow_templates' );
	$license_key            = get_option( 'flowmattic_license_key', '' );

	if ( false === $all_workflow_templates && '' !== $license_key ) {
		$site_url    = site_url();
		$server_host = 'https://flowmattic.com/workflows/';

		$args = array(
			'license' => $license_key,
			'site'    => $site_url,
			'method'  => 'getWorkflows',
			'nonce'   => wp_create_nonce( 'workflow-templates' ),
		);

		$request                = wp_remote_get( $server_host . '?' . http_build_query( $args ) );
		$request                = wp_remote_retrieve_body( $request );
		$all_workflow_templates = json_decode( $request, true );

		if ( ! is_array( $all_workflow_templates ) ) {
			$all_workflow_templates = $request;
		}

		set_transient( 'flowmattic_workflow_templates', $all_workflow_templates, HOUR_IN_SECONDS * 24 );
	}

	return $all_workflow_templates;
}

/**
 * Helper function to get the time formats.
 *
 * @access public
 * @since 3.0
 *
 * @return array All available time formats.
 */
function flowmattic_get_time_formats() {
	return array(
		'D M d H:i:s O Y'  => 'ddd MMM DD HH:mm:ss Z YYYY (Sun Jan 22 23:04:05 -0000 2023)',
		'D m d H:i'        => 'ddd MM DD HH:mm (Sun Jan 22 23:04)',
		'F d Y H:i:s'      => 'MMMM DD YYYY HH:mm:ss (January 22 2023 23:04:05)',
		'F d Y'            => 'MMMM DD YYYY (January 22 2023)',
		'M d Y'            => 'MMM DD YYYY (Jan 22 2023)',
		'Y-m-dTH:i:sO'     => 'YYYY-MM-DDTHH:mm:ssZ (2023-01-22T23:04:05-0000)',
		'Y-m-d H:i:s O'    => 'YYYY-MM-DD HH:mm:ss Z (2023-01-22 23:04:05 -0000)',
		'Y-m-d'            => 'YYYY-MM-DD (2023-01-22)',
		'Y/m/d'            => 'YYYY/MM/DD (2023/01/22)',
		'Y/m/d H:i:s'      => 'YYYY/MM/DD HH:mm:ss (2023/01/22 23:04:05)',
		'Y/m/d h:i A'      => 'YYYY/MM/DD hh:mm A (2023/01/22 11:04 PM)',
		'm-d-Y'            => 'MM-DD-YYYY (01-22-2023)',
		'm/d/Y'            => 'MM/DD/YYYY (01/22/2023)',
		'm/d/y'            => 'MM/DD/YY (01/22/23)',
		'd-m-Y'            => 'DD-MM-YYYY (22-01-2023)',
		'd/m/Y'            => 'DD/MM/YYYY (22/01/2023)',
		'd/m/y'            => 'DD/MM/YY (22/01/23)',
		'd/m/Y h:i:s A'    => 'DD/MM/YYYY hh:mm:ss A (09/12/2023 05:30:11 PM)',
		'd/m/Y H:i:s'      => 'DD/MM/YYYY HH:mm:ss (09/12/2023 17:30:11)',
		'D, d M Y H:i:s'   => 'DDD, DD MMM YYYY HH:mm:ss (Sun, 22 Jan 2023 23:04:05)',
		'D, d M Y'         => 'DDD, DD MMM YYYY (Sun, 22 Jan 2023)',
		'D, d M y h:i:s A' => 'DDD, DD MMM YY hh:mm:ss A (Sun, 22 Jan 23 11:04:05 PM)',
		'D, d M y h:i A'   => 'DDD, DD MMM YY hh:mm A (Sun, 22 Jan 23 11:04 PM)',
		'Y-m-d H:i:s'      => 'YYYY-MM-DD HH:mm:ss (2023-11-05 13:08:16)',
		'd-m-Y H:i:s'      => 'DD-MM-YYYY HH:mm:ss (09-12-2023 15:30:11)',
		'd-m-Y h:i:s A'    => 'DD-MM-YYYY hh:mm:ss A (09-12-2023 5:30:11 AM)',
		'Y-m-d G:i:s'      => 'YYYY-MM-DD H:M:S (2023-07-28 7:7:0)',
		'Y-m-d H:i'        => 'YYYY-MM-DD HH:mm (2023-07-28 13:08)',
		'Y-m-d h:i A'      => 'YYYY-MM-DD hh:mm A (2023-07-28 01:08 PM)',
		'Y-m-d H:i:s T'    => 'YYYY-MM-DD HH:mm:ss T (2023-07-28 13:08:16 UTC)',
		'Y-m-d H:i:s P'    => 'YYYY-MM-DD HH:mm:ss P (2023-07-28 13:08:16 +00:00)',
		'H:i'              => 'HH:mm (13:08)',
		'U'                => 'Unix Timestamp (1627480096)',
		'Uv'               => 'Unix Timestamp with milliseconds (1627480096000)',
		'Uu'               => 'Unix Timestamp with microseconds (1627480096000000)',
	);
}

/**
 * Replace a specific value with another value in a nested array.
 *
 * @access public
 * @since 3.0
 * @param array  $nested_array  The input nested array to search and replace the value in.
 * @param string $search_value  The value to search for in the nested array.
 * @param mixed  $replace_value The value to replace the search value with. This can be any data type.
 * @return array The updated nested array with the search value replaced by the replace value.
 */
function flowmattic_dynamic_tag_values( $nested_array, $search_value, $replace_value ) {
	// $nested_array = flowmattic_stripslashes_deep( $nested_array );
	$json_string = stripslashes( wp_json_encode( $nested_array ) );

	// Check if the search value exists in the JSON-encoded array.
	if ( strpos( $json_string, $search_value ) === false ) {
		// Search value not found, return the original array.
		return $nested_array;
	}
	foreach ( $nested_array as $key => $value ) {
		if ( is_array( $value ) ) {
			$nested_array[ $key ] = flowmattic_dynamic_tag_values( $value, $search_value, $replace_value );
		} elseif ( is_array( $replace_value ) ) {
			if ( $value === $search_value ) {
				$nested_array[ $key ] = $replace_value;
			}
		} else {
			$nested_array[ $key ] = str_replace( $search_value, $replace_value, $value );
		}
	}

	return $nested_array;
}

/**
 * Perform a deep stripslashes operation on array items
 *
 * @access public
 * @since 3.0.1
 * @param mixed $value Value to be cleaned from slashes.
 *
 * @return mixed Cleaned value.
 */
function flowmattic_stripslashes_deep( $value ) {
	$value = is_array( $value ) ? array_map( 'flowmattic_stripslashes_deep', $value ) : stripslashes( $value );

	return $value;
}

/**
 * Retrieves a value from a nested array by index.
 *
 * @access public
 * @since 3.0.2
 * @param array  $ref_array The array to search.
 * @param string $index     The index to search for.
 *
 * @return mixed|null The value of the found index or null if not found.
 */
function flowmattic_get_value_by_index( $ref_array, $index ) {
	// Check if the index exists in the array.
	if ( isset( $ref_array[ $index ] ) ) {
		return $ref_array[ $index ];
	}

	// If the index does not exist in the array, look for it in the nested arrays.
	foreach ( $ref_array as $value ) {
		if ( is_array( $value ) ) {
			$result = flowmattic_get_value_by_index( $value, $index );
			if ( $result !== null ) {
				return $result;
			}
		} elseif ( is_array( json_decode( $value, true ) ) ) {
			$value  = json_decode( $value, true );
			$result = flowmattic_get_value_by_index( $value, $index );
			if ( $result !== null ) {
				return $result;
			}
		}
	}

	// Return null if the index is not found.
	return null;
}

/**
 * Check if integration updates are available.
 *
 * @access public
 * @since 3.1.1
 *
 * @return bool True if updates are available, false if not.
 */
function flowmattic_is_integration_update_available() {
	$license_key = get_option( 'flowmattic_license_key', '' );

	// If the license key is not set, return false.
	if ( '' === $license_key ) {
		return false;
	}

	$flowmattic_apps        = wp_flowmattic()->apps;
	$installed_applications = $flowmattic_apps->get_all_applications();
	$all_integrations       = flowmattic_get_integrations();
	$is_update_available    = 0;

	$license = wp_flowmattic()->check_license();

	// License is expired.
	if ( is_string( $license ) ) {
		return false;
	}

	if ( ! $license || ! is_array( $all_integrations ) ) {
		return false;
	}

	if ( is_array( $installed_applications ) && $all_integrations ) {
		$all_integrations = (array) $all_integrations;
		foreach ( $installed_applications as $app => $app_settings ) {
			$find_app = array_search( $app, array_column( $all_integrations, 'slug' ), true );
			$app_key  = array_keys( $all_integrations )[ $find_app ];

			if ( $all_integrations[ $app_key ] ) {
				if ( isset( $app_settings['version'] ) && isset( $all_integrations[ $app_key ] ) && $all_integrations[ $app_key ]->slug === $app ) {
					if ( version_compare( $app_settings['version'], $all_integrations[ $app_key ]->version, '!=' ) ) {
						++$is_update_available;
					}
				}
			}
		}
	}

	return $is_update_available;
}

/**
 * Function to convert hex to hsl.
 *
 * @access public
 * @since 4.0
 * @param string $hex Hex color code.
 * @return array
 */
function flowmattic_hex_to_hsl( $hex ) {
	$hex = str_replace( '#', '', $hex );

	if ( 3 === strlen( $hex ) ) {
		$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
		$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
		$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
	} else {
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
	}

	$hsl = array();

	$var_r = ( $r / 255 );
	$var_g = ( $g / 255 );
	$var_b = ( $b / 255 );

	$var_min = min( $var_r, $var_g, $var_b );
	$var_max = max( $var_r, $var_g, $var_b );
	$del_max = $var_max - $var_min;

	$l = ( $var_max + $var_min ) / 2;

	if ( 0 === $del_max ) {
		$h = 0;
		$s = 0;
	} else {
		if ( 0.5 > $l ) {
			$s = $del_max / ( $var_max + $var_min );
		} else {
			$s = $del_max / ( 2 - $var_max - $var_min );
		}

		$del_r = ( ( ( $var_max - $var_r ) / 6 ) + ( $del_max / 2 ) ) / $del_max;
		$del_g = ( ( ( $var_max - $var_g ) / 6 ) + ( $del_max / 2 ) ) / $del_max;
		$del_b = ( ( ( $var_max - $var_b ) / 6 ) + ( $del_max / 2 ) ) / $del_max;

		if ( $var_r === $var_max ) {
			$h = $del_b - $del_g;
		} elseif ( $var_g === $var_max ) {
			$h = ( 1 / 3 ) + $del_r - $del_b;
		} elseif ( $var_b === $var_max ) {
			$h = ( 2 / 3 ) + $del_g - $del_r;
		}

		if ( 0 > $h ) {
			$h += 1;
		}

		if ( 1 < $h ) {
			$h -= 1;
		}
	}

	$hsl['h'] = round( $h * 360 );
	$hsl['s'] = round( $s * 100 ) . '%';
	$hsl['l'] = '95%';

	return 'hsl(' . implode( ' ', $hsl ) . ')';
}

/**
 * Function to push new value to array.
 *
 * @access public
 * @since 4.0
 * @param Array $array1 Array to push the value to.
 * @param mixed $value  Value to push to the array.
 * @return Array
 */
function flowmattic_custom_array_push( $array1, $value ) {
	$new_value = ( is_numeric( $value ) ) ? (int) $value : $value;
	array_push( $array1, $new_value );

	return $array1;
}

/**
 * Function to add new feed.
 *
 * @access public
 * @since 4.1.0
 * @return void
 */
function flowmattic_add_rss_feed() {
	// Get the feed slugs from the database.
	$feed_slugs_db = wp_flowmattic()->rss_feed_db->get_slugs();

	// Get feed slugs.
	$feed_slugs = ( ! empty( $feed_slugs_db ) ) ? $feed_slugs_db->feed_slugs : array();

	if ( empty( $feed_slugs ) ) {
		return;
	}

	// Flush the rewrite rules. Required to make the new feed URL accessible.
	flush_rewrite_rules();

	// Add the feed slugs.
	foreach ( $feed_slugs as $feed_slug ) {
		// Add the feed.
		add_feed( 'rss-feed/' . $feed_slug, 'flowmattic_build_rss_feed' );
	}
}
add_action( 'init', 'flowmattic_add_rss_feed' );

/**
 * Function build the RSS Feed.
 *
 * @access public
 * @since 4.1.0
 * @param string $content_type  Content type.
 * @param string $feed_slug_url Feed slug URL.
 * @return void
 */
function flowmattic_build_rss_feed( $content_type, $feed_slug_url ) {
	$feed_slug = explode( '/', $feed_slug_url );
	$feed_slug = end( $feed_slug );

	// Get the feed items for the slug.
	$feed_items = wp_flowmattic()->rss_feed_db->get( array( 'feed_slug' => $feed_slug ) );

	// Set the feed content type.
	header( 'Content-Type: text/xml; charset=UTF-8' );

	// Set Access-Control-Allow-Origin header.
	header( 'Access-Control-Allow-Origin: *' );

	// Set User-Agent header.
	header( 'User-Agent: FlowMattic/' . FLOWMATTIC_VERSION );

	// Get the first feed item for the channel.
	$feed_data = $feed_items[0]->feed_data;
	$feed_data = json_decode( $feed_data );

	// Get the max items.
	$max_items = ( ! empty( $feed_data->max_records ) ) ? (int) $feed_data->max_records : 50;

	// Check if the items to be deleted from database if the max items is reached.
	$remove_older_records = ( ! empty( $feed_data->remove_older_records ) ) ? $feed_data->remove_older_records : 'No';

	if ( count( $feed_items ) > $max_items ) {
		$feed_items_to_delete = array_slice( $feed_items, $max_items );
		foreach ( $feed_items_to_delete as $feed_item_to_delete ) {
			wp_flowmattic()->rss_feed_db->delete( $feed_item_to_delete->id );
		}
	}

	// Set the feed link.
	$feed_link = ( ! empty( $feed_data->feed_link ) ) ? esc_url( $feed_data->feed_link ) : home_url( $feed_slug_url );

	echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"
		xmlns:content="http://purl.org/rss/1.0/modules/content/"
		xmlns:wfw="http://wellformedweb.org/CommentAPI/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
		xmlns:atom="http://www.w3.org/2005/Atom"
		xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
		xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	>';
	echo '<channel>';
	echo '<title>' . esc_attr( $feed_data->feed_title ) . '</title>';
	echo '<link>' . esc_attr( $feed_link ) . '</link>';
	echo '<atom:link href="' . esc_attr( home_url( $feed_slug_url ) ) . '" rel="self" type="application/rss+xml" />';
	echo '<description>This feed is powered by FlowMattic\'s RSS Feed module.</description>';

	foreach ( $feed_items as $key => $feed_item ) {
		// Break the loop if the max items is reached.
		if ( $key >= $max_items ) {
			if ( strtolower( $remove_older_records ) === 'yes' ) {
				$args = array(
					'feed_id' => $feed_item->id,
				);

				// Delete the feed item from the database.
				wp_flowmattic()->rss_feed_db->delete( $args );

				continue;
			} else {
				break;
			}
		}

		// Get the feed item data.
		$feed_item = json_decode( $feed_item->feed_items );

		echo '<item>';
		echo '<title>' . esc_html( $feed_item->item_title ) . '</title>';
		echo '<link>' . esc_url( $feed_item->item_source ) . '</link>';
		echo '<description>' . esc_html( $feed_item->item_description ) . '</description>';
		echo '<pubDate>' . esc_html( $feed_item->item_pub_date ) . '</pubDate>';

		// Add the category if available.
		if ( ! empty( $feed_item->item_category ) ) {
			echo '<category>' . esc_html( $feed_item->item_category ) . '</category>';
		}

		// Add the media if available.
		if ( ! empty( $feed_item->item_media_url ) && false === strpos( $feed_item->item_media_url, '{' ) ) {
			$mime_type    = wp_check_filetype( $feed_item->item_media_url );
			$media_type   = ( ! empty( $feed_item->item_media_mime ) ) ? $feed_item->item_media_mime : ( ! empty( $mime_type['type'] ) ? $mime_type['type'] : 'audio/mpeg' );
			$media_length = ( ! empty( $feed_item->item_media_length ) ) ? $feed_item->item_media_length : 0;

			echo '<enclosure length="' . esc_attr( $media_length ) . '" type="' . esc_attr( $media_type ) . '" url="' . esc_url( $feed_item->item_media_url ) . '"/>';
		}

		// Add author if available.
		if ( ! empty( $feed_item->item_author ) && ! empty( $feed_item->item_author_email ) ) {
			echo '<author>' . esc_attr( $feed_item->item_author_email ) . ' ( ' . esc_html( $feed_item->item_author ) . ' )</author>';
		}

		// Add GUID.
		$guid = str_replace( '=', '', base64_encode( $feed_slug_url . '/' . $key ) ); // @codingStandardsIgnoreLine
		echo '<guid isPermaLink="false">' . esc_html( $guid ) . '</guid>';

		echo '</item>';
	}

	echo '</channel>';
	echo '</rss>';
}

/**
 * Function to add custom XML mime type.
 *
 * @access public
 * @since 4.2.0
 * @param array $mimes Mime types.
 * @return array
 */
function flowmattic_custom_upload_xml( $mimes ) {
	$mimes = array_merge( $mimes, array( 'xml' => 'text/xml' ) );
	return $mimes;
}

// Add the custom XML mime type.
add_filter( 'upload_mimes', 'flowmattic_custom_upload_xml' );

/**
 * Function to get the dynamic value of the provided tag.
 *
 * @access public
 * @since 4.2.2
 * @param string $dynamic_tag Tag to check value against.
 * @param string $workflow_id Current Workflow ID.
 * @return string
 */
function flowmattic_get_dynamic_tag_value( $dynamic_tag, $workflow_id ) {
	$args = array(
		'workflow_id' => $workflow_id,
	);

	$workflow       = wp_flowmattic()->workflows_db->get( $args );
	$workflow_steps = json_decode( $workflow->workflow_steps, true );

	// Get the step number from the template ID.
	preg_match( '/{([a-zA-Z]+[0-9]+)/', $dynamic_tag, $matches );
	$step_number = preg_match( '/\d+$/', $matches[1], $numbers ) ? (int) $numbers[0] : null;

	// Search for the step with stepIndex = step_number.
	$step = array_values(
		array_filter(
			$workflow_steps,
			function( $step ) use ( $step_number ) {
				return isset( $step['stepIndex'] ) && $step['stepIndex'] === $step_number;
			}
		)
	);

	// Get the capturedData from step.
	$capture_data = isset( $step[0]['capturedData'] ) ? $step[0]['capturedData'] : array();

	foreach ( $capture_data as $key => $value ) {
		$new_dynamic_tag = '{' . $matches[1] . '.' . $key . '}';

		if ( $new_dynamic_tag === $dynamic_tag ) {
			$dynamic_tag = $value;
			break;
		}
	}

	return $dynamic_tag;
}

/**
 * Convert a custom tag string with multiple items to JSON format.
 *
 * @access public
 * @since 4.3.0
 * @param string $input The input string in the format [Tag 1, Tag 2].
 * @return string JSON representation of the input.
 */
function flowmattic_convert_strings_to_json( $input ) {
	// If input is blank array, return empty array.
	if ( '[]' === $input ) {
		return $input;
	}

	// If input is valid JSON, return the input as array.
	if ( is_array( json_decode( $input, true ) ) ) {
		return json_decode( $input, true );
	}

	// Use a regular expression to extract the content inside the square brackets.
	preg_match( '/\[(.*?)\]/', $input, $matches );

	// Check if a match was found.
	if ( isset( $matches[1] ) ) {
		// Split the content by comma and trim any whitespace.
		$contents = array_map( 'trim', explode( ',', $matches[1] ) );

		return $contents;
	} else {
		return $input;
	}
}

/**
 * Function to recursively find and replace a value in a nested array.
 *
 * @access public
 * @since 4.3.0
 * @param array  $ref_array The array to search and replace the value in.
 * @param string $search The value to search for in the array.
 * @param string $replace The value to replace the search value with.
 * @return array The updated array with the search value replaced by the replace value.
 */
function flowmattic_recursive_array_replace( $ref_array, $search, $replace ) {
	$new_array = array();
	foreach ( $ref_array as $key => $value ) {
		if ( is_array( $value ) ) {
			$new_array[ $key ] = flowmattic_recursive_array_replace( $value, $search, $replace );
		} else {
			$replace       = ( $replace ) ? $replace : '';
			$replace       = ( is_array( $replace ) ) ? wp_json_encode( $replace ) : $replace;
			$updated_value = str_replace( $search, $replace, $value );

			// Convert the string to JSON format if it contains JSON string.
			$updated_value = flowmattic_convert_strings_to_json( $updated_value );

			$new_array[ $key ] = $updated_value;
		}
	}

	return $new_array;
}

/**
 * Function to retrieve user data by ID.
 *
 * @access public
 * @since 4.3.0
 * @param int $user_id The ID of the user to retrieve data for.
 * @return array
 */
function flowmattic_get_simple_userdata( $user_id ) {
	$user_id   = is_email( $user_id ) ? email_exists( $user_id ) : $user_id;
	$user_data = get_userdata( $user_id );

	// Check if the user data is empty.
	if ( empty( $user_data ) ) {
		return array();
	}

	$simple_data = array(
		'user_id'           => $user_data->ID,
		'user_login'        => $user_data->user_login,
		'user_email'        => $user_data->user_email,
		'user_display_name' => $user_data->display_name,
		'user_first_name'   => $user_data->first_name,
		'user_last_name'    => $user_data->last_name,
		'user_url'          => $user_data->user_url,
		'user_roles'        => $user_data->roles,
	);

	return $simple_data;
}

/**
 * Function to handle ajax to get workflow suggestions.
 *
 * @access public
 * @since 4.3.0
 * @return array
 */
function flowmattic_generate_workflow_assistance() {
	if ( ! defined( 'REST_REQUEST' ) ) {
		if ( ! wp_verify_nonce( $_POST['workflow_nonce'], 'workflow_nonce' ) ) {
			$response = array(
				'status'  => 'error',
				'message' => esc_html__( 'Authentication failed!', 'flowmattic' ),
			);

			return new WP_REST_Response( $response, 403 );
		}
	}

	// Get the prompt.
	$prompt = isset( $_POST['prompt'] ) ? sanitize_text_field( wp_unslash( $_POST['prompt'] ) ) : '';

	$args = array(
		'headers' => array(
			'X-User-Agent' => 'FlowMattic/' . FLOWMATTIC_VERSION,
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		),
		'timeout' => 600,
	);

	// Get the workflow suggestion.
	$request  = wp_remote_get( 'https://workflows.flowmattic.ai/generate?prompt=' . $prompt, $args );
	$response = wp_remote_retrieve_body( $request );

	// Validate the JSON response. If the JSON starts with {, but does not end with }, it is invalid, so add a closing bracket.
	$response = flowmattic_validate_json( $response );

	// Decode the response.
	$workflow_suggestion = json_decode( $response );

	if ( ! isset( $workflow_suggestion->workflow_name ) ) {
		$response = array(
			'status'  => 'error',
			'message' => esc_html__( 'Failed to generate workflow suggestion!', 'flowmattic' ),
		);

		wp_send_json( $response );
	}

	// Get the workflow steps.
	$workflow_steps = isset( $workflow_suggestion->workflow_steps ) ? $workflow_suggestion->workflow_steps : array();

	// Prepare the new workflow.
	$new_workflow = array(
		'workflow_name'     => $workflow_suggestion->workflow_name,
		'workflow_settings' => array(
			// Set the workflow settings.
			'folder'            => 'default',
			'description'       => 'AI Generated Workflow for the prompt - ' . $prompt,
			'status'            => 'off',
			'user_email'        => get_option( 'admin_email' ),
			'webhook_queue'     => 'disabled',
			'workflow_auth_key' => '',
			'time'              => date_i18n( 'd-m-Y h:i:s A' ),
			'capturedResponses' => '',
		),
		'workflow_steps'    => array(),
	);

	// Prepare the new workflow steps.
	foreach ( $workflow_steps as $key => $step ) {
		$step     = (array) $step;
		$step_id  = flowmattic_random_string( 5 ) . '-' . flowmattic_random_string( 16 );
		$new_step = $step;

		// Set the step ID.
		$new_step['stepID']    = $step_id;
		$new_step['stepIndex'] = $key + 1;

		// Add the new step to the workflow.
		$new_workflow['workflow_steps'][] = $new_step;
	}

	// Return the suggestions.
	$suggestion_with_html = array(
		'workflow_suggestion' => $new_workflow,
		'workflow_preview'    => flowmattic_generate_workflow_preview( $new_workflow ),
	);

	// Return the suggestions.
	wp_send_json( $suggestion_with_html );
}

/**
 * Function to generate workflow preview.
 *
 * @access public
 * @since 4.3.0
 * @param array $workflow The workflow to generate the preview for.
 * @return string
 */
function flowmattic_generate_workflow_preview( $workflow ) {
	if ( isset( $workflow['workflow_steps'] ) && empty( $workflow['workflow_steps'] ) ) {
		return '';
	}

	$workflow_steps    = $workflow['workflow_steps'];
	$flowmattic_apps   = wp_flowmattic()->apps;
	$all_applications  = $flowmattic_apps->get_all_applications();
	$applications_used = array();

	$workflow_preview = '<div class="flowmattic-workflow-preview flowmattic-wrap">';

	$workflow_steps_preview = '';
	foreach ( $workflow_steps as $key => $step ) {
		$step_title       = isset( $step['stepTitle'] ) ? $step['stepTitle'] : '';
		$application_icon = isset( $all_applications[ $step['application'] ] ) ? $all_applications[ $step['application'] ]['icon'] : '';
		$application_name = isset( $all_applications[ $step['application'] ] ) ? $all_applications[ $step['application'] ]['name'] : ucwords( $step['application'] ) . ' (Not available)';
		$step_type        = isset( $step['type'] ) ? $step['type'] : '';

		if ( $key < count( $workflow_steps ) - 1 ) {
			$applications_used[] = '<div class="workflow-image text-center me-2" style="width: 30px;height: 30px;display: flex;align-items: center;flex-direction: column;justify-content: center;border: 1px solid #ddd;border-radius: 2px;" data-toggle="tooltip" title="' . $application_name . '"><img src="' . $application_icon . '"></div>
								<span class="svg-icon svg-icon--step-arrow me-2"><svg width="10" viewBox="0 0 512 512"><path d="M71 455c0 35 39 55 67 35l285-199c24-17 24-53 0-70L138 22c-28-20-67 0-67 35z"></path></svg></span>';
		} else {
			$applications_used[] = '<div class="workflow-image text-center me-2" style="width: 30px;height: 30px;display: flex;align-items: center;flex-direction: column;justify-content: center;border: 1px solid #ddd;border-radius: 2px;margin-right: 7px;" data-toggle="tooltip" title="' . $application_name . '"><img src="' . $application_icon . '"></div>';
		}

		$workflow_steps_preview .= '<div class="fm-workflow-' . $step_type . ' flowmattic-' . $step_type . '-step fm-workflow-step">';
		$workflow_steps_preview .= '<div class="fm-workflow-step-header">';
		$workflow_steps_preview .= '<div class="fm-workflow-icon">';
		$workflow_steps_preview .= '<img src="' . esc_url( $application_icon ) . '" alt="' . esc_attr( $step['application'] ) . '" style="width: 32px;">';
		$workflow_steps_preview .= '</div>';
		$workflow_steps_preview .= '<div class="fm-workflow-step-info">';

		if ( 'trigger' === $step_type ) {
			$workflow_steps_preview .= '<span class="fm-workflow-hint-label">Trigger: When this happens...</span>';
		} else {
			$workflow_steps_preview .= '<span class="fm-workflow-hint-label">Action: Do this...</span>';
		}

		$workflow_steps_preview .= '<h6 class="fm-workflow-step-application-title"><strong>' . ( $key + 1 ) . '. ' . esc_html( $application_name ) . ':</strong> ' . esc_html( $step_title ) . '</h6>';

		$workflow_steps_preview .= '</div>';
		$workflow_steps_preview .= '</div>';
		$workflow_steps_preview .= '</div>';

		// Add the step separator, if not the last step.
		if ( $key < count( $workflow_steps ) - 1 ) {
			$workflow_steps_preview .= '<div class="fm-workflow-add-step">
				<a href="javascript:void(0)" class="fm-add-step fm-add-trigger" data-toggle="tooltip" title="" data-original-title="Add New Action">
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
				</a>
			</div>';
		}
	}

	$workflow_preview .= '<div class="card-header py-3 bg-white d-flex align-items-center">';
	$workflow_preview .= '<div class="workflow-applications d-flex align-items-center position-relative me-2">';
	$workflow_preview .= implode( '', $applications_used );
	$workflow_preview .= '</div>';
	$workflow_preview .= '<h6 class="m-0">' . esc_html( $workflow['workflow_name'] ) . '</h6>';
	$workflow_preview .= '</div>';
	$workflow_preview .= '<div class="d-flex card-body bg-white flex-wrap">';
	$workflow_preview .= '<div class="fm-workflow-steps w-100 mb-0">';
	$workflow_preview .= $workflow_steps_preview;
	$workflow_preview .= '</div>';
	$workflow_preview .= '</div>';
	$workflow_preview .= '</div>';

	return $workflow_preview;
}

/**
 * Function to execute seconds delay.
 *
 * @access public
 * @since 4.3.1
 * @param int $seconds Number of seconds.
 * @return String.
 */
function flowmattic_delay( $seconds ) {
	// Ensure $seconds is a positive integer.
	$seconds = absint( $seconds );

	// Start a timer to delay the response without using sleep().
	$start_time = microtime( true );
	while ( ( microtime( true ) - $start_time ) < $seconds ) { // @codingStandardsIgnoreLine
		// Busy wait loop.
	}

	$response_data = array(
		'message' => sprintf( 'Response delayed by %d seconds.', $seconds ),
	);

	return wp_json_encode( $response_data );
}

/**
 * Register the REST API routes.
 *
 * @access public
 * @since 4.3.0
 * @return void
 */
function flowmattic_register_rest_routes() {
	// Register custom route for workflow assistance.
	register_rest_route(
		'flowmattic/v1',
		'/workflow-assistance',
		array(
			'methods'             => 'POST',
			'callback'            => 'flowmattic_generate_workflow_assistance',
			'permission_callback' => '__return_true',
		)
	);
}

add_action( 'rest_api_init', 'flowmattic_register_rest_routes' );

/**
 * Fixes common issues in a JSON string and validates it.
 *
 * @access public
 * @since 4.3.0
 * @param string $json The JSON string to be fixed.
 * @return string|bool The fixed JSON string, or false if it cannot be fixed.
 */
function flowmattic_validate_json( $json ) {
	// Check if the JSON is valid.
	if ( json_decode( $json ) ) {
		return $json;
	}

	// Explode the JSON string by {.
	$test = explode( '{', $json );

	// Check if the last item in the array is double quote.
	if ( '"' !== substr( end( $test ), -1 ) ) {
		$json  = substr( $json, 0, -1 );
		$json .= '"}]}';
	}

	// Check if the JSON is valid.
	if ( ! json_decode( $json ) ) {
		// Check if the last item in the array is "}.
		if ( '"}' !== substr( end( $test ), -2 ) ) {
			$json  = substr( $json, 0, -2 );
			$json .= '"}]}';
		}
	}

	return $json;
}
