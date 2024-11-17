<?php FlowMattic_Admin::loader(); ?>
<div class="wrap flowmattic-wrap about-wrap">
	<div class="flowmattic-wrapper d-flex">
		<?php FlowMattic_Admin::header(); ?>
		<div class="flowmattic-dashboard-content container m-0 ps-3">
			<div class="row">
				<?php
				// Check conditions.
				$permalink_structure = get_option( 'permalink_structure' );

				// If postname is not in the permalink structure, it's not recommended.
				$permalink_ok = ( strpos( $permalink_structure, '%postname%' ) !== false ) ? true : false;

				$site_url    = get_option( 'siteurl' );
				$site_url_ok = ( strpos( $site_url, 'https://' ) === 0 ) ? true : false;

				$php_version    = phpversion();
				$php_version_ok = ( version_compare( $php_version, '7.4', '>' ) ) ? true : false;

				$memory_limit    = ini_get( 'memory_limit' );
				$memory_limit_ok = ( intval( $memory_limit ) >= 256 ) ? true : false;

				$timeout_limit    = ini_get( 'max_execution_time' );
				$timeout_limit_ok = ( $timeout_limit == 0 || $timeout_limit >= 300 ) ? true : false;
				?>
				<div class="flowmattic-container flowmattic-dashboard m-0">
					<h3><?php esc_html_e( 'FlowMattic Status Page', 'flowmattic' ); ?></h3>
					<div class="navbar-text bg-light w-100 ps-3 py-3">
						<?php esc_html_e( 'Check your site configuration status. If any of the following is red, please contact your hosting provider and ask them to fix it to avoid issues with your workflows.', 'flowmattic' ); ?>
					</div>
					<ul class="list-group mt-3">
						<li class="list-group-item d-flex align-items-center py-3 mb-0">
							<span class="fs-6 me-3 badge bg-<?php echo $permalink_ok ? 'success' : 'danger'; ?> rounded-circle p-0">
								<i class="dashicons dashicons-<?php echo $permalink_ok ? 'yes-alt' : 'warning'; ?>"></i>
							</span>
							<span style="min-width: 180px;"><?php esc_html_e( 'Permalink Structure', 'flowmattic' ); ?></span>
							<?php if ( ! $permalink_ok ) : ?>
								<small class="text-danger ms-4"><?php esc_html_e( 'Recommended: /%postname', 'flowmattic' ); ?>%/</small>
							<?php endif; ?>
						</li>
						<li class="list-group-item d-flex align-items-center py-3 mb-0">
							<span class="fs-6 me-3 badge bg-<?php echo $site_url_ok ? 'success' : 'danger'; ?> rounded-circle p-0">
								<i class="dashicons dashicons-<?php echo $site_url_ok ? 'yes-alt' : 'warning'; ?>"></i>
							</span>
							<span style="min-width: 180px;"><?php esc_html_e( 'Secure Site URL', 'flowmattic' ); ?></span>
							<?php if ( ! $site_url_ok ) : ?>
								<small class="text-danger ms-4"><?php esc_html_e( 'Required: HTTPS', 'flowmattic' ); ?></small>
							<?php endif; ?>
						</li>
						<li class="list-group-item d-flex align-items-center py-3 mb-0">
							<span class="fs-6 me-3 badge bg-<?php echo $php_version_ok ? 'success' : 'danger'; ?> rounded-circle p-0">
								<i class="dashicons dashicons-<?php echo $php_version_ok ? 'yes-alt' : 'warning'; ?>"></i>
							</span>
							<span style="min-width: 180px;"><?php esc_html_e( 'PHP Version', 'flowmattic' ); ?></span>
							<?php if ( ! $php_version_ok ) : ?>
								<small class="text-danger ms-4"><?php esc_html_e( 'Required: 7.4 or greater', 'flowmattic' ); ?></small>
							<?php endif; ?>
						</li>
						<li class="list-group-item d-flex align-items-center py-3 mb-0">
							<span class="fs-6 me-3 badge bg-<?php echo $memory_limit_ok ? 'success' : 'danger'; ?> rounded-circle p-0">
								<i class="dashicons dashicons-<?php echo $memory_limit_ok ? 'yes-alt' : 'warning'; ?>"></i>
							</span>
							<span style="min-width: 180px;"><?php esc_html_e( 'PHP Memory Limit', 'flowmattic' ); ?></span>
							<?php if ( ! $memory_limit_ok ) : ?>
								<small class="text-danger ms-4"><?php esc_html_e( 'Required: 256 MB or greater', 'flowmattic' ); ?></small>
							<?php endif; ?>
						</li>
						<li class="list-group-item d-flex align-items-center py-3 mb-0">
							<span class="fs-6 me-3 badge bg-<?php echo $timeout_limit_ok ? 'success' : 'danger'; ?> rounded-circle p-0">
								<i class="dashicons dashicons-<?php echo $timeout_limit_ok ? 'yes-alt' : 'warning'; ?>"></i>
							</span>
							<span style="min-width: 180px;"><?php esc_html_e( 'PHP Timeout Limit', 'flowmattic' ); ?></span>
							<?php if ( ! $timeout_limit_ok ) : ?>
								<small class="text-danger ms-4"><?php esc_html_e( 'Required: 0 or greater than 300', 'flowmattic' ); ?></small>
							<?php endif; ?>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
<?php FlowMattic_Admin::footer(); ?>
