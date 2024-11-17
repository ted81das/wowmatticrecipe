<?php
/**
 * Underscore.js template
 *
 * @package FlowMattic
 * @since 1.1
 */
?>
<script type="text/html" id="flowmattic-application-email-data-template">
	<div class="flowmattic-email-action-data">
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Email Provider', 'flowmattic' ); ?></h4>
			<div class="fm-dynamic-input-field">
				<select id="fm-select-email-provider" class="form-control fm-select-box w-100" name="email_provider" required>
					<option value="wp" <# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.email_provider && 'wp' === actionAppArgs.email_provider ) { #>selected<# } #>>WP Default</option>
					<option value="flowmattic" <# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.email_provider && 'flowmattic' === actionAppArgs.email_provider ) { #>selected<# } #>>FlowMattic Default</option>
					<option value="smtp" <# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.email_provider && 'smtp' === actionAppArgs.email_provider ) { #>selected<# } #>>Custom SMTP</option>
				</select>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Choose how you want to send the email. WP default does not report errors.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'From Name', 'flowmattic' ); ?></h4>
			<div class="fm-dynamic-input-field">
				<input class="form-control dynamic-field-input w-100" name="from_name" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.from_name ) { #>{{{ actionAppArgs.from_name }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter email sender name.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'From Email', 'flowmattic' ); ?></h4>
			<div class="fm-dynamic-input-field">
				<input class="form-control dynamic-field-input w-100" name="from_email" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.from_email ) { #>{{{ actionAppArgs.from_email }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Email will be sent from this email. If you\'re using SMTP with WP Default, this setting won\'t work, as WP Default configuration with SMTP sends only from the email configured in SMTP settings.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Reply-to Email', 'flowmattic' ); ?></h4>
			<div class="fm-dynamic-input-field">
				<input class="form-control dynamic-field-input w-100" name="reply_to_email" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.reply_to_email ) { #>{{{ actionAppArgs.reply_to_email }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter email to set as reply address. Make sure it is not same as the from email. If it is same, skip this field. WP Default will ignore this setting if using with SMTP plugins.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'To Email', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<input class="form-control dynamic-field-input w-100" required name="to_email" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.to_email ) { #>{{{ actionAppArgs.to_email }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter recipient\'s email address.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'CC Email', 'flowmattic' ); ?></h4>
			<div class="fm-dynamic-input-field">
				<input class="form-control dynamic-field-input w-100" name="cc_email" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.cc_email ) { #>{{{ actionAppArgs.cc_email }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter recipient\'s email addresses set to be in CC. You can add multiple emails separated by comma. Eg. email1@domain.com, email2@domain.com', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'BCC Email', 'flowmattic' ); ?></h4>
			<div class="fm-dynamic-input-field">
				<input class="form-control dynamic-field-input w-100" name="bcc_email" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.bcc_email ) { #>{{{ actionAppArgs.bcc_email }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter recipient\'s email addresses set to be in BCC. You can add multiple emails separated by comma. Eg. email1@domain.com, email2@domain.com', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Email Subject', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<input class="form-control dynamic-field-input w-100" required name="email_subject" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.email_subject ) { #>{{{ actionAppArgs.email_subject }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter your email subject.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Email Body', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<div class="flowmattic-content-editor"></div>
				<div class="d-none">
					<textarea class="fm-textarea form-control content-editor-input w-100" required rows="4" name="email_body"><# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.email_body ) { #>{{{ actionAppArgs.email_body }}}<# } #></textarea>
				</div>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'HTML or text for the email content.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group dynamic-inputs w-100">
			<h4 class="input-title"><?php echo esc_attr__( 'Attachments', 'flowmattic' ); ?></h4>
			<div class="fm-custom-fields-body data-dynamic-fields m-t-20" data-field-name="attachments">
				<#
				if( 'undefined' !== typeof attachments ) {
					_.each( attachments, function( value, key ) {
						#>
						<div class="fm-dynamic-input-wrap fm-custom-fields">
							<div class="fm-dynamic-input-field">
								<input class="fm-dynamic-inputs w-100" name="dynamic-field-key[]" type="text" placeholder="File Name" value="{{{key}}}" />
							</div>
							<div class="fm-dynamic-input-field">
								<textarea class="fm-textarea fm-dynamic-inputs dynamic-field-input w-100" name="dynamic-field-value[]" rows="1" placeholder="File URL">{{{value}}}</textarea>
								<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
							</div>
							<a href="javascript:void(0);" class="dynamic-input-remove btn-remove-parameter">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="#333333" xmlns="http://www.w3.org/2000/svg" data-reactroot="">
									<path stroke-linejoin="round" stroke-linecap="round" stroke-miterlimit="10" stroke-width="1" stroke="#333333" fill="none" d="M20 22H4C2.9 22 2 21.1 2 20V4C2 2.9 2.9 2 4 2H20C21.1 2 22 2.9 22 4V20C22 21.1 21.1 22 20 22Z"></path>
									<path stroke-linejoin="round" stroke-linecap="round" stroke-miterlimit="10" stroke-width="1" stroke="#333333" d="M6 6L18 18"></path>
									<path stroke-linejoin="round" stroke-linecap="round" stroke-miterlimit="10" stroke-width="1" stroke="#333333" d="M18 6L6 18"></path>
								</svg>
							</a>
						</div>
						<#
					} );
				} else {
					#>
					<div class="fm-dynamic-input-wrap fm-custom-fields">
						<div class="fm-dynamic-input-field">
							<input class="fm-dynamic-inputs w-100" autocomplete="off" name="dynamic-field-key[]" type="text" placeholder="File Name" value="" />
						</div>
						<div class="fm-dynamic-input-field">
							<textarea class="fm-textarea fm-dynamic-inputs dynamic-field-input w-100" name="dynamic-field-value[]" rows="1" placeholder="File URL"></textarea>
							<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
						</div>
						<a href="javascript:void(0);" class="dynamic-input-remove btn-remove-parameter">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="#333333" xmlns="http://www.w3.org/2000/svg" data-reactroot="">
								<path stroke-linejoin="round" stroke-linecap="round" stroke-miterlimit="10" stroke-width="1" stroke="#333333" fill="none" d="M20 22H4C2.9 22 2 21.1 2 20V4C2 2.9 2.9 2 4 2H20C21.1 2 22 2.9 22 4V20C22 21.1 21.1 22 20 22Z"></path>
								<path stroke-linejoin="round" stroke-linecap="round" stroke-miterlimit="10" stroke-width="1" stroke="#333333" d="M6 6L18 18"></path>
								<path stroke-linejoin="round" stroke-linecap="round" stroke-miterlimit="10" stroke-width="1" stroke="#333333" d="M18 6L6 18"></path>
							</svg>
						</a>
					</div>
					<#
				}
				#>
				<div class="dynamic-input-add-more fm-api-parameters-add-more">
					<a href="javascript:void(0);" class="btn flowmattic-button btn-small btn-success btn-add-more-parameters"><?php echo esc_attr__( 'Add More', 'flowmattic' ); ?></a>
				</div>
			</div>
		</div>
		<div class="flowmattic-email-smtp-fields">
		</div>
	</div>
</script>
<script type="text/html" id="flowmattic-application-email-smtp-data-template">
	<div class="form-group w-100">
		<h4 class="fm-input-title"><?php esc_attr_e( 'Host Name', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
		<div class="fm-dynamic-input-field">
			<input class="form-control dynamic-field-input w-100" required name="host_name" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.host_name ) { #>{{{ actionAppArgs.host_name }}}<# } #>">
			<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
		</div>
		<div class="fm-application-instructions">
			<p>
				<?php echo esc_attr__( 'Enter your SMTP host name. eg. smtp.gmail.com', 'flowmattic' ); ?>
			</p>
		</div>
	</div>
	<div class="form-group w-100">
		<h4 class="fm-input-title"><?php esc_attr_e( 'Username', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
		<div class="fm-dynamic-input-field">
			<input class="form-control dynamic-field-input w-100" required name="smtp_username" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.smtp_username ) { #>{{{ actionAppArgs.smtp_username }}}<# } #>">
			<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
		</div>
		<div class="fm-application-instructions">
			<p>
				<?php echo esc_attr__( 'Enter your SMTP username.', 'flowmattic' ); ?>
			</p>
		</div>
	</div>
	<div class="form-group w-100">
		<h4 class="fm-input-title"><?php esc_attr_e( 'Password', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
		<div class="fm-dynamic-input-field">
			<input class="form-control dynamic-field-input w-100" required name="smtp_password" autocomplete="off" type="password" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.smtp_password ) { #>{{{ actionAppArgs.smtp_password }}}<# } #>">
			<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
		</div>
		<div class="fm-application-instructions">
			<p>
				<?php echo esc_attr__( 'Enter your SMTP password.', 'flowmattic' ); ?>
			</p>
		</div>
	</div>
	<div class="form-group w-100">
		<h4 class="fm-input-title"><?php esc_attr_e( 'Encryption Type', 'flowmattic' ); ?></h4>
		<div class="fm-dynamic-input-field">
			<input class="form-control dynamic-field-input w-100" name="encryption_type" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.encryption_type ) { #>{{{ actionAppArgs.encryption_type }}}<# } #>">
			<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
		</div>
		<div class="fm-application-instructions">
			<p>
				<?php echo esc_attr__( 'Enter your SMTP Encryption Type ( TLS / SSL / NONE ). Ex - TLS.', 'flowmattic' ); ?>
			</p>
		</div>
	</div>
	<div class="form-group w-100">
		<h4 class="fm-input-title"><?php esc_attr_e( 'Port Number', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
		<div class="fm-dynamic-input-field">
			<input class="form-control dynamic-field-input w-100" required name="smtp_port" autocomplete="off" type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.smtp_port ) { #>{{{ actionAppArgs.smtp_port }}}<# } #>">
			<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
		</div>
		<div class="fm-application-instructions">
			<p>
				<?php echo esc_attr__( 'Enter your SMTP Port ( 587 / 465 / 2525 / 25 ). Ex - 587', 'flowmattic' ); ?>
			</p>
		</div>
	</div>
</script>
