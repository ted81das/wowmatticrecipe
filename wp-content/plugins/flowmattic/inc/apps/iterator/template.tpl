<?php
/**
 * Underscore.js template
 *
 * @package FlowMattic
 * @since 1.1
 */
?>
<script type="text/html" id="flowmattic-application-iterator-data-template">
	<div class="flowmattic-iterator-form-data">
		<div class="form-group w-100 fm-iterator-trigger-iterator-unit">
			<h4 class="fm-input-title"><?php esc_attr_e( 'Iteration Array', 'flowmattic' ); ?> <span class="badge outline bg-danger">Required</span></h4>
			<div class="fm-dynamic-input-field">
				<textarea class="w-100 fm-dynamic-inputs fm-textarea form-control dynamic-field-input" name="iteratorArray" rows="1" required><# if ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.iteratorArray ) { #>{{{ actionAppArgs.iteratorArray }}}<# } #></textarea>
				<span class="dynamic-field-button dashicons dashicons-database" title="Replace with captured data"></span>
			</div>
		</div>
		<div class="form-group dynamic-inputs w-100">
			<div class="fm-input-wrap">
				<h4 class="fm-input-title"><strong><?php esc_attr_e( 'Simple Response', 'flowmattic' ); ?></strong></h4>
				<div class="form-check form-switch">
					<#
					var simpleResponse = ( 'undefined' !== typeof actionAppArgs && 'undefined' !== typeof actionAppArgs.iterator_simple_response ) ? actionAppArgs.iterator_simple_response : 'Yes';
					#>
					<input class="form-check-input form-control me-2" type="checkbox" name="iterator_simple_response" id="fm-iterator-simple-response" <# if ( 'undefined' === typeof simpleResponse || 'Yes' === simpleResponse ) { #>checked<# } #> style="width:2em;margin-top: 0.25em;background-repeat: no-repeat;">
					<label for="fm-iterator-simple-response"><?php esc_attr_e( 'Retrieve the data in simple format', 'flowmattic' ); ?></label>
				</div>
			</div>
		</div>
	</div>
</script>
