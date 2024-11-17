<?php
/**
 * Handle database queries for workflows.
 *
 * @package flowmattic
 * @since 1.0
 */

if ( ! class_exists( 'FlowMattic_Database_Workflows' ) ) {
	/**
	 * Handle database queries for workflows.
	 *
	 * @since 1.0
	 */
	class FlowMattic_Database_Workflows {

		/**
		 * The table name.
		 *
		 * @access protected
		 * @since 1.0
		 * @var string
		 */
		protected $table_name = 'flowmattic_workflows';

		/**
		 * The Constructor.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function __construct() {
		}

		/**
		 * Insert workflow to database.
		 *
		 * @since 1.0
		 * @access public
		 * @param array $args The arguments.
		 * @return integer|boolean The last insert id or false if query failed.
		 */
		public function insert( $args ) {
			global $wpdb;

			// Check if workflow ID is provided, else skip.
			if ( isset( $args['workflow_id'] ) ) {
				$exists = $this->get( $args );

				// If not exists, add new record, else update.
				if ( ! $exists ) {
					return $wpdb->insert(
						$wpdb->prefix . $this->table_name,
						array(
							'workflow_id'       => esc_attr( $args['workflow_id'] ),
							'workflow_name'     => esc_attr( $args['workflow_name'] ),
							'workflow_steps'    => wp_json_encode( $args['workflow_steps'] ),
							'workflow_settings' => wp_json_encode( $args['workflow_settings'] ),
						)
					);
				} else {
					return $this->update( $args );
				}
			}
		}

		/**
		 * Update the workflow in database.
		 *
		 * @since 1.0
		 * @access public
		 * @param array $args The arguments.
		 * @return void
		 */
		public function update( $args ) {
			global $wpdb;

			// Check if workflow ID is provided, else skip.
			if ( isset( $args['workflow_id'] ) ) {
				return $wpdb->update(
					$wpdb->prefix . $this->table_name,
					array(
						'workflow_name'     => esc_attr( $args['workflow_name'] ),
						'workflow_steps'    => wp_json_encode( $args['workflow_steps'] ),
						'workflow_settings' => wp_json_encode( $args['workflow_settings'] ),
					),
					array(
						'workflow_id' => esc_attr( $args['workflow_id'] ),
					)
				);
			}
		}

		/**
		 * Update the workflow settings.
		 *
		 * @since 4.1.0
		 * @access public
		 * @param integer $workflow_id The workflow ID.
		 * @param array   $args        The arguments.
		 * @return integer|boolean The last insert id or false if query failed.
		 */
		public function update_settings( $workflow_id, $args ) {
			global $wpdb;

			// Check if workflow ID is provided, else skip.
			if ( isset( $workflow_id ) && '' !== $workflow_id ) {
				return $wpdb->update(
					$wpdb->prefix . $this->table_name,
					array(
						'workflow_settings' => wp_json_encode( $args['workflow_settings'] ),
					),
					array(
						'workflow_id' => esc_attr( $workflow_id ),
					)
				);
			}
		}

		/**
		 * Delete the workflow from database.
		 *
		 * @since 1.0
		 * @access public
		 * @param array $args The arguments.
		 * @return void
		 */
		public function delete( $args ) {
			global $wpdb;

			// Check if workflow ID is provided, else skip.
			if ( isset( $args['workflow_id'] ) ) {
				$wpdb->delete(
					$wpdb->prefix . $this->table_name,
					array(
						'workflow_id' => esc_attr( $args['workflow_id'] ),
					)
				);
			}
		}

		/**
		 * Get the workflow from database.
		 *
		 * @since 1.0
		 * @access public
		 * @param array $args The arguments.
		 * @return array Workflow data from database.
		 */
		public function get( $args ) {
			global $wpdb;

			// Check if workflow ID is provided, else skip.
			if ( isset( $args['workflow_id'] ) ) {
				$workflow = $wpdb->get_results(
					$wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . $this->table_name . '` WHERE `workflow_id` = %s', $args['workflow_id'] )
				);
			}

			$workflow = ( ! empty( $workflow ) && isset( $workflow[0] ) ) ? $workflow[0] : array();

			return $workflow;
		}

		/**
		 * Get the workflow from database.
		 *
		 * @since 1.0
		 * @access public
		 * @param array $application_name The application name.
		 * @return array Workflow data from database.
		 */
		public function get_workflow_by_trigger_application( $application_name ) {
			global $wpdb;

			// Check if trigger application is provided, else skip.
			if ( '' !== $application_name ) {
				$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $this->table_name ) );
				if ( $wpdb->get_var( $query ) === $wpdb->prefix . $this->table_name ) {
					$workflows = $wpdb->get_results(
						$wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . $this->table_name . '` WHERE `workflow_steps` LIKE %s', '%"application":"' . $application_name . '"%' )
					);
				} else {
					$workflows = false;
				}
			}

			if ( ! empty( $workflows ) ) {
				foreach ( $workflows as $key => $workflow ) {
					$workflow_steps = json_decode( $workflow->workflow_steps );

					// Loop through each workflow to check the trigger step application.
					if ( ! empty( $workflow_steps ) ) {
						foreach ( $workflow_steps as $step ) {
							if ( 'trigger' !== $step->type ) {
								continue;
							}

							// Remove the workflow if the trigger application is not the same as the provided application.
							if ( $application_name !== $step->application ) {
								unset( $workflows[ $key ] );
							}
						}
					}
				}
			}

			return $workflows;
		}

		/**
		 * Get all workflows from database.
		 *
		 * @since 1.0
		 * @access public
		 * @return object Workflow data from database.
		 */
		public function get_all() {
			global $wpdb;

			return $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . $this->table_name . '` WHERE %s', 1 )
			);
		}

		/**
		 * Get all workflows from database for the specified user.
		 *
		 * @since 1.3.0
		 * @access public
		 * @return object Workflow data from database.
		 */
		public function get_user_workflows( $user_email ) {
			global $wpdb;

			return $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . $this->table_name . '` WHERE `workflow_settings` LIKE %s', '%"user_email":"' . $user_email . '"%' )
			);
		}
	}
}
