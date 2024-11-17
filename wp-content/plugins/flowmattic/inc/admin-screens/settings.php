<?php FlowMattic_Admin::loader(); ?>
<div class="wrap flowmattic-wrap about-wrap">
	<div class="flowmattic-wrapper d-flex">
		<?php FlowMattic_Admin::header(); ?>
		<div class="flowmattic-dashboard-content container m-0 ps-3">
			<div class="row">
				<?php
				$settings            = get_option( 'flowmattic_settings', array() );
				$integrations_access = isset( $settings['integration_page_access'] ) ? $settings['integration_page_access'] : 'yes';
				$delete_app_access   = isset( $settings['delete_app_access'] ) ? $settings['delete_app_access'] : 'yes';
				$webhook_url_base    = isset( $settings['webhook_url_base'] ) ? $settings['webhook_url_base'] : 'regular';

				// Set the default values for connect settings.
				$settings['enable_notifications_connect'] = isset( $settings['enable_notifications_connect'] ) ? $settings['enable_notifications_connect'] : 'yes';
				$settings['notification_email_connect']   = isset( $settings['notification_email_connect'] ) ? $settings['notification_email_connect'] : $settings['notification_email'];
				?>
				<div class="flowmattic-container flowmattic-dashboard m-0">
					<div class="row flex-row-reverse flex-xl-row-reverse flex-sm-column">
						<div class="col-sm-12 col-xl-12 fm-settings-list ps-4 pe-4">
							<div class="fm-setting-header d-flex mt-4 justify-content-between">
								<h3 class="fm-setting-heading m-0">
									<?php echo esc_attr__( 'Settings', 'flowmattic' ); ?>
								</h3>
								<div class="header-actions">
									<?php
									$license_key = get_option( 'flowmattic_license_key', '' );

									$button_type  = '';
									$button_class = '';
									$button_url   = admin_url( '/admin.php?page=flowmattic-workflows&flowmattic-action=new&nonce=' . wp_create_nonce( 'flowmattic-workflow-new' ) );

									if ( '' === $license_key ) {
										$button_type  = 'disabled';
										$button_url   = 'javascript:void(0)';
										$button_class = 'needs-registration';
									}
									?>
									<a href="<?php echo $button_url; ?>" <?php echo $button_type; ?>  class="btn btn-md btn-primary d-inline-flex align-items-center justify-content-center <?php echo $button_class; ?>">
										<span class="dashicons dashicons-plus-alt2 d-inline-block pe-3 ps-0 me-1"></span>
										<?php echo esc_attr__( 'New Workflow', 'flowmattic' ); ?>
									</a>
								</div>
							</div>
							<div class="fm-settings container p-0 mt-4">
								<div class="row m-0">
									<form class="p-0 flowmattic-settings-form">
										<div class="mb-4 bg-light p-4">
											<div class="form-input-col">
												<strong><label class="form-label"><?php esc_html_e( 'Notification of Failed Tasks', 'flowmattic' ); ?></label></strong>
											</div>
											<div class="d-flex">
												<div class="form-input-col pt-2 pe-5 col-6">
													<label for="notification_status" class="form-label"><?php esc_html_e( 'Enable Notifications', 'flowmattic' ); ?></label>
													<select class="form-select mw-100 w-100" aria-label="Enable notifications" name="enable_notifications">
														<option value="yes" <?php echo ( ( isset( $settings['enable_notifications'] ) && 'yes' === $settings['enable_notifications'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Yes', 'flowmattic' ); ?></option>
														<option value="no" <?php echo ( ( isset( $settings['enable_notifications'] ) && 'no' === $settings['enable_notifications'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'No', 'flowmattic' ); ?></option>
													</select>
													<div id="enable_notificationsHelp" class="form-text"><?php esc_html_e( 'To receive notification of failed task, this email will be used.', 'flowmattic' ); ?></div>
												</div>
												<div class="form-input-col pt-2 col-6">
													<label for="notification_email" class="form-label"><?php esc_html_e( 'Notification Email', 'flowmattic' ); ?></label>
													<input type="email" class="form-control" id="notification_email" name="notification_email" value="<?php echo ( isset( $settings['notification_email'] ) ? $settings['notification_email'] : '' ); ?>" aria-describedby="notification_emailHelp">
													<div id="notification_emailHelp" class="form-text"><?php esc_html_e( 'To receive notification of failed task, this email will be used.', 'flowmattic' ); ?></div>
												</div>
											</div>
										</div>
										<div class="mb-4 bg-light p-4">
											<div class="form-input-col">
												<strong><label class="form-label"><?php esc_html_e( 'Notification of Authentication Expiry', 'flowmattic' ); ?></label></strong>
											</div>
											<div class="d-flex">
												<div class="form-input-col pt-2 pe-5 col-6">
													<label for="notification_status" class="form-label"><?php esc_html_e( 'Enable Notifications', 'flowmattic' ); ?></label>
													<select class="form-select mw-100 w-100" aria-label="Enable notifications" name="enable_notifications_connect">
														<option value="yes" <?php echo ( ( isset( $settings['enable_notifications_connect'] ) && 'yes' === $settings['enable_notifications_connect'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Yes', 'flowmattic' ); ?></option>
														<option value="no" <?php echo ( ( isset( $settings['enable_notifications_connect'] ) && 'no' === $settings['enable_notifications_connect'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'No', 'flowmattic' ); ?></option>
													</select>
													<div id="enable_notifications_connectHelp" class="form-text"><?php esc_html_e( 'To receive notification of authentication expiry in Connects, this email will be used.', 'flowmattic' ); ?></div>
												</div>
												<div class="form-input-col pt-2 col-6">
													<label for="notification_email_connect" class="form-label"><?php esc_html_e( 'Notification Email', 'flowmattic' ); ?></label>
													<input type="email" class="form-control" id="notification_email_connect" name="notification_email_connect" value="<?php echo ( isset( $settings['notification_email_connect'] ) ? $settings['notification_email_connect'] : '' ); ?>" aria-describedby="notification_email_connectHelp">
													<div id="notification_email_connectHelp" class="form-text"><?php esc_html_e( 'To receive notification of failed task, this email will be used.', 'flowmattic' ); ?></div>
												</div>
											</div>
										</div>
										<div class="mb-4 bg-light p-4">
											<div class="d-flex">
												<div class="form-input-col pt-2 pe-5 col-6">
													<strong><label for="task_clean_interval" class="form-label">Task Clean Interval</label></strong>
													<input type="number" class="form-control" id="task_clean_interval" name="task_clean_interval" value="<?php echo ( isset( $settings['task_clean_interval'] ) ? $settings['task_clean_interval'] : '90' ); ?>" aria-describedby="taskHistoryHelp">
													<div id="taskHistoryHelp" class="form-text"><?php esc_html_e( 'Enter the interval in days to clear the old task history. Eg. 30 to remove the task history older than 30 days.', 'flowmattic' ); ?></div>
												</div>
												<div class="form-input-col pt-2 col-6">
													<strong><label for="integration_page_access" class="form-label"><?php esc_html_e( 'Allow Integration Page Access', 'flowmattic' ); ?></label></strong>
													<select class="form-select mw-100 w-100" aria-label="Allow Integration Page Access" name="integration_page_access">
														<option value="yes" <?php echo ( ( 'yes' === $integrations_access ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Yes', 'flowmattic' ); ?></option>
														<option value="no" <?php echo ( ( 'no' === $integrations_access ) ? 'selected' : '' ); ?>><?php esc_html_e( 'No', 'flowmattic' ); ?></option>
													</select>
													<div id="integration_page_accessHelp" class="form-text"><?php esc_html_e( 'Control the access of Integrations page for the users with FlowMattic Workflow Manager roles.', 'flowmattic' ); ?></div>
												</div>
											</div>
											<div class="d-flex mt-3">
												<div class="form-input-col pt-2 pe-5 col-6">
													<strong><label for="delete_app_access" class="form-label"><?php esc_html_e( 'Allow Integration Deletion', 'flowmattic' ); ?></label></strong>
													<select class="form-select mw-100 w-100" aria-label="Allow Integration Deletion" name="delete_app_access">
														<option value="yes" <?php echo ( ( 'yes' === $delete_app_access ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Yes', 'flowmattic' ); ?></option>
														<option value="no" <?php echo ( ( 'no' === $delete_app_access ) ? 'selected' : '' ); ?>><?php esc_html_e( 'No', 'flowmattic' ); ?></option>
													</select>
													<div id="delete_app_accessHelp" class="form-text"><?php esc_html_e( 'Control the access of Uninstalling Integrations for the users with FlowMattic Workflow Manager roles.', 'flowmattic' ); ?></div>
												</div>
												<div class="form-input-col pt-2 pe-5 col-6">
													<strong><label for="webhook_url_base" class="form-label"><?php esc_html_e( 'Webhook URL Base', 'flowmattic' ); ?></label></strong>
													<select class="form-select mw-100 w-100" aria-label="Allow Integration Deletion" name="webhook_url_base">
														<option value="regular" <?php echo ( ( 'regular' === $webhook_url_base ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Regular', 'flowmattic' ); ?></option>
														<option value="rest_api" <?php echo ( ( 'rest_api' === $webhook_url_base ) ? 'selected' : '' ); ?>><?php esc_html_e( 'REST API', 'flowmattic' ); ?></option>
													</select>
													<div id="webhook_url_baseHelp" class="form-text"><?php esc_html_e( 'Control the URL base for the webhook. Regular works with permalinks, and REST API base works with WordPress REST API. Use REST API base when you have issues with permalink based webhooks.', 'flowmattic' ); ?></div>
												</div>
											</div>
										</div>
										<div class="mb-4 bg-light p-4">
											<div class="form-input-col">
												<strong><label class="form-label"><?php esc_html_e( 'Disable Core Integrations', 'flowmattic' ); ?></label></strong>
												<div id="disable_appsHelp" class="form-text mb-2"><?php esc_html_e( 'Disable the Core Integrations that you are not using to keep the workflow editor clean.', 'flowmattic' ); ?></div>
											</div>
											<div class="d-flex flex-wrap">
												<?php
												$flowmattic_apps        = wp_flowmattic()->apps;
												$installed_applications = $flowmattic_apps->get_all_applications();

												foreach ( $installed_applications as $app => $app_settings ) {
													if ( strtolower( 'WordPress' ) === $app ) {
														$app_settings['base'] = 'core';
													}

													if ( ! isset( $app_settings['base'] ) ) {
														continue;
													}

													$checked = ( isset( $settings[ 'disable-app-' . $app ] ) ) ? checked( $settings[ 'disable-app-' . $app ], $app, false ) : '';
													?>
													<div class="form-input-col pt-2 col-3">
														<div class="card core-app p-1 me-3 mb-2 mt-0 core-<?php echo esc_attr( $app ); ?> overflow-hidden flex-row align-items-center justify-content-between">
															<label class="form-check-label d-flex align-items-center" for="flexSwitchCheckChecked-<?php echo esc_attr( $app ); ?>">
																<img src="<?php echo esc_url( $app_settings['icon'] ); ?>" class="card-img-top" alt="<?php echo esc_attr( $app_settings['name'] ); ?>" style="width: 28px; height: 28px;">
																<h5 class="card-title integration-title m-0 fs-6 p-2 w-100 text-left"><?php echo esc_attr( str_replace( 'by FlowMattic', '', $app_settings['name'] ) ); ?></h5>
															</label>
															<div class="form-check form-switch">
																<input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckChecked-<?php echo esc_attr( $app ); ?>" name="disable-app-<?php echo esc_attr( $app ); ?>" value="<?php echo esc_attr( $app ); ?>" <?php echo $checked; ?> style="margin-top: 0.25em;background-repeat: no-repeat;">
															</div>
														</div>
													</div>
													<?php
												}
												?>
											</div>
										</div>
										<button type="button" class="btn btn-primary flowmattic-save-settings"><?php esc_html_e( 'Save Settings', 'flowmattic' ); ?></button>
									</form>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php FlowMattic_Admin::footer(); ?>
