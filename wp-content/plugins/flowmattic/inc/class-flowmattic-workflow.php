<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FLOWMATTIC_PLUGIN_DIR . 'inc/async/class-flowmattic-async-request.php';
require_once FLOWMATTIC_PLUGIN_DIR . 'inc/async/class-flowmattic-background-process.php';
require_once FLOWMATTIC_PLUGIN_DIR . 'inc/async/class-flowmattic-webhook-background-process.php';

/**
 * Main worflow class.
 *
 * @since 1.0
 */
class FlowMattic_Workflow {
	/**
	 * Background process.
	 *
	 * @access protected
	 * @since 3.2.0
	 * @var array
	 */
	public $background_process;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		// Define the background process object.
		$this->background_process = new FlowMattic_Webhook_Background_Process();

		// Ajax to get the captured webhook data.
		add_action( 'wp_ajax_flowmattic_capture_data', array( $this, 'flowmattic_capture_data' ) );

		// Ajax to save workflow.
		add_action( 'wp_ajax_flowmattic_save_workflow', array( $this, 'flowmattic_save_workflow' ) );

		// Ajax to delete workflow.
		add_action( 'wp_ajax_flowmattic_delete_workflow', array( $this, 'flowmattic_delete_workflow' ) );

		// Ajax to delete tasks in a workflow.
		add_action( 'wp_ajax_flowmattic_delete_workflow_task_history', array( $this, 'flowmattic_delete_workflow_task_history' ) );

		// Ajax to handle the save and test action event.
		add_action( 'wp_ajax_flowmattic_save_test_action_step', array( $this, 'flowmattic_save_test_action_step' ) );

		// Re-execute workflow.
		add_action( 'wp_ajax_flowmattic_execute_workflow', array( $this, 'flowmattic_execute_workflow' ) );

		// Re-execute workflows in bulk.
		add_action( 'wp_ajax_flowmattic_bulk_execute_tasks', array( $this, 'flowmattic_bulk_execute_workflows' ) );

		// Bulk delete task history items.
		add_action( 'wp_ajax_flowmattic_bulk_delete_tasks', array( $this, 'flowmattic_bulk_delete_tasks' ) );

		// Ajax to cancel the delay.
		add_action( 'wp_ajax_flowmattic_cancel_delay', array( $this, 'flowmattic_cancel_delay' ) );

		// Ajax to update the workflow status.
		add_action( 'wp_ajax_flowmattic_update_workflow_status', array( $this, 'update_workflow_status' ) );

		// Handle the task cleanup event.
		add_action( 'flowmattic_task_cleanup_cron', array( $this, 'run_task_cleanup_cron' ), 10, 1 );

