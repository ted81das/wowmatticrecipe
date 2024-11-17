<?php
/**
 * Underscore.js template
 *
 * @package FlowMattic
 * @since 4.0
 */
?>
<script type="text/html" id="flowmattic-application-tools-action-data-template">
	<div class="tools-action-data"></div>
</script>
<script type="text/html" id="flowmattic-tools-action-get_variable_value-template">
	<div class="tools-action-data">
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Variable Name', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<input type="text" class="form-control dynamic-field-input w-100" name="variable_name" autocomplete="off" required type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.variable_name ) { #>{{{ actionAppArgs.variable_name }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter the variable name you want to pull the value of. Make sure it is not wrapped in curly braces. eg. my_custom_app', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
	</div>
</script>
<script type="text/html" id="flowmattic-tools-action-set_variable_value-template">
	<div class="tools-action-data">
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Variable Name', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<input type="text" class="form-control dynamic-field-input w-100" name="variable_name" autocomplete="off" required type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.variable_name ) { #>{{{ actionAppArgs.variable_name }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter the variable name you want to pull the value of. Make sure it is not wrapped in curly braces. eg. my_custom_app', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Variable Value', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<input type="text" class="form-control dynamic-field-input w-100" name="variable_value" autocomplete="off" required type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.variable_value ) { #>{{{ actionAppArgs.variable_value }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'The new value for the variable.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
	</div>
</script>
<script type="text/html" id="flowmattic-tools-action-turn_on_workflow-template">
	<div class="tools-action-data">
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Workflow ID', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<input type="text" class="form-control dynamic-field-input w-100" name="workflow_id" autocomplete="off" required type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.workflow_id ) { #>{{{ actionAppArgs.workflow_id }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter the workflow ID you want to turn on. You can find the workflow ID in the URL when you are editing the workflow.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
	</div>
</script>
<script type="text/html" id="flowmattic-tools-action-turn_off_workflow-template">
	<div class="tools-action-data">
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Workflow ID', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<input type="text" class="form-control dynamic-field-input w-100" name="workflow_id" autocomplete="off" required type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.workflow_id ) { #>{{{ actionAppArgs.workflow_id }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter the workflow ID you want to turn off. You can find the workflow ID in the URL when you are editing the workflow.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
	</div>
</script>
<script type="text/html" id="flowmattic-tools-action-get_workflow_status-template">
	<div class="tools-action-data">
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Workflow ID', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<input type="text" class="form-control dynamic-field-input w-100" name="workflow_id" autocomplete="off" required type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.workflow_id ) { #>{{{ actionAppArgs.workflow_id }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter the workflow ID you want to get the status of. You can find the workflow ID in the URL when you are editing the workflow.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
	</div>
</script>
<script type="text/html" id="flowmattic-tools-action-redirect-template">
	<div class="tools-action-data">
		<div class="form-group w-100">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Redirect URL', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<input type="text" class="form-control dynamic-field-input w-100" name="redirect_url" autocomplete="off" required type="search" value="<# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.redirect_url ) { #>{{{ actionAppArgs.redirect_url }}}<# } #>">
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
			<div class="fm-application-instructions">
				<p>
					<?php echo esc_attr__( 'Enter the URL you want to redirect to.', 'flowmattic' ); ?>
				</p>
			</div>
		</div>
		<div class="alert alert-warning" role="alert">
			<?php echo esc_attr__( 'Note: Redirect only works if the trigger app allows. Better to use at the end of the workflow.', 'flowmattic' ); ?>
		</div>
	</div>
</script>