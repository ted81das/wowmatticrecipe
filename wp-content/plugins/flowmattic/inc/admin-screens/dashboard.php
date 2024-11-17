<?php FlowMattic_Admin::loader(); ?>
<div class="wrap flowmattic-wrap">
	<div class="flowmattic-wrapper d-flex">
		<?php FlowMattic_Admin::header(); ?>
		<?php
		$_user            = wp_get_current_user();
		$users_email      = $_user->user_email;
		$flowmattic_apps  = wp_flowmattic()->apps;
		$all_applications = $flowmattic_apps->get_all_applications();

		if ( current_user_can( 'manage_options' ) ) {
			$all_workflows = wp_flowmattic()->workflows_db->get_all();
		} else {
			$all_workflows = wp_flowmattic()->workflows_db->get_user_workflows( $users_email );
		}

		$task_count = wp_flowmattic()->tasks_db->get_tasks_count();
		$settings   = get_option( 'flowmattic_settings', array() );

		// Get all custom app types.
		$custom_apps = $custom_apps_db = wp_flowmattic()->custom_apps_db->get_all();
		$apps_count  = ( ! empty( $custom_apps ) ) ? count( $custom_apps ) : 0;

		// Get all connects.
		$all_connects   = wp_flowmattic()->connects_db->get_all();
		$connects_count = ( ! empty( $all_connects ) ) ? count( $all_connects ) : 0;

		$license_key      = get_option( 'flowmattic_license_key', '' );
		$license          = wp_flowmattic()->check_license();
		$all_integrations = (array) flowmattic_get_integrations();
		?>
		<div class="flowmattic-dashboard-content container m-0 ps-3">
			<div class="row">
				<div class="flowmattic-content-block col-12 col-lg-8 col-md-12 col-sm-12 pe-2">
					<div class="p-4 mb-4 ms-2 mt-4 rounded-3"  style="background-image: url('<?php echo FLOWMATTIC_PLUGIN_URL; ?>assets/admin/img/vector-stars.png'); background-color: #d5e5fd;background-position: center center;background-repeat: no-repeat;background-size: cover;">
						<div class="container-fluid py-2">
							<div class="row flex-xl-row flex-sm-column">
								<div class="w-100 col-xl-7 col-sm-12 justify-content-end d-flex flex-column justify-content-center">
									<h2 class="fs-2 fw-bold">Welcome to FlowMattic!</h2>
									<p class="fs-6 mt-3">Accelerate productivity with FlowMattic, facilitating limitless workflow automation. Assemble your workflows interruption-free and connect your applications like a seasoned professional!</p>
								</div>
							</div>
						</div>
					</div>
					<div class="ai-workflow-assistance-wrap mb-4 ms-2">
						<div class="card-header py-3 bg-light d-flex">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" height="24" width="24" size="24" name="miscAI"><path fill="#2196F3" fill-rule="evenodd" d="m7.754 8.377 2.261-3.888 2.261 3.888.362.362L16.526 11l-3.888 2.261-.362.362-2.26 3.888-1.784-3.066-1.554 1.304 2.473 4.254h1.729l2.992-5.146 5.146-2.992v-1.73l-5.146-2.992-2.992-5.146H9.15L6.16 7.143l-5.146 2.993v1.729l4.243 2.467 1.628-1.367L3.504 11l3.889-2.26.361-.363Z" clip-rule="evenodd"></path><path fill="#00BCD4" d="m19.516 15.991 1.28 2.203L23 19.474l-2.203 1.282-1.281 2.203-1.281-2.203-2.203-1.281 2.203-1.281 1.28-2.203Z"></path></svg>
							<h5 class="fw-bold m-0 ms-2">
								<?php echo esc_html__( 'What would you like to automate?', 'flowmattic' ); ?>
								<span class="badge bg-warning text-dark ms-2">Beta</span>
							</h5>
						</div>
						<div class="card-body bg-white">
							<div class="d-flex justify-content-between align-items-center">
								<div class="ai-workflow-assistance m-2 w-100 d-flex">
									<textarea class="fm-textarea form-control mb-2 w-100 me-2 workflow-prompt" rows="1" style="height: 42px" placeholder="Describe your workflow.."></textarea>
									<div class="ai-workflow-assistance-action">
										<a href="javascript:void(0);" class="btn btn-outline-primary btn-sm generate-workflow-btn" style="height: 42px;align-items: center;display: flex;width: 60px;text-align: center;justify-content: center;">
											<svg xmlns="http://www.w3.org/2000/svg" fill="none" height="28" viewBox="0 0 32 32" width="28"><g fill="currentColor"><path d="m27.8709 4.42343c.1305.2831.3515.50762.6427.63453l1.2152.52715c.3616.16596.3616.66382 0 .82977l-1.2051.52715c-.2913.12691-.5223.35144-.6428.63454l-.9541 2.16718c-.1707.34165-.6829.34165-.8536 0l-.9541-2.16718c-.1306-.2831-.3515-.50763-.6428-.63454l-1.2051-.52715c-.3616-.16595-.3616-.66381 0-.82977l1.2051-.52715c.2913-.12691.5223-.35143.6428-.63453l.9541-2.16718c.1707-.34167.6829-.34167.8536 0z"/><path d="m15.9834 5.1646c.0936.19469.2497.35045.4578.43806l.8637.36017c.2601.11681.2601.45753 0 .57434l-.8637.36018c-.2081.08761-.3746.24336-.4578.43805l-.6764 1.48938c-.1249.23363-.4891.23363-.614 0l-.6763-1.48938c-.0937-.19469-.2498-.35044-.4579-.43805l-.8637-.36018c-.2601-.11681-.2601-.45753 0-.57434l.8637-.36017c.2081-.08761.3746-.24337.4579-.43806l.6763-1.48938c.1249-.23363.4891-.23363.614 0z"/><path d="m4 26-1.6625 1.6271c-.45.4513-.45 1.1733 0 1.6245l.41.4112c.44.4513 1.17.4513 1.61-.01l1.6425-1.6472-.0028-.0028 14.0028-14.0028 1.6741-1.6673c.4345-.4449.4345-1.2392 0-1.6747l-.3305-.3314c-.444-.43548-1.3436-.3266-1.8436.1734z"/><path d="m25.9507 16.2976c-.2034-.098-.364-.2613-.4603-.4791l-.6853-1.6225c-.1284-.2613-.4925-.2613-.6102 0l-.6853 1.6225c-.0856.2069-.2569.3811-.4603.4791l-.8566.3921c-.2569.1306-.2569.5009 0 .6206l.8566.3921c.2034.098.364.2613.4603.4791l.6853 1.6225c.1284.2613.4925.2613.6102 0l.6853-1.6225c.0856-.2069.2569-.3811.4603-.4791l.8566-.3921c.2569-.1306.2569-.5009 0-.6206z"/><path d="m12 14c.5523 0 1-.4477 1-1s-.4477-1-1-1-1 .4477-1 1 .4477 1 1 1z"/><path d="m30 13c0 .5523-.4477 1-1 1s-1-.4477-1-1 .4477-1 1-1 1 .4477 1 1z"/><path d="m19 4c.5523 0 1-.44771 1-1s-.4477-1-1-1-1 .44771-1 1 .4477 1 1 1z"/><path d="m20 21c0 .5523-.4477 1-1 1s-1-.4477-1-1 .4477-1 1-1 1 .4477 1 1z"/></g></svg>
										</a>
									</div>
								</div>
							</div>
							<p class="ms-2"><?php echo esc_attr__( 'Example: When new order is placed in WooCommerce, add a new row in Google Sheets', 'flowmattic' ); ?></p>
							<p class="ms-2"><?php echo esc_attr__( 'FlowMattic will assist you in creating workflows based on your description. Click the magic button to get started!', 'flowmattic' ); ?></p>
							<p class="ms-2"><?php echo esc_attr__( 'Note: This feature is in beta and may not work as expected in all cases.', 'flowmattic' ); ?></p>
						</div>
					</div>

					<div class="flowmattic-stats ms-2 mb-5">
						<div class="card-header py-3 bg-light">
							<h5 class="fw-bold m-0"><?php echo esc_html__( 'Your Stats', 'flowmattic' ); ?></h5>
						</div>
						<div class="d-flex card-body bg-white pt-0 flex-wrap">
							<div class="d-flex w-100">
								<div class="card w-50 shadow-sm me-2 px-2 pb-0" style="background-image: linear-gradient(160deg,#ffebec 13%,snow 86%);border-color: #fff;">
									<div class="card-body pe-0">
										<div class="row align-items-center gx-0">
											<div class="col">
											<h6 class="text-uppercase text-muted mb-2">
												<?php echo esc_html__( 'Workflows Created', 'flowmattic' ); ?>
											</h6>
											<span class="h2 mb-0 fw-bold">
												<?php echo number_format( count( $all_workflows ) ); ?>
											</span>
											</div>
										</div>
									</div>
								</div>
								<div class="card w-50 shadow-sm ms-2 me-2 px-2 pb-0" style="background-image: linear-gradient(160deg,#edf3ff 13%,#f9fbff 86%);border-color: #fff;">
									<div class="card-body pe-0">
										<div class="row align-items-center gx-0">
											<div class="col">
											<h6 class="text-uppercase text-muted mb-2">
												<?php echo esc_html__( 'Apps Connected', 'flowmattic' ); ?>
											</h6>
											<span class="h2 mb-0 fw-bold">
												<?php echo number_format( $connects_count ); ?>
											</span>
											</div>
										</div>
									</div>
								</div>
								<div class="card w-50 shadow-sm ms-2 px-2 pb-0" style="background-image: linear-gradient(160deg,#fbf4e2 13%,#fffcf3 86%);border-color: #fff;">
									<div class="card-body pe-0">
										<div class="row align-items-center gx-0">
											<div class="col">
											<h6 class="text-uppercase text-muted mb-2">
												<?php echo esc_html__( 'Custom Apps Created', 'flowmattic' ); ?>
											</h6>
											<span class="h2 mb-0 fw-bold">
												<?php echo number_format( $apps_count ); ?>
											</span>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="d-flex w-100">
								<div class="card w-50 shadow-sm ms-0 me-2 px-2 pb-0" style="background-image: linear-gradient(160deg,#ebffee 13%,#f8fff9 86%);border-color: #fff;">
									<div class="card-body pe-0">
										<div class="row align-items-center gx-0">
											<div class="col">
											<h6 class="text-uppercase text-muted mb-2">
												<?php echo esc_html__( 'Tasks Executions', 'flowmattic' ); ?>
											</h6>
											<span class="h2 mb-0 fw-bold">
												<?php echo number_format( $task_count ); ?>
												<span class="fw-light fs-6 ps-1"><?php echo sprintf( esc_attr__( '/ Last %s days', 'flowmattic' ), ( isset( $settings['task_clean_interval'] ) ? $settings['task_clean_interval'] : '90' ) ); ?></span>
											</span>
											</div>
										</div>
									</div>
								</div>
								<div class="card w-50 shadow-sm ms-2 px-2 pb-0" style="background-image: linear-gradient(160deg,#f8eaff 0,rgba(253,248,255,.51) 100%);border-color: #fff;">
									<div class="card-body pe-0">
										<div class="row align-items-center gx-0">
											<div class="col">
											<h6 class="text-uppercase text-muted mb-2">
												<?php echo esc_html__( 'Active Integrations', 'flowmattic' ); ?>
											</h6>
											<span class="h2 mb-0 fw-bold">
												<?php echo number_format( get_option( 'flowmattic_installed_apps' ) ); ?>
											</span>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="workflow-templates mt-4">
							<div class="card-header py-3 bg-light">
								<h5 class="fw-bold m-0"><?php echo esc_html__( 'Workflow Templates', 'flowmattic' ); ?></h5>
							</div>
							<div class="navbar-text bg-light w-100 ps-3 py-3 mt-0 d-flex justify-content-between align-items-center pe-3 card-body">
								<?php esc_html_e( 'Get started with workflow templates in just a click.', 'flowmattic' ); ?>
								<input type="text" id="template-search" placeholder="Search templates...">
							</div>
							<div class="workflow-template-list w-100 card-body bg-white pt-1">
								<?php
								$flowmattic_apps    = wp_flowmattic()->apps;
								$all_applications   = $flowmattic_apps->get_all_applications();
								$workflow_templates = flowmattic_get_workflow_templates();

								if ( is_array( $workflow_templates ) ) {
									// Loop through all the templates.
									foreach ( $workflow_templates as $template_id => $template ) {
										$template_apps = $template['apps'];
										$applications  = array();
										$app_names     = array();
										$popup_apps    = array();
										$count         = count( $template_apps );
										$i             = ( 3 >= $count ) ? 0 : 1;

										foreach ( $template_apps as $key => $template_app ) {
											++$i;
											$app_name = $template_app['app'];
											$find_app = array_search( $app_name, array_column( $all_applications, 'name' ), true );
											$app_data = $all_applications[ array_keys( $all_applications )[ $find_app ] ];
											$app_icon = $template_app['icon'];

											$app_names[] = $app_name;

											if ( 3 >= $i ) {
												$applications[] = '<div class="workflow-image" style="width: 30px; height: 30px;" data-toggle="tooltip" title="' . $app_name . '"><img class="p-0" src="' . $app_icon . '"></div>
																<span class="svg-icon svg-icon--step-arrow"><svg viewBox="0 0 512 512"><path d="M71 455c0 35 39 55 67 35l285-199c24-17 24-53 0-70L138 22c-28-20-67 0-67 35z"></path></svg></span>';
											} else {
												$popup_apps[] = '<div class="workflow-image" style="width: 30px; height: 30px;" data-toggle="tooltip" title="' . $app_name . '"><img class="p-0" src="' . $app_icon . '"></div>
																<span class="svg-icon svg-icon--step-arrow"><svg viewBox="0 0 512 512"><path d="M71 455c0 35 39 55 67 35l285-199c24-17 24-53 0-70L138 22c-28-20-67 0-67 35z"></path></svg></span>';
											}
										}

										// If count is greater than 3, show the remaining numbers.
										if ( 3 < $count ) {
											$applications[] = '<div class="workflow-image d-flex align-items-center justify-content-center fm-workflow-popup-trigger" data-toggle="tooltip" title="' . esc_html__( 'Click to expand', 'flowmattic' ) . '" style="width: 30px; height: 30px;">+' . ( $count - 2 ) . '</div>';
										}
										?>
										<div class="card p-0 border-0 mw-100 flowmattic-recent-workflow-item d-none">
											<div class="card-body bg-light">
												<div class="d-flex justify-content-between align-items-center">
													<div class="workflow-template-item d-flex align-items-center">
														<div class="workflow-applications d-flex align-items-center position-relative m-0" style="width: 170px;">
															<?php echo implode( '', $applications ); ?>
															<?php
															if ( ! empty( $popup_apps ) ) {
																echo '<div class="fm-workflow-apps-popup"><span class="svg-icon svg-icon--step-arrow"><svg viewBox="0 0 512 512"><path d="M71 455c0 35 39 55 67 35l285-199c24-17 24-53 0-70L138 22c-28-20-67 0-67 35z"></path></svg></span>' . implode( '', $popup_apps ) . '</div>';
															}
															?>
														</div>
														<h5 class="fs-6 m-0 w-75 lh-base"><?php echo $template['title']; ?></h5>
													</div>
													<div class="workflow-template-action w-25 text-end">
														<a href="javascript:void(0);" data-template-id="<?php echo $template_id; ?>" data-applications="<?php echo implode( ' > ', $app_names ); ?>" class="btn btn-outline-primary btn-sm btn-use-template"><?php echo esc_html__( 'Use Template', 'flowmattic' ); ?></a>
													</div>
												</div>
											</div>
										</div>
										<?php
									}
								} else {
									$workflow_message = $workflow_templates;

									// If license key is not set, show the message to enter the license key.
									if ( '' === $license_key ) {
										$workflow_message = esc_html__( 'Please enter your license key to get access to the workflow templates.', 'flowmattic' );
									}
									?>
									<div class="card border-light mw-100 w-100 d-block">
										<div class="card-body text-center">
											<div class="alert alert-danger ms-3 mt-3" role="alert">
												<?php echo $workflow_message; ?>
											</div>
										</div>
									</div>
									<?php
								}
								?>
								<div class="d-flex justify-content-center w-100">
									<button id="fmLoadMore" class="btn btn-outline-secondary"><?php echo esc_attr__( 'Load More', 'flowmattic' ); ?></button>
								</div>
							</div>
							<!-- Import Template Modal -->
							<div class="modal fade" id="fm-template-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="fm-template-modal-label" aria-hidden="true">
								<div class="modal-dialog modal-lg modal-dialog-centered">
									<form id="fm-template-import" class="w-100" method="POST" novalidate>
										<input class="hidden" name="template_id" type="hidden" value=""/>
										<div class="modal-content">
											<div class="modal-header">
												<h5 class="modal-title" id="fm-template-modal-label"><?php esc_html_e( 'Import Workflow Template', 'flowmattic' ); ?></h5>
												<button type="button" class="btn-close" data-dismiss="modal" aria-label="<?php esc_html_e( 'Close', 'flowmattic' ); ?>"></button>
											</div>
											<div class="modal-body">
												<div class="mb-3">
													<label for="template_name" class="form-label"><?php esc_html_e( 'Workflow Name', 'flowmattic' ); ?> <small class="badge text-danger bg-light"><?php esc_html_e( 'Required', 'flowmattic' ); ?></small></label>
													<input type="search" name="template_name" class="form-control fm-textarea" id="template_name" required>
													<div class="form-text"><?php esc_html_e( 'Name the workflow you want to import this template as.', 'flowmattic' ); ?></div>
												</div>
												<div class="alert alert-info" role="alert">
													<?php echo esc_html__( 'NOTE: Make sure you have installed the integrations before importing the workflow template', 'flowmattic' ); ?>
												</div>
												<p>
													<button type="submit" class="btn btn-import-template btn-primary me-2"><?php esc_html_e( 'Get Started', 'flowmattic' ); ?></button>
													<button type="button" class="btn btn-outline-secondary" data-dismiss="modal" aria-label="<?php esc_html_e( 'Close', 'flowmattic' ); ?>"><?php esc_html_e( 'Cancel', 'flowmattic' ); ?></button>
												</p>
											</div>
										</div>
									</form>
								</div>
							</div>
							<script type="text/javascript">
								jQuery( document ).ready( function ($) {
									var listItems = <?php echo wp_json_encode( $workflow_templates ); ?>;
									var currentPage = 1;
									var timer;  // For search debounce.
									const swalPopup = window.Swal.mixin({
										customClass: {
											confirmButton: 'btn btn-primary shadow-none me-xxl-3',
											cancelButton: 'btn btn-danger shadow-none'
										},
										buttonsStyling: false
									} );

									function showPage( page, items ) {
										var itemsPerPage = ( 'undefined' !== typeof items ) ? items : 10;
										var listItems = jQuery( '.workflow-template-list' ).find( '.flowmattic-recent-workflow-item' );
										var start = (page - 1) * itemsPerPage;
										var end = start + itemsPerPage;

										// Then, using slice function, select items from start to end and show them.
										listItems.slice( 0, end ).removeClass( 'd-none' );

										// If all items are visible, hide the Load More button.
										if ( end >= listItems.length ) {
											jQuery( '#fmLoadMore' ).hide();
										} else {
											jQuery('#fmLoadMore').show();
										}
									}

									jQuery( '#fmLoadMore' ).on( 'click', function() {
										currentPage++;
										showPage( currentPage );
									} );

									jQuery('#template-search').on('input', function() {
										clearTimeout(timer);  // Clear previous timer if it exists.
										timer = setTimeout(function() {
											var searchTerm = jQuery('#template-search').val().toLowerCase();
											if (searchTerm) {
												jQuery( '.workflow-template-list' ).find( '.flowmattic-recent-workflow-item' ).addClass( 'd-none' );
												jQuery( '.workflow-template-list' ).find( '.flowmattic-recent-workflow-item' ).each(function() {
													if (jQuery(this).text().toLowerCase().includes(searchTerm)) {
														jQuery(this).removeClass( 'd-none' );
													}
												});
												jQuery('#fmLoadMore').hide();
											} else {
												currentPage = 1;
												showPage(currentPage);
											}
										}, 300);  // Delay of 300ms.
									} );
									jQuery( document ).ready( function() {
										showPage( currentPage, 5 );
									} );

									jQuery( '.btn-use-template' ).on( 'click', function() {
										var templateID   = jQuery( this ).attr( 'data-template-id' ),
											templateName = jQuery( this ).attr( 'data-applications' );

										// Set the template name.
										jQuery( '#fm-template-modal' ).find( '[name="template_name"]' ).val( templateName );

										// Set the template ID.
										jQuery( '#fm-template-modal' ).find( '[name="template_id"]' ).val( templateID );

										// Show the modal.
										jQuery( '#fm-template-modal' ).modal( 'show' );
									} );

									jQuery( '#fm-template-import' ).on( 'submit', function( e ) {
										e.preventDefault();

										const form             = jQuery( this );
										const thisButton       = form.find( '.btn-import-template' );
										const templateID       = form.find( '[name="template_id"]' ).val();
										const templateName     = form.find( '[name="template_name"]' ).val();
										const templateFormData = new FormData( form[0] );

										// Show saving popup.
										swalPopup.fire(
											{
												title: 'Importing Template',
												showConfirmButton: false,
												didOpen: function() {
													swalPopup.showLoading();
												}
											}
										);

										// Add action name and nonce to the form data.
										templateFormData.append( 'action', 'flowmattic_import_workflow_template' );
										templateFormData.append( 'workflow_nonce', FMConfig.workflow_nonce );
										templateFormData.append( 'template_id', templateID );
										templateFormData.append( 'template_name', templateName );

										// Add saving animation for button.
										thisButton.addClass( 'disabled' );
										thisButton.html( '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span>Importing...' );

										jQuery.ajax( {
											url: ajaxurl,
											data: templateFormData,
											processData: false,
											contentType: false,
											type: 'POST',
											success: function( response ) {
												response = JSON.parse( response );
												swalPopup.fire(
													{
														title: 'Workflow Template Imported!',
														html: 'Redirecting to the workflow editor...',
														icon: 'success',
														showConfirmButton: false,
														timer: 5000,
														timerProgressBar: true
													}
												);

												// Hide the modal.
												jQuery( '#fm-template-modal' ).modal( 'hide' );

												// Remove animation for button and enable it.
												thisButton.html( 'Get Started' );
												thisButton.removeClass( 'disabled' );

												window.location = "<?php echo admin_url( '/admin.php?page=flowmattic-workflows&flowmattic-action=edit&workflow-id=' ); ?>" + response.workflow_id;
											},
											error: function( jqXHR, textStatus, errorThrown ) {
												swalPopup.fire(
													{
														title: 'Workflow Template Import Failed!',
														text: 'Something went wrong, and the workflow template import was not succesful. Please try again.',
														icon: 'error',
														showConfirmButton: true,
														timer: 3000
													}
												);

												// Hide the modal.
												jQuery( '#fm-template-modal' ).modal( 'hide' );

												// Remove animation for button and enable it.
												thisButton.html( 'Get Started' );
												thisButton.removeClass( 'disabled' );
											}
										} );
									} );
								} );
							</script>
						</div>
					</div>
				</div>
				<div class="flowmattic-content-sidebar col-12 col-lg-4 col-md-12 col-sm-12 mt-4">
					<div class="d-grid">
						<?php
						$button_type  = '';
						$button_class = '';
						$button_url   = admin_url( '/admin.php?page=flowmattic-workflows&flowmattic-action=new&nonce=' . wp_create_nonce( 'flowmattic-workflow-new' ) );

						if ( ! $license || '' === $license_key ) {
							$button_type  = 'disabled';
							$button_url   = 'javascript:void(0)';
							$button_class = 'needs-registration';
						}
						?>
						<a href="<?php echo $button_url; ?>" <?php echo $button_type; ?>  class="btn btn-lg btn-primary d-inline-flex align-items-center justify-content-center <?php echo $button_class; ?>">
							<span class="dashicons dashicons-plus-alt2 d-inline-block pe-3 ps-0 me-1"></span>
							<?php echo esc_attr__( 'New Workflow', 'flowmattic' ); ?>
						</a>
					</div>
					<div class="flowmattic-recent-workflows">
						<div class="card p-0 border-0 mw-100">
							<div class="card-header py-3">
								<h4 class="my-0 fw-bold fs-5">Recent Workflows</h4>
							</div>
							<div class="card-body bg-light">
								<?php
								if ( ! empty( $all_workflows ) ) {
									$nonce            = wp_create_nonce( 'flowmattic-workflow-edit' );
									$recent_workflows = array_slice( $all_workflows, -5 );

									// Sort workflows.
									arsort( $recent_workflows );

									foreach ( $recent_workflows as $key => $workflow ) {
										$applications      = array();
										$workflow_steps    = json_decode( $workflow->workflow_steps, true );
										$workflow_settings = json_decode( $workflow->workflow_settings );
										$workflow_time     = $workflow_settings->time;

										if ( ! empty( $workflow_steps ) ) {
											$count      = count( $workflow_steps );
											$popup_apps = array();

											foreach ( $workflow_steps as $index => $step ) {
												if ( ! isset( $step['application'] ) || ! isset( $all_applications[ $step['application'] ] ) ) {
													continue;
												}

												$application_icon = $all_applications[ $step['application'] ]['icon'];
												$application_name = $all_applications[ $step['application'] ]['name'];

												if ( 1 >= $index ) {
													$applications[] = '<div class="workflow-image" style="width: 30px; height: 30px;" data-toggle="tooltip" title="' . $application_name . '"><img src="' . $application_icon . '"></div>
																	<span class="svg-icon svg-icon--step-arrow"><svg viewBox="0 0 512 512"><path d="M71 455c0 35 39 55 67 35l285-199c24-17 24-53 0-70L138 22c-28-20-67 0-67 35z"></path></svg></span>';
												} else {
													$popup_apps[] = '<div class="workflow-image" style="width: 30px; height: 30px;" data-toggle="tooltip" title="' . $application_name . '"><img src="' . $application_icon . '"></div>
																		<span class="svg-icon svg-icon--step-arrow"><svg viewBox="0 0 512 512"><path d="M71 455c0 35 39 55 67 35l285-199c24-17 24-53 0-70L138 22c-28-20-67 0-67 35z"></path></svg></span>';
												}
											}

											// If count is greater than 2, show the remaining numbers.
											if ( 2 < $count ) {
												$applications[] = '<div class="workflow-image d-flex align-items-center justify-content-center fm-workflow-popup-trigger" data-toggle="tooltip" title="' . esc_html__( 'Click to expand', 'flowmattic' ) . '" style="width: 30px; height: 30px;">+' . ( $count - 2 ) . '</div>';
											}
										}
										?>
										<div class="flowmattic-recent-workflow-item shadow-sm p-3 rounded-3">
											<a href="<?php echo admin_url( '/admin.php?page=flowmattic-workflows&flowmattic-action=edit&workflow-id=' . $workflow->workflow_id . '&nonce=' . $nonce ); ?>" class="text-reset text-decoration-none">
												<h5 class="fs-6"><?php echo rawurldecode( $workflow->workflow_name ); ?></h5>
												<div class="abbr text-muted"><small><?php echo sprintf( __( 'Created on %s', 'flowmattic' ), $workflow_time ); ?></small></div>
											</a>
											<div class="workflow-applications d-flex align-items-center position-relative">
												<?php echo implode( '', $applications ); ?>
												<?php
												if ( ! empty( $popup_apps ) ) {
													echo '<div class="fm-workflow-apps-popup"><span class="svg-icon svg-icon--step-arrow"><svg viewBox="0 0 512 512"><path d="M71 455c0 35 39 55 67 35l285-199c24-17 24-53 0-70L138 22c-28-20-67 0-67 35z"></path></svg></span>' . implode( '', $popup_apps ) . '</div>';
												}
												?>
											</div>
										</div>
										<?php
									}
								} else {
									echo __( 'No workflows created. Click the Create Workflow button and create a new workflow to show it here.', 'elegant-elements' );
								}
								?>
								<div class="d-grid mt-3">
									<a href="<?php echo admin_url( '/admin.php?page=flowmattic-workflows' ); ?>" class="btn btn-outline-primary"><?php echo esc_attr__( 'See All Workflows', 'flowmattic' ); ?></a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="modal fade" id="fm-workflow-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="fm-workflow-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="fm-workflow-modal-label"><?php esc_html_e( 'Creating Workflow', 'flowmattic' ); ?></h5>
				<button type="button" class="btn-close" data-dismiss="modal" aria-label="<?php esc_html_e( 'Close', 'flowmattic' ); ?>"></button>
			</div>
			<div class="modal-body p-0">
				<div class="card p-0 border-0 mw-100 mt-0 pt-0">
					<div class="card-body bg-light">
						<div class="d-flex justify-content-between align-items-center">
							<div class="workflow-preview-loading d-flex align-items-center flex-column w-100 mb-4">
								<h3 class="text-center w-100 mb-4">
									Sit back! FlowMattic AI is creating a workflow for you!!<br/>
									This may take a few seconds..
								</h3>
								<div id="workflow-generator-lottie" style="width: 500px; height: 360px;"></div>
							</div>
							<div class="workflow-suggesion-preview w-100 d-none"></div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary btn-import-workflow-suggestion d-none"><?php esc_html_e( 'Use Workflow', 'flowmattic' ); ?></button>
				<button type="button" class="btn btn-secondary" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'flowmattic' ); ?></button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	jQuery( document ).ready( function() {
		const swalPopup = window.Swal.mixin({
			customClass: {
				confirmButton: 'btn btn-primary shadow-none me-xxl-3',
				cancelButton: 'btn btn-danger shadow-none'
			},
			buttonsStyling: false
		} );

		var animation,
			promptTextarea = jQuery( '.ai-workflow-assistance .workflow-prompt' );

		jQuery( '.generate-workflow-btn' ).on( 'click', function() {
			var promptText = promptTextarea.val();
				
			window.workflowData = {};

			if ( 'undefined' !== typeof promptText && '' === promptText ) {
				alert( 'Please enter a prompt to generate the workflow.' );
				return;
			}

			if ( ! animation ) {
				animation = window.lottie.loadAnimation({
					container: document.getElementById('workflow-generator-lottie'),
					renderer: 'svg',
					loop: true,
					autoplay: true,
					path: '<?php echo esc_url( FLOWMATTIC_PLUGIN_URL . 'assets/admin/img/ai-workflow-assistance.json' ); ?>'
				});
			} else {
				animation.play();
			}

			jQuery( '.workflow-preview-loading' ).removeClass( 'd-none' ).addClass( 'd-flex' );
			jQuery( '.workflow-suggesion-preview' ).addClass( 'd-none' );
			jQuery( '#fm-workflow-modal-label' ).html( 'Creating Workflow...' );

			jQuery( '#fm-workflow-modal' ).modal( 'show' );

			// If modal closed, stop the animation.
			jQuery( '#fm-workflow-modal' ).on( 'hidden.bs.modal', function() {
				animation.stop();
			} );

			jQuery.ajax( {
				url: '<?php echo rest_url( 'flowmattic/v1/workflow-assistance' ); ?>',
				data: {
					action: 'flowmattic_generate_workflow_assistance',
					prompt: promptText,
					workflow_nonce: FMConfig.workflow_nonce
				},
				type: 'POST',
				success: function( response ) {
					jQuery( '.workflow-suggesion-preview' ).removeClass( 'd-none' );
					jQuery( '.workflow-preview-loading' ).removeClass( 'd-flex' ).addClass( 'd-none' );
					if ( response.workflow_preview && '' !== response.workflow_preview ) {
						jQuery( '#fm-workflow-modal-label' ).html( 'Here\'s your workflow!' );
						jQuery( '.workflow-suggesion-preview' ).html( response.workflow_preview );
						jQuery( '.btn-import-workflow-suggestion' ).removeClass( 'd-none' );

						// Set the workflow data.
						window.workflowData = response.workflow_suggestion;
					} else {
						jQuery( '.workflow-suggesion-preview' ).html( '<h3 class="text-center w-100 mb-4">We couldn\'t generate a workflow for the prompt you entered. Please try again.</h3>' );
						jQuery( '.btn-import-workflow-suggestion' ).addClass( 'd-none' );
					}
				}
			} );
		} );

		// Import the workflow, and redirect the user to the workflow editor.
		jQuery( '.btn-import-workflow-suggestion' ).on( 'click', function() {
			var attributes = {
					action: 'flowmattic_import_workflow',
					workflowData: window.workflowData,
					workflow_nonce: FMConfig.workflow_nonce,
					ai_workflow: true
				};

			// Show loading popup.
			swalPopup.fire(
				{
					title: 'Creating workflow...',
					showConfirmButton: false,
					didOpen: function() {
						swalPopup.showLoading();
					}
				}
			);

			if ( 'undefined' !== typeof window.workflowData.workflow_steps && '' !== window.workflowData.workflow_steps ) {
				jQuery.ajax( {
					url: ajaxurl,
					data: attributes,
					type: 'POST',
					success: function( response ) {
						var data = JSON.parse( response );
						// Hide the import modal.
						jQuery( '#workflow-import-modal' ).modal( 'hide' );

						// Reload the page.
						setTimeout( function() {
							window.location = "<?php echo admin_url( '/admin.php?page=flowmattic-workflows&flowmattic-action=edit&workflow-id=' ); ?>" + data.workflow_id;
						}, 200 );
					}
				} );
			}
		} );

		// Auto resize the textarea.
		promptTextarea.on( 'input', function() {
			this.style.height = 'auto';
			this.style.height = ( this.scrollHeight ) + 'px';
		} );
	} );
</script>
<style type="text/css">
	.flowmattic-wrap .fm-workflow-steps .fm-workflow-trigger:after,
	.flowmattic-wrap .fm-workflow-steps .fm-workflow-action:after {
		top: 100% !important;
	}
	.flowmattic-wrap .fm-workflow-steps .fm-workflow-action:last-child:after {
		display: none;
	}
	body .flowmattic-wrap .fm-workflow-steps .fm-workflow-step .fm-workflow-step-header .fm-workflow-step-info .fm-workflow-step-application-title {
		font-size: 16px !important;
	}
</style>
<?php 
wp_enqueue_script( 'flowmattic-lottie', 'https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.7.4/lottie.min.js', array(), '5.7.4', true );
FlowMattic_Admin::footer(); ?>