		// Handle workflow execution globally.
		add_action( 'flowmattic_workflow_execute', array( $this, 'execute' ), 10, 4 );
	}

	/**
	 * Check if webhook has received any data.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function flowmattic_capture_data() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		if ( isset( $_POST['webhook-id'] ) ) {
			$webhook_id       = $_POST['webhook-id'];
			$capture_response = isset( $_POST['capture'] ) ? $_POST['capture'] : '';
			$application_name = isset( $_POST['application'] ) ? $_POST['application'] : '';
			$app_action       = isset( $_POST['appAction'] ) ? $_POST['appAction'] : '';
			$trigger_event    = isset( $_POST['triggerEvent'] ) ? $_POST['triggerEvent'] : '';
			$webhook_auth_key = isset( $_POST['workflow_auth_key'] ) ? $_POST['workflow_auth_key'] : '';
			$webhook_response = isset( $_POST['webhook_response'] ) ? $_POST['webhook_response'] : '';

			// Set the authentication for current workflow.
			if ( '' !== $webhook_auth_key ) {
				update_option( 'webhook-authentication-key-' . $webhook_id, $webhook_auth_key, false );
			} else {
				// Delete the authentication key option.
				delete_option( 'webhook-authentication-key-' . $webhook_id );
			}

			// Set the custom webhook response for current workflow.
			if ( '' !== $webhook_response ) {
				update_option( 'webhook-response-text-' . $webhook_id, $webhook_response, false );
			}

			// Set current capture webhook.
			update_option( 'webhook-capture-live', $webhook_id, false );
			update_option( 'webhook-capture-application', $application_name, false );

			if ( 1 === (int) $capture_response ) {
				update_option( 'webhook-capture-app-action', $app_action, false );
				update_option( 'webhook-capture-trigger-event', $trigger_event, false );
			}

			$webhook_capture = get_option( 'webhook-capture-' . $webhook_id, array() );

			if ( $webhook_capture ) {
				$reply = array(
					'status'          => 'success',
					'capture'         => $capture_response,
					'webhook_capture' => $webhook_capture,
					'captured_at'     => date_i18n( 'Y-m-d H:i:s' ),
				);

				// Print the response.
				echo wp_json_encode( $reply );

				// Delete the live webhook id.
				delete_option( 'webhook-capture-live' );

				// Delete the temp. stored response.
				delete_option( 'webhook-capture-' . $webhook_id );
			} else {
				$reply = array(
					'status'  => 'pending',
					'capture' => $capture_response,
				);

				// Print the response.
				echo wp_json_encode( $reply );
			}
		}

		die();
	}

	/**
	 * Run the workflow for the given workflow ID.
	 *
	 * @access public
	 * @since 1.0
	 * @param string $workflow_id   Workflow ID.
	 * @param string $capture_data  Use submitted or captured data by the trigger.
	 * @param array  $delay_data    Data passed by the delay module.
	 * @param array  $iterator_data Data passed by the iterator module.
	 * @param bool   $force_execute Execute workflow forcefully, even if it is turned off.
	 * @return bool If workflow is turned OFF, it should not execute.
	 */
	public function run( $workflow_id, $capture_data, $delay_data = array(), $iterator_data = array(), $force_execute = false ) {
		$args = array(
			'workflow_id' => $workflow_id,
		);

		$tasks_db = wp_flowmattic()->tasks_db;
		$workflow = wp_flowmattic()->workflows_db->get( $args );

		if ( ! $workflow ) {
			return false;
		}

		$settings        = json_decode( $workflow->workflow_settings, true );
		$workflow_status = ( isset( $settings['status'] ) && $settings['status'] ) ? $settings['status'] : 'off';
		$webhook_queue   = ( isset( $settings['webhook_queue'] ) && $settings['webhook_queue'] ) ? $settings['webhook_queue'] : 'disable';

		// Do not execute workflows that are not turned ON.
		if ( 'on' !== $workflow_status && ! $force_execute ) {
			return false;
		}

		$steps              = $workflow->workflow_steps;
		$steps              = json_decode( $steps, true );
		$dummy_steps        = $steps;
		$response_data      = array();
		$task_email_body    = '';
		$request_data       = '';
		$iterator_last_step = ( isset( $iterator_data['last_step'] ) && $iterator_data['last_step'] ) ? true : false;

		$task_history_id = flowmattic_random_string() . '_' . wp_rand();
		$skip_to_step    = false;

		// If delay, set the task id from database.
		if ( ! empty( $delay_data ) && isset( $delay_data['task_history_id'] ) ) {
			$task_history_id = $delay_data['task_history_id'];
		}

		// If iterator, set the task id from database.
		if ( ! empty( $iterator_data ) && isset( $iterator_data['task_history_id'] ) ) {
			$task_history_id = $iterator_data['task_history_id'];
		}

		// Initialize task data.
		$task = array(
			'task_id'     => $task_history_id,
			'workflow_id' => $workflow_id,
			'task_data'   => array(),
		);

		if ( empty( $delay_data ) && empty( $iterator_data ) ) {
			// $tasks_db->insert( $task );
		} else {
			// Get the task data by ID.
			$task = $tasks_db->get_task_by_id( $task_history_id );
			$task = (array) $task[0];

			// Set the captured data from trigger step.
			$task_data         = json_decode( $task['task_data'], true );
			$task['task_data'] = $task_data;
		}

		if ( ! empty( $delay_data ) ) {
			$task_history_id = $delay_data['task_history_id'];
			$skip_to_step    = $delay_data['step_id'];

			// Get the task data by ID.
			$task = $tasks_db->get_task_by_id( $task_history_id );
			$task = (array) $task[0];

			// Set the captured data from trigger step.
			$task_data         = json_decode( $task['task_data'], true );
			$task['task_data'] = $task_data;
			$task_captured     = $task_data[0]['captured_data'];
			$capture_data      = $task_captured;

			foreach ( $steps as $i => $step_item ) {
				$step_app    = $step_item['application'];
				$step_number = $i + 1;
				foreach ( $task_data as $key => $task_item ) {
					$task_app      = $task_item['application'];
					$step_id_match = ( isset( $task_item['step_id'] ) ) ? ( $task_item['step_id'] === $step_item['stepID'] ) : true;

					if ( $step_app === $task_app && $step_id_match ) {
						// If skip to step is set, do it!
						if ( $skip_to_step && $skip_to_step !== $step_item['stepID'] ) {

							// If steps before delay are already executed, get response data.
							$task_captured = $task_item['captured_data'];

							// Set the response data for the step.
							$response_data[ $step_app . $step_number ] = ! is_array( $task_captured ) ? json_decode( $task_captured, true ) : $task_captured;
						} else {
							break;
						}
					}
				}
			}
		}

		$step_number    = 1;
		$next_step_flag = true;
		$task_queued    = false;

		// Skip to step if set.
		$skip_to_step_id = ( ! empty( $iterator_data ) && isset( $iterator_data['next_step_id'] ) ) ? $iterator_data['next_step_id'] : '';

		if ( '' !== $skip_to_step_id ) {
			// Iterator.
			if ( isset( $iterator_data['task_history_id'] ) ) {
				$task_history_id = $iterator_data['task_history_id'];
			}

			if ( empty( $response_data ) ) {
				$response_data = $iterator_data['response_data'];
			}
		}

		$step_count = 0;

		foreach ( $steps as $i => $step ) {
			$type        = $step['type'];
			$application = $step['application'];
			$action_step = isset( $steps[ $i + 1 ] ) ? $steps[ $i + 1 ]['stepID'] : false;

			// Iterator.
			if ( '' !== $skip_to_step_id ) {

				if ( 'iterator' === $application ) {
					$response_data[ $application . $step_number ] = $capture_data;
				}

				if ( $skip_to_step_id !== $step['stepID'] ) {
					// Increase the step number accordingly to avoid conflicts.
					$step_number = $step_number + 1;

					continue;
				} else {
					$skip_to_step_id = '';
				}
			}

			// If skip to step is set, do it!
			if ( $skip_to_step && $skip_to_step !== $step['stepID'] ) {

				// Increase the step number accordingly to avoid conflicts.
				$step_number = $step_number + 1;

				continue;
			}

			// Step after delay is getting executed, set the skip to step flag to false.
			$skip_to_step = false;

			// Delay.
			if ( 'delay' === $application ) {
				$event = $step['action'];

				$next_step_id = isset( $steps[ $i + 1 ] ) ? $steps[ $i + 1 ]['stepID'] : false;

				// If last step, abort the delay.
				if ( ! $next_step_id ) {
					$task['task_data'][] = array(
						'application'   => $application,
						'step_id'       => $step['stepID'],
						'captured_data' => array(
							'status'           => esc_attr__( 'Success', 'flowmattic' ),
							'message'          => esc_attr__( 'Delay step is the last step in the workflow. Aborting the delay.', 'flowmattic' ),
							'step_executed_at' => date_i18n( 'Y-m-d H:i:s' ),
						),
					);

					// Record task into database.
					$tasks_db->update( $task );

					$next_step_flag = false;

					break;
				}

				foreach ( $response_data as $app_id => $app_data ) {
					// If app data is not array, do not process.
					if ( ! is_array( $app_data ) ) {
						continue;
					}

					foreach ( $app_data as $key => $value ) {
						$replace_tag = '{' . $app_id . '.' . $key . '}';

						if ( is_array( $value ) ) {
							continue;
						}

						// If is null, assign empty string.
						$value = ( null === $value ) ? '' : str_replace( array( "\r" ), '', $value );

						// Deal with the values having double quotes.
						$value = addslashes( $value );

						$step_json     = wp_json_encode( $step );
						$updated_value = str_replace( $replace_tag, $value, $step_json );
						$step          = json_decode( $updated_value, true );
					}
				}

				$fields = $step['actionAppArgs'];

				if ( 'delay_for' === $event ) {
					$delay_unit  = $fields['delayUnit'];
					$delay_value = (int) $fields['delayValue'];
					$time_value  = '';

					// Translators: %1$s: Delay value, %2$s: Delay unit.
					$response = sprintf( __( 'Execution delayed for %1$s %2$s', 'flowmattic' ), $delay_value, $delay_unit );

					switch ( $delay_unit ) {
						case 'seconds':
							$time_value = $delay_value;
							break;
						case 'minutes':
							$time_value = $delay_value * 60;
							break;
						case 'hours':
							$time_value = $delay_value * 3600;
							break;
						case 'days':
							$time_value = $delay_value * 24 * 3600;
							break;
						case 'weeks':
							$time_value = $delay_value * 7 * 24 * 3600;
							break;
					}

					if ( 59 >= $time_value ) {
						$task['task_data'][] = array(
							'application'   => $application,
							'step_id'       => $step['stepID'],
							'captured_data' => array(
								'status'           => esc_attr__( 'Success', 'flowmattic' ),
								'message'          => $response,
								'step_executed_at' => date_i18n( 'Y-m-d H:i:s' ),
							),
						);

						// Record task into database.
						$tasks_db->update( $task );

						// Wait for seconds.
						flowmattic_delay( $time_value );

						if ( ! empty( $iterator_data ) ) {
							$iterator_data_new = array(
								'response_data'   => $iterator_data['response_data'],
								'next_step_id'    => $next_step_id,
								'task_history_id' => $task_history_id,
							);

							// If last step, set the flag.
							if ( isset( $iterator_data['last_step'] ) && $iterator_data['last_step'] ) {
								$iterator_data_new['last_step'] = true;
							}

							$flowmattic_workflow = new FlowMattic_Workflow();
							$flowmattic_workflow->run( $workflow_id, $capture_data, array(), $iterator_data_new, $force_execute );
						} else {
							// Execute the delay action.
							do_action( 'flowmattic_delay_workflow_step', $task_history_id, $next_step_id, $workflow_id );
						}

						$next_step_flag = false;

						break;
					} else {
						// Wait for a sec.
						flowmattic_delay( 1 );

						// Set the next trigger timestamp.
						$next_trigger_timestamp = time() + $time_value;

						// Schedule the delay event.
						$schedule = wp_schedule_single_event( $next_trigger_timestamp, 'flowmattic_delay_workflow_step', array( $task_history_id, $next_step_id, $workflow_id ), true );

						$next_scheduled = wp_next_scheduled( 'flowmattic_delay_workflow_step', array( $task_history_id, $next_step_id, $workflow_id ) );

						// Check if the schedule is set.
						if ( is_wp_error( $schedule ) ) {
							$response = $schedule->get_error_message();
						}

						$next_step_flag = false;
					}
				} elseif ( 'delay_until' === $event ) {
					$delay_time = $fields['delayTime'];

					$time_difference     = ( strtotime( get_date_from_gmt( $delay_time ) ) - strtotime( date_i18n( 'Y-m-d H:i:s', strtotime( $delay_time ) ) ) );
					$timezone_delay_time = ( date_i18n( 'Y-m-d H:i:s', ( strtotime( date_i18n( 'Y-m-d H:i:s', strtotime( $delay_time ) ) ) - $time_difference ) ) );

					// Translators: %s: Delay time.
					$response   = sprintf( esc_attr__( 'Execution paused until %s', 'flowmattic' ), $delay_time );
					$time_value = strtotime( $timezone_delay_time );

					if ( time() >= $time_value ) {
						// Translators: %s: Delay time.
						$response   = sprintf( esc_attr__( 'Delay execution time already passed on %s. Executing the next step in a few moments.', 'flowmattic' ), $delay_time );
						$time_value = time() + 20;
					}

					$schedule = wp_schedule_single_event( $time_value, 'flowmattic_delay_workflow_step', array( $task_history_id, $next_step_id, $workflow_id ) );

					if ( is_wp_error( $schedule ) ) {
						$response = $schedule->get_error_message();
					}

					$next_step_flag = false;
				}

				$task['task_data'][] = array(
					'application'   => $application,
					'step_id'       => $step['stepID'],
					'captured_data' => array(
						'status'           => esc_attr__( 'Success', 'flowmattic' ),
						'message'          => $response,
						'step_executed_at' => date_i18n( 'Y-m-d H:i:s' ),
					),
					'cron_data'     => array(
						'next_step_id'    => $next_step_id,
						'task_history_id' => $task_history_id,
					),
				);

				// Insert task into database.
				$tasks_db->update( $task );

				break;
			}

			if ( ! $next_step_flag ) {
				break;
			}

			// Let the server breathe for a while.
			usleep( 25000 );

			switch ( $type ) {

				case 'trigger':
					$is_workflow_executable = true;

					// Check if it is a custom app.
					if ( preg_match( '/^(custom_app|app_)/', $application ) ) {
						$app_class = 'FlowMattic_Custom_User_App';
					} else {
						$app_class = ucwords( str_replace( array( '-', '_' ), ' ', $application ) );
						$app_class = str_replace( ' ', '_', $app_class );
						$app_class = 'FlowMattic_' . $app_class;
					}

					if ( class_exists( $app_class ) ) {
						$app_class_instance = new $app_class();
						if ( method_exists( $app_class_instance, 'validate_workflow_step' ) ) {
							$is_workflow_executable = $app_class_instance->validate_workflow_step( $workflow_id, $step, $capture_data );
						}
					}

					if ( $is_workflow_executable ) {
						$response_data[ $application . $step_number ] = $capture_data;

						if ( 'plugin_actions' === $application ) {
							update_option( 'plugin_actions_response', wp_json_encode( $capture_data ), false );
						}

						$task['task_data'][] = array(
							'application'      => $application,
							'captured_data'    => $capture_data,
							'step_executed_at' => date_i18n( 'Y-m-d H:i:s' ),
						);

						// Insert task into database.
						$tasks_db->insert( $task );

						// Check if workflow should use webhook queue if received too many requests simultenousely.
						if ( 'enable' === $webhook_queue ) {
							// Check if the webhook response step is in the workflow.
							if ( isset( $capture_data['webhook_captured_at'] ) && false === strpos( $workflow->workflow_steps, '"application":"webhook_response"' ) ) {
								if ( $action_step ) {
									// Add the task data to the background process queue.
									$this->background_process->push_to_queue( array( $task_history_id, $action_step, $workflow_id ) );

									// Set the queue flag.
									$task_queued = true;

									break 2;
								}
							}
						}
					} else {
						exit;
					}

					break;

				case 'action':
					// Delete the workflow captured data.
					unset( $step['capturedData'] );

					if ( isset( $response_data['plugin_actions1'] ) && get_option( 'plugin_actions_response', false ) ) {
						$new_response = array();

						$response_received = get_option( 'plugin_actions_response', array() );
						$response_received = json_decode( $response_received, true );

						foreach ( $response_received as $key => $value ) {
							if ( is_array( $value ) ) {
								foreach ( $value as $inner_key => $inner_value ) {
									if ( is_array( $inner_value ) ) {
										foreach ( $inner_value as $inner_key2 => $inner_value2 ) {
											if ( is_array( $inner_value2 ) ) {
												$new_response[ $key . '_' . $inner_key . '_' . $inner_key2 ] = wp_json_encode( $inner_value2 );
											} else {
												$new_response[ $key . '_' . $inner_key . '_' . $inner_key2 ] = $inner_value2;
											}
										}
									} else {
										$new_response[ $key . '_' . $inner_key ] = $inner_value;
									}
								}
							} else {
								$new_response[ $key ] = $value;
							}
						}

						$app_data = $new_response;

						$response_data['plugin_actions1']      = $app_data;
						$task['task_data'][0]['captured_data'] = $app_data;

						delete_option( 'plugin_actions_response' );
					}

					foreach ( $response_data as $app_id => $app_data ) {
						// If app data is not array, do not process.
						if ( ! is_array( $app_data ) ) {
							continue;
						}

						foreach ( $app_data as $key => $value ) {
							$replace_tag = '{' . $app_id . '.' . $key . '}';

							if ( is_array( $value ) ) {
								if ( 1 !== count( $value ) && isset( $value[0] ) ) {
									$value = $value[0];
								}

								// Do the dynamic tag replacement with their values.
								$updated_value = flowmattic_dynamic_tag_values( $step, $replace_tag, $value );

								// Assign the updated data back to step object.
								$step = $updated_value;
								continue;
							}

							// If is null, assign empty string.
							$value = ( null === $value ) ? '' : str_replace( array( "\r" ), '', $value );

							if ( 'filter' === $application ) {
								$is_json = ( is_array( json_decode( $value, true ) ) ) ? true : false;

								if ( is_bool( $value ) ) {
									$value = ( $value ) ? 1 : 0;
								}

								if ( $is_json ) {
									$value = 'JSON:' . base64_encode( $value ); // @codingStandardsIgnoreLine
								}

								// Convert double quotes to HTML entities.
								$value = htmlentities( $value, ENT_QUOTES, 'UTF-8' );
								$value = str_replace( array( "\r", "\n" ), '', $value );

								$filter_conditions_encode = wp_json_encode( $step['filterConditions'] );
								$filter_conditions_encode = str_replace( $replace_tag, $value, $filter_conditions_encode );
								$filter_conditions_decode = json_decode( $filter_conditions_encode, true );

								$step['filterConditions'] = $filter_conditions_decode;
							}

							// Do the dynamic tag replacement with their values.
							$updated_value = flowmattic_dynamic_tag_values( $step, $replace_tag, $value );

							// Assign the updated data back to step object.
							$step = $updated_value;
						}
					}

					// Find and update the variables with their values in the request data.
					$step = wp_flowmattic()->variables->find_and_replace( $step );

					// Set the pattern to find dynamic tags that are not replaced due to missing data in request.
					$pattern = '/\{(?:(?!\')(?!\")[a-zA-Z0-9_-]+\d+\.[a-zA-Z0-9-_\s]+)}/';

					// Convert the step data to json, for easy replacement.
					$data_string = wp_json_encode( $step );

					// Replace the matched dynamic tags with a blank string.
					$modified_string = preg_replace( $pattern, '', $data_string );

					// Assign the modified data back to step variable.
					if ( json_decode( $modified_string, true ) ) {
						$step = json_decode( $modified_string, true );
					}

					// Iterator.
					if ( 'iterator' === $application ) {
						$fields          = $step['actionAppArgs'];
						$arrays          = $fields['iteratorArray'];
						$simple_response = isset( $fields['iterator_simple_response'] ) && 'No' === $fields['iterator_simple_response'] ? false : true;

						$next_step_id         = isset( $steps[ $i + 1 ] ) ? $steps[ $i + 1 ]['stepID'] : false;
						$step['next_step_id'] = $next_step_id;

						$is_valid_array = false;

						if ( is_array( $arrays ) ) {
							$is_valid_array = true;
						} elseif ( json_decode( $fields['iteratorArray'], true ) ) {
							$arrays         = json_decode( $fields['iteratorArray'], true );
							$is_valid_array = true;
						} elseif ( json_decode( addslashes( $fields['iteratorArray'] ), true ) ) {
							$arrays         = json_decode( addslashes( $fields['iteratorArray'] ), true );
							$is_valid_array = true;
						} elseif ( json_decode( stripslashes( $fields['iteratorArray'] ), true ) ) {
							$arrays         = json_decode( stripslashes( $fields['iteratorArray'] ), true );
							$is_valid_array = true;
						}

						$task['task_data'][] = array(
							'application'   => $application,
							'captured_data' => array(
								'array'            => $fields['iteratorArray'],
								'is_valid_array'   => $is_valid_array,
								'step_executed_at' => date_i18n( 'Y-m-d H:i:s' ),
							),
						);

						// Insert task into database.
						$tasks_db->update( $task );

						if ( ! empty( $arrays ) ) {
							do_action( 'flowmattic_execute_iterator', $workflow_id, $arrays, $response_data, $next_step_id, $task_history_id, $simple_response, $step_number );
						}

						break 2;
					}

					// If Iterator Storage.
					if ( 'iterator_storage' === $application ) {
						$step['task_history_id'] = $task_history_id;
					}

					// If Iterator End.
					if ( 'iterator_end' === $application ) {
						$task['task_data'][] = array(
							'application'   => $application,
							'step_id'       => $step['stepID'],
							'captured_data' => array(
								'status'           => 'success',
								'message'          => esc_html__( 'Iterator will stop at this action step. Next steps will be executed after the iterator is fully executed', 'flowmattic' ),
								'step_executed_at' => date_i18n( 'Y-m-d H:i:s' ),
							),
						);

						if ( ! $iterator_last_step ) {
							break 2;
						} else {
							// Insert task into database.
							$tasks_db->update( $task );

							break;
						}
					}

					// Router.
					if ( 'router' === $application ) {
						$router_steps = $step['routerSteps'];

						if ( ! empty( $router_steps ) ) {
							$r_step_number   = $step_number;
							$r_response_data = $response_data;
							foreach ( $router_steps as $route_letter => $route_actions ) {
								$router_condition   = $route_actions[0];
								$route_title        = ( isset( $step[ 'routeTitle_' . $route_letter ] ) && '' !== $step[ 'routeTitle_' . $route_letter ] ) ? $step[ 'routeTitle_' . $route_letter ] : 'Route ' . $route_letter;
								$continue_execution = true;

								// Check if router condition is true.
								$filter_conditions = ( isset( $router_condition['filterConditions'] ) && ! empty( $router_condition['filterConditions'] ) ) ? $router_condition['filterConditions'] : array();

								if ( ! empty( $filter_conditions ) ) {
									if ( class_exists( 'FlowMattic_Filter' ) ) {
										$fields = array(
											'filter_conditions' => $filter_conditions,
										);

										$filter    = new FlowMattic_Filter();
										$condition = $filter->continue_if( $fields );
										$condition = json_decode( $condition );

										if ( 'continue' === $condition->flag ) {
											$continue_execution = true;
											$app_response       = array(
												'status'  => 'success',
												'message' => esc_html__( 'Conditions meet.', 'flowmattic' ),
											);
										} else {
											$continue_execution = false;
											$app_response       = array(
												'status'  => 'success',
												'message' => esc_html__( 'Conditions does not meet.', 'flowmattic' ),
											);
										}

										$app_response['step_executed_at'] = date_i18n( 'Y-m-d H:i:s' );
									}
								}

								$task['task_data'][] = array(
									'application'   => $application,
									'captured_data' => $app_response,
									'route_title'   => $route_title,
									'route_letter'  => $route_letter,
									'step_id'       => $step['stepID'],
									'request_data'  => wp_json_encode( $fields ),
								);

								// Insert task into database.
								$tasks_db->update( $task );

								$response_data[ 'route' . $route_letter . '.filter' . $r_step_number ] = $app_response;

								$router_step_number = $r_step_number + 1;

								// Remove the filter condition.
								unset( $route_actions[0] );

								if ( $continue_execution ) {
									$router_app = new FlowMattic_Router();
									$router_id  = $step['stepID'];
									$task       = $router_app->execute_router( $workflow_id, $route_actions, $response_data, $task, $router_step_number, $router_id );
								}
							}

							// Insert task into database.
							$tasks_db->update( $task );

							$r_step_number = $r_step_number + 1;
						}

						break;
					}

					// FlowMattic Tools -> Redirect.
					if ( 'tools' === $application && 'redirect' === $step['action'] ) {
						$redirect_url = $step['actionAppArgs']['redirect_url'];

						$task['task_data'][] = array(
							'application'   => $application,
							'step_id'       => $step['stepID'],
							'captured_data' => array(
								'status'           => 'success',
								'message'          => esc_html__( 'Redirecting to the URL', 'flowmattic' ),
								'redirect_to'      => $redirect_url,
								'step_executed_at' => date_i18n( 'Y-m-d H:i:s' ),
							),
						);

						// Insert task into database.
						$tasks_db->update( $task );

						// Redirect to the URL.
						wp_redirect( $redirect_url ); // @codingStandardsIgnoreLine
						exit;
					}

					// Check if it is a custom app.
					if ( preg_match( '/^(custom_app|app_)/', $application ) ) {
						$app_class = 'FlowMattic_Custom_User_App';
					} else {
						$app_class = ucwords( str_replace( array( '-', '_' ), ' ', $application ) );
						$app_class = str_replace( ' ', '_', $app_class );
						$app_class = 'FlowMattic_' . $app_class;
					}

					if ( class_exists( $app_class ) ) {
						$app_class_instance = new $app_class();

						// Check if app has method to validate workflow.
						if ( method_exists( $app_class_instance, 'validate_workflow_step' ) ) {
							$is_workflow_executable   = true;
							$workflow_step_validation = $app_class_instance->validate_workflow_step( $workflow_id, $step, $capture_data );

							if ( is_object( $workflow_step_validation ) ) {
								if ( isset( $workflow_step_validation->flag ) && 'continue' === $workflow_step_validation->flag ) {
									$is_workflow_executable = true;
								} else {
									$is_workflow_executable = false;
								}

								$app_response = $workflow_step_validation;
							} elseif ( ! $workflow_step_validation ) {
								$is_workflow_executable = false;
								$app_response           = array(
									'status'  => 'error',
									'message' => esc_html__( 'Workflow step validation not passed', 'flowmattic' ),
								);
							}

							if ( ! $is_workflow_executable ) {

								$response_data[ $application . $step_number ] = $app_response;

								// Check if the request data method exists.
								$request_data = '';
								if ( method_exists( $app_class_instance, 'get_request_data' ) ) {
									$request_data = $app_class_instance->get_request_data();
								}

								// Get the response array.
								$app_response_array = is_array( $app_response ) || is_object( $app_response ) ? (array) $app_response : json_decode( $app_response );

								// Add the step execution time.
								$app_response_array['step_executed_at'] = date_i18n( 'Y-m-d H:i:s' );

								// Encode the response back to JSON.
								$app_response = wp_json_encode( $app_response_array );

								$task['task_data'][] = array(
									'application'   => $application,
									'captured_data' => $app_response,
									'step_id'       => $step['stepID'],
									'request_data'  => is_array( $request_data ) ? wp_json_encode( $request_data ) : $request_data,
								);

								// Update task into database.
								$tasks_db->update( $task );

								$next_step_flag = false;
								break 2;
							}
						}

						$continue_execution = true;
						// Check if conditional execution is turned ON.
						if ( isset( $step['conditional_execution'] ) && 'Yes' === $step['conditional_execution'] ) {
							$filter_conditions = ( isset( $step['filterConditions'] ) && ! empty( $step['filterConditions'] ) ) ? $step['filterConditions'] : array();
							if ( ! empty( $filter_conditions ) ) {
								if ( class_exists( 'FlowMattic_Filter' ) ) {
									$fields = array(
										'filter_conditions' => $filter_conditions,
									);

									$filter    = new FlowMattic_Filter();
									$condition = $filter->continue_if( $fields );
									$condition = json_decode( $condition );

									if ( 'continue' === $condition->flag ) {
										$continue_execution = true;
									} else {
										$continue_execution = false;
										$app_response       = array(
											'status'  => 'success',
											'message' => esc_html__( 'Conditions does not meet. This step will not be executed.', 'flowmattic' ),
										);
									}
								}
							}
						}

						if ( $continue_execution ) {
							// If application has both - trigger and action, run the action step.
							if ( method_exists( $app_class_instance, 'run_action_step' ) ) {
								$app_response = $app_class_instance->run_action_step( $workflow_id, $step, $capture_data );
							} else {
								$app_response = $app_class_instance->run_workflow_step( $workflow_id, $step, $capture_data );
							}

							$app_response = ( $app_response ) ? json_decode( $app_response, true ) : array( 'response' => esc_attr__( 'No response received', 'flowmattic' ) );
						}

						$response_array = $app_response;
						if ( is_array( $app_response ) ) {
							foreach ( $app_response as $key => $value ) {
								if ( is_array( $value ) ) {
									$response_array = flowmattic_recursive_array( $response_array, $key, $value );
								} else {
									$response_array[ $key ] = $value;
								}
							}
						}

						$response_decode = $response_array;
						$action_event    = $step['action'];

						// Apply filters to the response.
						$updated_response = apply_filters( 'flowmattic_action_step_response_' . $application, $response_decode, $action_event );

						// Update the response with the modified data, if any.
						$response_array = $updated_response;

						if ( 'router' !== $application ) {
							$response_data[ $application . $step_number ] = $response_array;
						} else {
							--$step_number;
						}

						// Check if the request data method exists.
						$request_data = '';
						if ( method_exists( $app_class_instance, 'get_request_data' ) ) {
							$request_data = $app_class_instance->get_request_data();
						}

						// Check if ignore error is set.
						$is_ignore_error = ( isset( $step['ignore_errors'] ) && 'Yes' === $step['ignore_errors'] ) ? true : false;

						// If ignore error is set and is true, change the status to success.
						if ( $is_ignore_error ) {
							if ( isset( $app_response['status'] ) && 'error' === trim( strtolower( $app_response['status'] ) ) ) {
								$app_response['status'] = 'success';
							}
						}

						// Get the response array.
						$app_response_array = is_array( $app_response ) || is_object( $app_response ) ? (array) $app_response : json_decode( $app_response );

						// Add the step execution time.
						$app_response_array['step_executed_at'] = date_i18n( 'Y-m-d H:i:s' );

						// Encode the response back to JSON.
						$app_response = wp_json_encode( $app_response_array );

						$task['task_data'][] = array(
							'application'   => $application,
							'captured_data' => $app_response,
							'step_id'       => $step['stepID'],
							'request_data'  => is_array( $request_data ) ? wp_json_encode( $request_data ) : $request_data,
						);

						// Update task into database.
						$tasks_db->update( $task );

						// Check if task is failed.
						$task_failed = false;
						if ( isset( $app_response['status'] ) && 'error' === trim( strtolower( $app_response['status'] ) ) ) {
							$task_failed = true;
						} elseif ( '0' === $app_response ) {
							$task_failed = true;
						}

						// Set the task failed flag to true, if ignore error is set to true.
						if ( $is_ignore_error ) {
							$task_failed = false;
						}

						if ( $task_failed ) {
							$task_email_body .= '<li>Application - <strong>' . $application . '</strong>';
							$task_email_body .= "\n <pre>" . wp_json_encode( $app_response ) . "</pre> \n";
							$task_email_body .= '</li>';
						}
					}

					break;
			}

			$step_number = $step_number + 1;
		}

		// Start the background process.
		if ( $task_queued ) {
			$this->background_process->save()->dispatch();
		}

		// Get the settings.
		$settings = get_option( 'flowmattic_settings', array() );

		// If notifications are turned on, and the task is failed, notify the user.
		if ( ( isset( $settings['enable_notifications'] ) && 'yes' === $settings['enable_notifications'] ) && '' !== $task_email_body ) {
			if ( '' !== $settings['notification_email'] ) {
				$notification_email = $settings['notification_email'];

				$email_body  = '<p>' . esc_html__( 'Hello,', 'flowmattic' ) . '</p>' . PHP_EOL;
				$email_body .= 'Your workflow <strong>' . $workflow->workflow_name . '</strong> is failed while the following application steps being executed. Here\'s the response we got from the application -';
				$email_body .= "\n<br> <ul>" . $task_email_body . "</ul> \n";
				$email_body .= "\n<br>Workflow URL - <a href='" . admin_url( '/admin.php?page=flowmattic-workflows&flowmattic-action=edit&workflow-id=' . $workflow->workflow_id ) . "'>" . admin_url( '/admin.php?page=flowmattic-workflows&flowmattic-action=edit&workflow-id=' . $workflow->workflow_id ) . "</a> \n";
				$email_body .= "\n<br>" . esc_html__( 'Please check the task history for more details.', 'flowmattic' ) . "\n";
				$email_body .= '<p><i>' . esc_html__( 'This is an automated email, please do not reply to this email.', 'flowmattic' ) . '</i></p>' . PHP_EOL . PHP_EOL;
				$email_body .= '<p>' . esc_html__( 'Regards,', 'flowmattic' ) . PHP_EOL;
				$email_body .= esc_html__( 'FlowMattic Team', 'flowmattic' ) . '</p>' . PHP_EOL;

				$email_body = wpautop( $email_body );

				$to      = $notification_email;
				$subject = esc_html__( 'FlowMattic: Workflow failed notification for site - ', 'flowmattic' ) . wp_parse_url( get_site_url(), PHP_URL_HOST );
				$body    = $email_body;
				$headers = array( 'Content-Type: text/html; charset=UTF-8' );

				wp_mail( $to, $subject, $body, $headers );
			}
		}
	}

	/**
	 * Process the ajax to save the workflow.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function flowmattic_save_workflow() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		$decode_method = 'base64_decode';

		$workflow = isset( $_POST['workflow'] ) ? json_decode( $decode_method( $_POST['workflow'] ), true ) : '';
		$settings = isset( $_POST['settings'] ) ? json_decode( $decode_method( $_POST['settings'] ), true ) : '';

		$cron_workflow_id = $settings['workflow_id'];

		// Get the workflow.
		$p_args = array(
			'workflow_id' => $cron_workflow_id,
		);

		$p_workflow          = wp_flowmattic()->workflows_db->get( $p_args );
		$p_workflow_settings = ( isset( $p_workflow->workflow_settings ) && ! empty( $p_workflow->workflow_settings ) ) ? json_decode( $p_workflow->workflow_settings ) : array();

		// Unschedule the previous workflow event, if trigger is changed, remove the existing scheduled event.
		foreach ( _get_cron_array() as $timestamp => $cron_event ) {
			if ( isset( $cron_event['flowmattic_workflow_cron'] ) ) {
				foreach ( $cron_event['flowmattic_workflow_cron'] as $key => $cron_settings ) {
					if ( $cron_workflow_id === $cron_settings['args']['workflow_id'] ) {
						wp_unschedule_event( $timestamp, 'flowmattic_workflow_cron', $cron_settings['args'] );
					}
				}
			}
		}

		// Fix API parameters missing on some hosts.
		foreach ( $workflow as $key => $workflow_step ) {
			if ( isset( $workflow_step['application'] ) ) {
				if ( 'api' === $workflow_step['application'] ) {
					if ( isset( $workflow_step['actionAppArgs'] ) && ! isset( $workflow_step['api_parameters'] ) ) {
						$workflow[ $key ]['api_parameters'] = $workflow_step['actionAppArgs'];
					}
				}

				// Fix API parameters for routers.
				if ( 'router' === $workflow_step['application'] ) {
					$router_steps = $workflow_step['routerSteps'];
					foreach ( $router_steps as $step_letter => $router_step ) {
						foreach ( $router_step as $step_index => $router_step_data ) {
							if ( 'api' === $router_step_data['application'] ) {
								if ( isset( $router_step_data['actionAppArgs'] ) && ! isset( $router_step_data['api_parameters'] ) ) {
									$workflow[ $key ]['routerSteps'][ $step_letter ][ $step_index ]['api_parameters'] = $router_step_data['actionAppArgs'];
								}
							}
						}
					}
				}
			}

			if ( 'trigger' === $workflow_step['type'] ) {
				$workflow_data = $workflow_step;
			}
		}

		// Check and set cron for the scheduled workflow.
		$workflow_application = isset( $workflow_data['application'] ) ? $workflow_data['application'] : '';

		// Check and remove the action hook if no plugin actions trigger is used.
		if ( 'trigger' === $workflow_data['type'] && 'plugin_actions' !== $workflow_application ) {
			// Get the workflow hooks registered.
			$workflow_hooks = get_option( 'flowmattic_workflow_hooks', array() );

			// Remove the action hook for the current workflow.
			unset( $workflow_hooks[ $cron_workflow_id ] );

			// Update the workflow hooks to database.
			update_option( 'flowmattic_workflow_hooks', $workflow_hooks, false );
		}

		if ( 'trigger' === $workflow_data['type'] && 'schedule' === $workflow_application ) {
			$scheduled_workflows = get_option( 'flowmattic_scheduled_workflows', array() );
			$action              = $workflow_data['action'];
			$weekend_trigger     = false;

			if ( isset( $workflow_data['applicationSettings'] ) && ! empty( $workflow_data['applicationSettings'] ) ) {
				$action_settings = $workflow_data['applicationSettings'];

				$minutes_time_key = array_search( 'minutes', array_column( $action_settings, 'name' ), true );
				$minutes_time     = $action_settings[ $minutes_time_key ]['value'];

				$day_time_key = array_search( 'day_time', array_column( $action_settings, 'name' ), true );
				$day_time     = $action_settings[ $day_time_key ]['value'];

				$time_difference   = ( strtotime( get_date_from_gmt( $day_time ) ) - strtotime( date_i18n( 'H:i:s', strtotime( $day_time ) ) ) );
				$timezone_day_time = ( date_i18n( 'H:i:s', ( strtotime( date_i18n( 'H:i:s', strtotime( $day_time ) ) ) - $time_difference ) ) );

				$week_day_key = array_search( 'week_day', array_column( $action_settings, 'name' ), true );
				$week_day     = $action_settings[ $week_day_key ]['value'];

				$month_day_key = array_search( 'month_day', array_column( $action_settings, 'name' ), true );
				$month_day     = $action_settings[ $month_day_key ]['value'];

				$weekend_trigger_key = array_search( 'weekend_trigger', array_column( $action_settings, 'name' ), true );
				$weekend_trigger     = $action_settings[ $weekend_trigger_key ]['value'];
			}

			$schedule_workflow = array(
				'workflow_id'     => $cron_workflow_id,
				'weekend_trigger' => $weekend_trigger,
			);

			if ( 'on' === $settings['status'] ) {
				switch ( $action ) {
					case 'minutes':
						$interval = 'flowmattic_every_' . $minutes_time . '_minutes';

						// Schedule the new workflow event with updated time.
						wp_schedule_event( time(), $interval, 'flowmattic_workflow_cron', $schedule_workflow );

						break;

					case 'hour':
						// Schedule the new workflow event with updated time.
						wp_schedule_event( time(), 'hourly', 'flowmattic_workflow_cron', $schedule_workflow );

						break;

					case 'day':
						// Schedule the new workflow event with updated time.
						wp_schedule_event( strtotime( $timezone_day_time ), 'daily', 'flowmattic_workflow_cron', $schedule_workflow );

						break;

					case 'week':
						// Make sure the workflow runs on weekends as well.
						$schedule_workflow['weekend_trigger'] = true;

						// Set the day of the week for the cron.
						$schedule_workflow['week_day'] = $week_day;

						// Schedule the new workflow event with updated time.
						wp_schedule_event( strtotime( $timezone_day_time ), 'daily', 'flowmattic_workflow_cron', $schedule_workflow );

						break;

					case 'month':
						// Make sure the workflow runs on weekends as well.
						$schedule_workflow['weekend_trigger'] = true;

						// Set the 3rd argument blank.
						$schedule_workflow['week_day'] = false;

						// Set the day of the month for the cron.
						$schedule_workflow['month_day'] = $month_day;

						// Schedule the new workflow event with updated time.
						wp_schedule_event( strtotime( $timezone_day_time ), 'daily', 'flowmattic_workflow_cron', $schedule_workflow );

						break;
				}

				$scheduled_workflows[ $cron_workflow_id ] = $schedule_workflow;

				update_option( 'flowmattic_scheduled_workflows', $scheduled_workflows, false );
			}
		}

		$steps_data = $workflow;
		foreach ( $steps_data as $steps => $step_data ) {
			if ( 'trigger' === $step_data['type'] && isset( $step_data['application'] ) ) {
				if ( strtolower( 'WordPress' ) === $step_data['application'] && 'page_view' === $step_data['action'] ) {
					if ( 'on' === $settings['status'] ) {
						update_option( 'wp_page_view_trigger', true );
					} else {
						update_option( 'wp_page_view_trigger', false );
					}
				}

				break;
			}
		}

		// Get the settings assigned to temp. variable to avoid overwriting.
		$workflow_settings = $settings;

		// Set the workflow settings.
		$workflow_settings['folder']            = $settings['folder'];
		$workflow_settings['description']       = $settings['description'];
		$workflow_settings['status']            = $settings['status'];
		$workflow_settings['user_email']        = $settings['user_email'];
		$workflow_settings['webhook_queue']     = isset( $settings['webhook_queue'] ) ? $settings['webhook_queue'] : 'disabled';
		$workflow_settings['workflow_auth_key'] = isset( $settings['workflow_auth_key'] ) ? $settings['workflow_auth_key'] : '';
		$workflow_settings['time']              = date_i18n( 'd-m-Y h:i:s A' );

		// If stored response is available, update the settings.
		if ( isset( $p_workflow_settings->stored_response ) ) {
			$workflow_settings['stored_response'] = $p_workflow_settings->stored_response;
		}

		$workflow_data = array(
			'workflow_id'       => $settings['workflow_id'],
			'workflow_name'     => $settings['name'],
			'workflow_steps'    => $workflow,
			'workflow_settings' => $workflow_settings,
		);

		$workflow_db = wp_flowmattic()->workflows_db;
		$status      = $workflow_db->insert( $workflow_data );

		$reply = array(
			'status' => $status,
		);

		echo wp_json_encode( $reply );

		die();
	}

	/**
	 * Process the ajax to delete the workflow.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function flowmattic_delete_workflow() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		if ( isset( $_POST['workflow_id'] ) ) {
			$workflow_id   = $_POST['workflow_id'];
			$workflow_data = array(
				'workflow_id' => $workflow_id,
			);

			$workflow_db = wp_flowmattic()->workflows_db;
			$workflow_db->delete( $workflow_data );

			$tasks_db = wp_flowmattic()->tasks_db;
			$tasks_db->delete( $workflow_data );

			$reply = array(
				'status' => 'success',
			);

			// Unschedule the workflow event, in case the polling is enabled.
			foreach ( _get_cron_array() as $timestamp => $cron_event ) {
				if ( isset( $cron_event['flowmattic_workflow_polling_cron'] ) ) {
					foreach ( $cron_event['flowmattic_workflow_polling_cron'] as $key => $cron_settings ) {
						if ( $workflow_id === $cron_settings['args']['workflow_id'] ) {
							wp_unschedule_event( $timestamp, 'flowmattic_workflow_polling_cron', $cron_settings['args'] );
							break 2;
						}
					}
				}
			}

			// Fire an action for apps.
			do_action( 'flowmattic_workflow_deleted', $workflow_id );

			echo wp_json_encode( $reply );
		} else {
			$reply = array(
				'status'  => 'error',
				'message' => esc_html__( 'Workflow ID missing', 'flowmattic' ),
			);

			echo wp_json_encode( $reply );
		}

		die();
	}

	/**
	 * Process the ajax to delete the task history for workflow.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function flowmattic_delete_workflow_task_history() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		if ( isset( $_POST['workflow_id'] ) ) {
			$workflow_id   = $_POST['workflow_id'];
			$workflow_data = array(
				'workflow_id' => $workflow_id,
			);

			$tasks_db = wp_flowmattic()->tasks_db;
			$tasks_db->delete( $workflow_data );

			$reply = array(
				'status' => 'success',
			);

			echo wp_json_encode( $reply );
		} else {
			$reply = array(
				'status'  => 'error',
				'message' => esc_html__( 'Workflow ID missing', 'flowmattic' ),
			);

			echo wp_json_encode( $reply );
		}

		die();
	}

	/**
	 * Process the ajax to save and test the action step.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function flowmattic_save_test_action_step() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		$response = wp_json_encode(
			array(
				'status'  => 'error',
				'message' => esc_html__( 'Something went wrong!', 'flowmattic' ),
			)
		);

		$application = isset( $_POST['application'] ) ? $_POST['application'] : '';

		if ( '' === $application ) {
			$response = wp_json_encode(
				array(
					'status'  => 'error',
					'message' => esc_html__( 'Application is missing in the request.', 'flowmattic' ),
				)
			);
		} else {
			// Check if it is a custom app.
			if ( preg_match( '/^(custom_app|app_)/', $application ) ) {
				$app_class = 'FlowMattic_Custom_User_App';
			} else {
				$app_class = ucwords( str_replace( array( '-', '_' ), ' ', $application ) );
				$app_class = str_replace( ' ', '_', $app_class );
				$app_class = 'FlowMattic_' . $app_class;
			}

			if ( class_exists( $app_class ) ) {
				$app_class_instance = new $app_class();

				// Check if app has method to test action step.
				if ( method_exists( $app_class_instance, 'test_event_action' ) ) {
					$event_data  = $_POST;
					$workflow_id = $event_data['workflow_id'];
					$step_ids    = $event_data['stepIDs'];
					$settings    = $event_data['settings'];
					$fields      = isset( $event_data['fields'] ) ? $event_data['fields'] : ( isset( $settings['actionAppArgs'] ) ? $settings['actionAppArgs'] : array() );

					// Get capture data from all steps.
					$workflow_captures = flowmattic_get_workflow_captures( $workflow_id );

					// Set internal step IDs for routers.
					if ( isset( $workflow_captures['routers'] ) ) {
						$router_captures = $workflow_captures['routers'];
						foreach ( $router_captures as $router_step_id => $internal_step_ids ) {
							foreach ( $internal_step_ids as $key => $internal_step ) {
								// Append step ID in tag to avoid conflicts.
								$step_ids[ $internal_step['tag'] . '-' . $internal_step['stepID'] ] = $internal_step['stepID'];
							}
						}

						unset( $workflow_captures['routers'] );
					}

					if ( ! empty( $step_ids ) ) {
						foreach ( $step_ids as $tag => $id ) {
							// Replace the step ID in tag that we added for router internal steps.
							$tag = str_replace( '-' . $id, '', $tag );

							if ( isset( $workflow_captures[ $id ] ) ) {
								$step_capture = $workflow_captures[ $id ];

								if ( isset( $step_capture['webhook_capture'] ) ) {
									$step_capture = $step_capture['webhook_capture'];
								}

								if ( ! is_array( $step_capture ) ) {
									$step_capture = json_decode( $step_capture, true );
								}

								if ( ! is_array( $step_capture ) ) {
									continue;
								}

								foreach ( $step_capture as $key => $value ) {
									$replace_tag = '{' . $tag . '.' . $key . '}';

									if ( is_array( $value ) ) {
										if ( 1 !== count( $value ) && isset( $value[0] ) ) {
											$value = $value[0];
										}

										// Repalce dynamic data in action fields.
										$updated_fields = flowmattic_dynamic_tag_values( $fields, $replace_tag, $value );
										$fields         = $updated_fields;

										// Repalce dynamic data in main event data.
										$updated_value = flowmattic_dynamic_tag_values( $event_data, $replace_tag, $value );
										$event_data    = $updated_value;
										continue;
									}

									// If is null, assign empty string.
									$value = ( null === $value ) ? '' : str_replace( array( "\r" ), '', $value );

									if ( 'filter' === $application ) {
										$is_json = ( is_array( json_decode( $value, true ) ) ) ? true : false;

										if ( is_bool( $value ) ) {
											$value = ( $value ) ? 1 : 0;
										}

										if ( $is_json ) {
											$value = 'JSON:' . base64_encode( $value ); // @codingStandardsIgnoreLine
										}

										$filter_conditions_encode = wp_json_encode( $event_data['settings']['filterConditions'] );
										$filter_conditions_encode = str_replace( $replace_tag, $value, $filter_conditions_encode );
										$filter_conditions_decode = json_decode( $filter_conditions_encode, true );

										if ( is_array( $filter_conditions_decode ) ) {
											$event_data['settings']['filterConditions'] = $filter_conditions_decode;
										}
									}

									// Repalce dynamic data in action fields.
									$updated_fields = flowmattic_dynamic_tag_values( $fields, $replace_tag, $value );
									$fields         = $updated_fields;

									// Repalce dynamic data in main event data.
									$updated_value = flowmattic_dynamic_tag_values( $event_data, $replace_tag, $value );
									$event_data    = $updated_value;
								}
							}
						}
					}

					$data           = $event_data;
					$data['fields'] = $fields;

					$continue_execution = true;
					// Check if conditional execution is turned ON.
					if ( isset( $data['settings']['conditional_execution'] ) && 'Yes' === $data['settings']['conditional_execution'] ) {
						$filter_conditions = ( isset( $data['settings']['filterConditions'] ) && ! empty( $data['settings']['filterConditions'] ) ) ? $data['settings']['filterConditions'] : array();
						if ( ! empty( $filter_conditions ) ) {
							if ( class_exists( 'FlowMattic_Filter' ) ) {
								$fields = array(
									'filter_conditions' => $filter_conditions,
								);

								$filter    = new FlowMattic_Filter();
								$condition = $filter->continue_if( $fields );
								$condition = json_decode( $condition );

								if ( 'continue' === $condition->flag ) {
									$continue_execution = true;
								} else {
									$continue_execution = false;
									$response           = wp_json_encode(
										array(
											'status'  => 'success',
											'message' => esc_html__( 'Conditions does not meet. This step will not be executed.', 'flowmattic' ),
										)
									);
								}
							}
						}
					}

					// Find and update the variables with their values in the request data.
					$data = wp_flowmattic()->variables->find_and_replace( $data );

					// Set the pattern to find dynamic tags that are not replaced due to missing data in request.
					$pattern = '/\{(?:(?!\')(?!\")[a-zA-Z0-9_-]+\d+\.[a-zA-Z0-9-_\s]+)}/';

					// Convert the data to be sent to app to json, for easy replacement.
					$data_string = wp_json_encode( $data );

					// Replace the matched dynamic tags with a blank string.
					$modified_string = preg_replace( $pattern, '', $data_string );

					// Assign the modified data back.
					$updated_data = json_decode( $modified_string, true );

					if ( $continue_execution ) {
						$response = $app_class_instance->test_event_action( $updated_data );

						$response_decode = json_decode( $response, true );
						$action_event    = $updated_data['event'];

						// Apply filters to the response.
						$updated_response = apply_filters( 'flowmattic_action_step_response_' . $application, $response_decode, $action_event );

						// Update the response with the modified data, if any.
						$response = wp_json_encode( $updated_response );
					}
				}
			} else {
				$response = wp_json_encode(
					array(
						'status'  => 'error',
						// translators: Application name.
						'message' => sprintf( esc_html__( 'Application %s is not active or installed.', 'flowmattic' ), $application ),
					)
				);
			}
		}

		echo $response; // @codingStandardsIgnoreLine

		die();
	}

	/**
	 * Re-execute the workflow.
	 *
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	public function flowmattic_execute_workflow() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		$workflow_id   = isset( $_POST['workflow_id'] ) ? sanitize_text_field( wp_unslash( $_POST['workflow_id'] ) ) : '';
		$captured_data = isset( $_POST['captured_data'] ) ? $_POST['captured_data'] : array(); // @codingStandardsIgnoreLine

		// Add current date and time in d-m-Y H:i:s format to the captured data.
		$captured_data['workflow_re_executed_at'] = date_i18n( 'd-m-Y H:i:s' );

		// Run the workflow.
		$flowmattic_workflow = new FlowMattic_Workflow();
		$flowmattic_workflow->run( $workflow_id, $captured_data, array(), array(), true );

		$response = wp_json_encode(
			array(
				'status' => 'success',
			)
		);

		echo $response; // @codingStandardsIgnoreLine

		die();
	}

	/**
	 * Re-execute the workflows in bulk
	 *
	 * @access public
	 * @since 4.3.0
	 * @return void
	 */
	public function flowmattic_bulk_execute_workflows() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		// Get the workflows to execute.
		$workflows = isset( $_POST['workflows'] ) ? $_POST['workflows'] : '';

		if ( '' === $workflows ) {
			die();
		}

		foreach ( $workflows as $key => $workflow ) {
			$workflow_id   = $workflow['workflow_id'];
			$captured_data = $workflow['captured_data'];

			// Add current date and time in d-m-Y H:i:s format to the captured data.
			$captured_data['workflow_re_executed_at'] = date_i18n( 'd-m-Y H:i:s' );

			// Fix the slashes in captured data.
			foreach ( $captured_data as $key => $value ) {
				if ( ! is_array( $value ) && $value ) {
					$captured_data[ $key ] = json_decode( $value ) ? $value : stripslashes( $value );
				}
			}

			// Run the workflow.
			$flowmattic_workflow = new FlowMattic_Workflow();
			$flowmattic_workflow->run( $workflow_id, $captured_data, array(), array(), true );

			// Wait a sec. to let the server breath a little.
			flowmattic_delay( 1 );
		}

		$response = wp_json_encode(
			array(
				'status' => 'success',
			)
		);

		echo $response; // @codingStandardsIgnoreLine
		die();
	}

	/**
	 * Delete task history items in bulk.
	 *
	 * @access public
	 * @since 4.3.0
	 * @return void
	 */
	public function flowmattic_bulk_delete_tasks() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		// Get the tasks to execute.
		$tasks = isset( $_POST['tasks'] ) ? $_POST['tasks'] : '';

		if ( '' === $tasks ) {
			die();
		}

		foreach ( $tasks as $key => $task ) {
			// Delete the task from history.
			$tasks_db = wp_flowmattic()->tasks_db;
			$tasks_db->delete_by_task_id( $task );
		}

		$response = wp_json_encode(
			array(
				'status' => 'success',
			)
		);

		echo $response; // @codingStandardsIgnoreLine
		die();
	}

	/**
	 * Process the ajax to cancel the delayed task.
	 *
	 * @access public
	 * @since 4.3.1
	 * @return void
	 */
	public function flowmattic_cancel_delay() {
		check_ajax_referer( 'flowmattic_workflow_nonce', 'workflow_nonce' );

		$task_history_id = isset( $_POST['task_id'] ) ? sanitize_text_field( wp_unslash( $_POST['task_id'] ) ) : '';
		$next_step_id    = isset( $_POST['next_step_id'] ) ? sanitize_text_field( wp_unslash( $_POST['next_step_id'] ) ) : '';
		$workflow_id     = isset( $_POST['workflow_id'] ) ? sanitize_text_field( wp_unslash( $_POST['workflow_id'] ) ) : '';

		$response = wp_json_encode(
			array(
				'status' => 'success',
			)
		);

		// If task ID is not set, return.
		if ( '' === $task_history_id ) {
			$response = wp_json_encode(
				array(
					'status'  => 'error',
					'message' => esc_html__( 'Task ID is missing.', 'flowmattic' ),
				)
			);

			echo $response; // @codingStandardsIgnoreLine

			die();
		}

		// Prepare the cron event data.
		$cron_event_data = array(
			$task_history_id,
			$next_step_id,
			$workflow_id,
		);

		// Get the cron schedule.
		$next_scheduled = wp_next_scheduled( 'flowmattic_delay_workflow_step', $cron_event_data );

		// If the event is scheduled, unschedule it.
		if ( $next_scheduled ) {
			$unscheduled = wp_unschedule_event( $next_scheduled, 'flowmattic_delay_workflow_step', $cron_event_data, true );

			if ( is_wp_error( $unscheduled ) ) {
				$response = wp_json_encode(
					array(
						'status'  => 'error',
						'message' => esc_html__( 'Error cancelling the delay.', 'flowmattic' ),
						'error'   => $unscheduled->get_error_message(),
					)
				);
			} else {
				// Update the task history item in database.
				$tasks_db     = wp_flowmattic()->tasks_db;
				$current_task = $tasks_db->get_task_by_id( $task_history_id );
				$task_data    = json_decode( $current_task[0]->task_data, true );

				foreach ( $task_data as $key => $task ) {
					if ( isset( $task['cron_data']['next_step_id'] ) && $task['cron_data']['next_step_id'] === $next_step_id ) {
						$task_data[ $key ]['captured_data']['status'] = 'cancelled';
						$task_data[ $key ]['captured_data']['update'] = esc_html__( 'Delay was cancelled at ', 'flowmattic' ) . date_i18n( 'd-m-Y H:i:s' );
					}
				}

				$tasks_db->update( array( 'task_data' => $task_data, 'task_id' => $task_history_id ) );
			}
		}

		echo $response; // @codingStandardsIgnoreLine
		die();
	}

	/**
	 * Process the ajax to update the workflow status.
	 *
	 * @access public
	 * @since 4.3.0
	 * @return void
	 */
	public function update_workflow_status() {
		check_ajax_referer( 'flowmattic_workflow_update_nonce', 'nonce' );

		$workflow_id = isset( $_POST['workflow_id'] ) ? sanitize_text_field( wp_unslash( $_POST['workflow_id'] ) ) : '';
		$status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		// If workflow ID is not set, return.
		if ( '' === $workflow_id ) {
			$response = wp_json_encode(
				array(
					'status'  => 'error',
					'message' => esc_html__( 'Workflow ID is missing.', 'flowmattic' ),
				)
			);

			echo $response; // @codingStandardsIgnoreLine

			die();
		}

		// Change the workflow status.
		$this->change_workflow_status( $workflow_id, $status );

		$response = wp_json_encode(
			array(
				'status' => 'success',
			)
		);

		echo $response; // @codingStandardsIgnoreLine

		die();
	}

	/**
	 * Run the scheduled cron to cleanup the tasks automatically.
	 *
	 * @access public
	 * @since 1.0
	 * @param string $clean_before Delete the tasks befoer set time.
	 * @return void
	 */
	public function run_task_cleanup_cron( $clean_before ) {
		// Get the settings.
		$settings = get_option( 'flowmattic_settings', array() );
		$interval = isset( $settings['task_clean_interval'] ) ? $settings['task_clean_interval'] : 90;

		$tasks_db = wp_flowmattic()->tasks_db;
		$tasks_db->delete_before( $interval );
	}

	/**
	 * Process the webhook queue.
	 *
	 * @access public
	 * @since 3.0
	 * @param bool   $task_history_id Flag to run the workflow on weekends.
	 * @param bool   $next_step_id    Flag to run the workflow on week days.
	 * @param string $workflow_id     Workflow ID of the workflow being executed.
	 * @return void
	 */
	public function process_webhook_queue( $task_history_id, $next_step_id, $workflow_id ) {
		// Initialize the array.
		$workflow_data = array(
			'task_history_id' => $task_history_id,
			'step_id'         => $next_step_id,
			'workflow_id'     => $workflow_id,
		);

		// Execute the workflow.
		$flowmattic_workflow = new FlowMattic_Workflow();
		$flowmattic_workflow->run( $workflow_id, array(), $workflow_data );
	}

	/**
	 * Change the workflow status.
	 *
	 * @access public
	 * @since 4.2.0
	 * @param string $workflow_id Workflow ID.
	 * @param string $status      Status to be updated.
	 * @return bool
	 */
	public function change_workflow_status( $workflow_id, $status ) {
		// Get the workflow.
		$args = array(
			'workflow_id' => $workflow_id,
		);

		$workflow = wp_flowmattic()->workflows_db->get( $args );

		// If workflow is not found, return.
		if ( ! $workflow ) {
			return false;
		}

		$workflow_steps     = json_decode( $workflow->workflow_steps, true );
		$settings           = json_decode( $workflow->workflow_settings, true );
		$settings['status'] = $status;

		$workflow_data = array(
			'workflow_id'       => $workflow->workflow_id,
			'workflow_name'     => $workflow->workflow_name,
			'workflow_steps'    => $workflow_steps,
			'workflow_settings' => $settings,
		);

		$workflow_db = wp_flowmattic()->workflows_db;
		$workflow_db->update( $workflow_data );

		return true;
	}

	/**
	 * Execute the workflow.
	 *
	 * @access public
	 * @since 4.3.0
	 * @param string $application   Application slug.
	 * @param string $trigger       Trigger step.
	 * @param array  $response_data Response data.
	 * @param array  $conditions    Conditions to be checked.
	 * @return void
	 */
	public function execute( $application, $trigger, $response_data, $conditions = array() ) {
		$workflow_live_id = get_option( 'webhook-capture-live', false );
		$app_action_live  = get_option( 'webhook-capture-app-action', false );
		$application_live = get_option( 'webhook-capture-application', false );

		// Get the workflows.
		$workflows = wp_flowmattic()->workflows_db->get_workflow_by_trigger_application( $application );

		if ( ! empty( $workflows ) ) {
			foreach ( $workflows as $key => $workflow ) {
				$workflow_steps = json_decode( $workflow->workflow_steps );

				// Loop through each workflow to check the action event.
				if ( ! empty( $workflow_steps ) ) {
					foreach ( $workflow_steps as $step ) {

						if ( 'trigger' !== $step->type ) {
							continue;
						}

						if ( $application !== $step->application ) {
							break;
						}

						$continue_execution = true;

						// CHeck if conditions are provided, and they meet.
						if ( ! empty( $conditions ) ) {

							// Get the check condition.
							$condition_type = isset( $conditions['condition'] ) ? $conditions['condition'] : 'AND';

							// Set execution flag.
							$continue_execution = false;

							unset( $conditions['condition'] );

							$step_data = (array) $step;

							foreach ( $conditions as $trigger_field => $condition ) {
								$trigger_value  = ( isset( $step_data[ $trigger_field ] ) ) ? (string) $step_data[ $trigger_field ] : '';
								$captured_value = ( isset( $response_data[ $condition['field'] ] ) ) ? ( is_array( $response_data[ $condition['field'] ] ) ? $response_data[ $condition['field'] ][0] : (string) $response_data[ $condition['field'] ] ) : '';
								$operator       = ( isset( $condition['operator'] ) ) ? $condition['operator'] : 'equals';

								// If the operator is dynamic, get the value from the step data.
								if ( 'dynamic' === $condition['operator'] ) {
									$operator = ( isset( $step_data['condition'] ) ) ? $step_data['condition'] : 'equals';
								}

								// If the trigger value is "any", set the captured value to "any".
								if ( 'any' === $trigger_value ) {
									$captured_value = 'any';
								}

								// Set the condition meet flag.
								$condition_meet = false;

								switch ( $operator ) {
									case 'contains':
										// Check if the captured value contains the trigger value.
										if ( false !== strpos( $captured_value, $trigger_value ) ) {
											$condition_meet = true;
										}
										break;

									case 'not_contains':
										// Check if the captured value does not contain the trigger value.
										if ( false === strpos( $captured_value, $trigger_value ) ) {
											$condition_meet = true;
										}
										break;

									case 'starts_with':
										// Check if the captured value starts with the trigger value.
										if ( 0 === strpos( $captured_value, $trigger_value ) ) {
											$condition_meet = true;
										}
										break;

									case 'ends_with':
										// Check if the captured value ends with the trigger value.
										if ( substr( $captured_value, -strlen( $trigger_value ) ) === $trigger_value ) {
											$condition_meet = true;
										}
										break;

									case 'equals':
										// Check if the captured value is equal to the trigger value.
										if ( $captured_value === $trigger_value ) {
											$condition_meet = true;
										}
										break;

									case 'not_equals':
										// Check if the captured value is not equal to the trigger value.
										if ( $captured_value !== $trigger_value ) {
											$condition_meet = true;
										}
										break;

									case 'greater_than':
										// Check if the captured value is greater than the trigger value.
										if ( $captured_value > $trigger_value ) {
											$condition_meet = true;
										}
										break;

									case 'less_than':
										// Check if the captured value is less than the trigger value.
										if ( $captured_value < $trigger_value ) {
											$condition_meet = true;
										}
										break;

									case 'greater_than_or_equal':
										// Check if the captured value is greater than or equal to the trigger value.
										if ( $captured_value >= $trigger_value ) {
											$condition_meet = true;
										}
										break;

									case 'less_than_or_equal':
										// Check if the captured value is less than or equal to the trigger value.
										if ( $captured_value <= $trigger_value ) {
											$condition_meet = true;
										}
										break;
								}

								if ( 'AND' === $condition_type ) {
									if ( ! $condition_meet ) {
										$continue_execution = false;
										break;
									} else {
										$continue_execution = true;
									}
								} elseif ( $condition_meet ) {
									$continue_execution = true;
									break;
								}
							}
						}

						if ( $workflow_live_id && $app_action_live === $trigger && $application_live === $application && $continue_execution ) {
							update_option( 'webhook-capture-' . $workflow_live_id, $response_data, false );
							delete_option( 'webhook-capture-live' );

							// Do not execute workflow if capture data in process.
							continue;
						}

						$trigger_event = $step->action;

						if ( $trigger === $trigger_event && $continue_execution ) {
							// Get the current workflow ID.
							$workflow_id = $workflow->workflow_id;

							// Run the workflow.
							$flowmattic_workflow = new FlowMattic_Workflow();
							$flowmattic_workflow->run( $workflow_id, $response_data );
						}
					}
				}
			}
		}
	}
}
