<?php
/**
 * FlowMattic Tools App.
 *
 * @package flowmattic
 * @since 4.0
 * @version 1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FlowMattic Tools App.
 *
 * @since 4.0
 */
class FlowMattic_Tools {
	/**
	 * Request body.
	 *
	 * @access public
	 * @since 4.3.0
	 * @var array|string
	 */
	public $request_body;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since 4.0
	 * @return void
	 */
	public function __construct() {
		// Enqueue custom view for counter.
		add_action( 'flowmattic_enqueue_views', array( $this, 'enqueue_views' ) );

		flowmattic_add_application(
			'tools',
			array(
				'name'         => esc_attr__( 'FlowMattic Tools', 'flowmattic' ),
				'icon'         => FLOWMATTIC_PLUGIN_URL . 'inc/apps/tools/icon.svg',
				'instructions' => __( 'Tools to manage your workflows in better way', 'flowmattic' ),
				'actions'      => $this->get_actions(),
				'base'         => 'core',
				'type'         => 'action',
			)
		);
	}

	/**
	 * Enqueue view js.
	 *
	 * @access public
	 * @since 4.0
	 * @return void
	 */
	public function enqueue_views() {
		wp_enqueue_script( 'flowmattic-app-view-tools', FLOWMATTIC_PLUGIN_URL . 'inc/apps/tools/view-flowmattic-tools.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );
	}

	/**
	 * Set actions.
	 *
	 * @access public
	 * @since 4.0
	 * @return array
	 */
	public function get_actions() {
		return array(
			'get_variable_value'  => array(
				'title'       => esc_attr__( 'Get Variable Value', 'flowmattic' ),
				'description' => esc_attr__( 'Get the value of a variable.', 'flowmattic' ),
			),
			'set_variable_value'  => array(
				'title'       => esc_attr__( 'Set Variable Value', 'flowmattic' ),
				'description' => esc_attr__( 'Set the value of a variable.', 'flowmattic' ),
			),
			'turn_on_workflow'    => array(
				'title'       => esc_attr__( 'Turn On Workflow', 'flowmattic' ),
				'description' => esc_attr__( 'Turn on a workflow by ID.', 'flowmattic' ),
			),
			'turn_off_workflow'   => array(
				'title'       => esc_attr__( 'Turn Off Workflow', 'flowmattic' ),
				'description' => esc_attr__( 'Turn off a workflow by ID.', 'flowmattic' ),
			),
			'get_workflow_status' => array(
				'title'       => esc_attr__( 'Get Workflow Status', 'flowmattic' ),
				'description' => esc_attr__( 'Get the status of a workflow by ID.', 'flowmattic' ),
			),
			'redirect'            => array(
				'title'       => esc_attr__( 'Redirect to URL', 'flowmattic' ),
				'description' => esc_attr__( 'Redirect the user to given URL. Works only if the triggering app allows to redirect.', 'flowmattic' ),
			),
		);
	}

	/**
	 * Run the action step.
	 *
	 * @access public
	 * @since 4.0
	 * @param string $workflow_id  Workflow ID.
	 * @param object $step         Workflow current step.
	 * @param array  $capture_data Data captured by the WordPress action.
	 * @return array
	 */
	public function run_action_step( $workflow_id, $step, $capture_data ) {
		// CS.
		$capture_data;

		$step   = (array) $step;
		$action = $step['action'];
		$fields = isset( $step['fields'] ) ? $step['fields'] : ( isset( $step['actionAppArgs'] ) ? $step['actionAppArgs'] : array() );

		switch ( $action ) {
			case 'get_variable_value':
				$response = $this->get_variable_value( $fields );
				break;
			case 'set_variable_value':
				$response = $this->set_variable_value( $fields );
				break;

			case 'turn_on_workflow':
				$response = $this->turn_on_workflow( $fields );
				break;

			case 'turn_off_workflow':
				$response = $this->turn_off_workflow( $fields );
				break;

			case 'get_workflow_status':
				$response = $this->get_workflow_status( $fields );
				break;

			case 'redirect':
				$response = $this->redirect( $fields );
				break;
		}

		return $response;
	}

	/**
	 * Get the value of a variable.
	 *
	 * @access public
	 * @since 4.0
	 * @param array $fields The arguments.
	 * @return array
	 */
	public function get_variable_value( $fields ) {
		$variable_name = isset( $fields['variable_name'] ) ? $fields['variable_name'] : '';

		$response = array(
			'success' => false,
			'message' => esc_attr__( 'Variable name is required.', 'flowmattic' ),
		);

		if ( ! empty( $variable_name ) ) {
			// Get the variable value.
			$variable_value = wp_flowmattic()->variables->get_var_value( $variable_name );

			$response = array(
				'success' => true,
				'value'   => $variable_value,
			);
		}

		return wp_json_encode( $response );
	}

	/**
	 * Set the value of a variable.
	 *
	 * @access public
	 * @since 4.0
	 * @param array $fields The arguments.
	 * @return array
	 */
	public function set_variable_value( $fields ) {
		$variable_name  = isset( $fields['variable_name'] ) ? $fields['variable_name'] : '';
		$variable_value = isset( $fields['variable_value'] ) ? $fields['variable_value'] : '';

		$response = array(
			'success' => false,
			'message' => esc_attr__( 'Variable name is required.', 'flowmattic' ),
		);

		// Make sure the variable name is not wrapped in curly braces.
		$variable_name = str_replace( array( '{', '}' ), '', $variable_name );

		if ( ! empty( $variable_name ) ) {
			// Generate an update array.
			$update = array(
				'variable_name'  => $variable_name,
				'variable_value' => $variable_value,
			);

			// Update the variable value.
			wp_flowmattic()->variables_db->update( $update );

			$response = array(
				'success' => true,
				'value'   => $variable_value,
			);
		}

		return wp_json_encode( $response );
	}

	/**
	 * Turn on a workflow by ID.
	 *
	 * @access public
	 * @since 4.2.0
	 * @param array $fields The arguments.
	 * @return array
	 */
	public function turn_on_workflow( $fields ) {
		$workflow_id = isset( $fields['workflow_id'] ) ? $fields['workflow_id'] : '';

		$response = array(
			'status'  => 'error',
			'message' => esc_attr__( 'Workflow ID is required.', 'flowmattic' ),
		);

		if ( ! empty( $workflow_id ) ) {
			// Turn on the workflow.
			$status = wp_flowmattic()->workflow->change_workflow_status( $workflow_id, 'on' );

			if ( ! $status ) {
				$response = array(
					'status'  => 'error',
					'message' => esc_attr__( 'Workflow not found.', 'flowmattic' ),
				);
				return wp_json_encode( $response );
			}

			$response = array(
				'status'      => 'success',
				'workflow_id' => $workflow_id,
				'message'     => esc_attr__( 'Workflow turned on.', 'flowmattic' ),
			);
		}

		return wp_json_encode( $response );
	}

	/**
	 * Turn off a workflow by ID.
	 *
	 * @access public
	 * @since 4.2.0
	 * @param array $fields The arguments.
	 * @return array
	 */
	public function turn_off_workflow( $fields ) {
		$workflow_id = isset( $fields['workflow_id'] ) ? $fields['workflow_id'] : '';

		$response = array(
			'status'  => 'error',
			'message' => esc_attr__( 'Workflow ID is required.', 'flowmattic' ),
		);

		if ( ! empty( $workflow_id ) ) {
			// Turn off the workflow.
			$status = wp_flowmattic()->workflow->change_workflow_status( $workflow_id, 'off' );

			if ( ! $status ) {
				$response = array(
					'status'  => 'error',
					'message' => esc_attr__( 'Workflow not found.', 'flowmattic' ),
				);
				return wp_json_encode( $response );
			}

			$response = array(
				'status'      => 'success',
				'workflow_id' => $workflow_id,
				'message'     => esc_attr__( 'Workflow turned off.', 'flowmattic' ),
			);
		}

		return wp_json_encode( $response );
	}

	/**
	 * Get the status of a workflow by ID.
	 *
	 * @access public
	 * @since 4.2.0
	 * @param array $fields The arguments.
	 * @return array
	 */
	public function get_workflow_status( $fields ) {
		$workflow_id = isset( $fields['workflow_id'] ) ? $fields['workflow_id'] : '';

		$response = array(
			'status'  => 'error',
			'message' => esc_attr__( 'Workflow ID is required.', 'flowmattic' ),
		);

		if ( ! empty( $workflow_id ) ) {
			// Get the workflow.
			$args = array(
				'workflow_id' => $workflow_id,
			);

			$workflow = wp_flowmattic()->workflows_db->get( $args );

			// If workflow is not found, return.
			if ( ! $workflow ) {
				$response = array(
					'status'  => 'error',
					'message' => esc_attr__( 'Workflow not found.', 'flowmattic' ),
				);

				return wp_json_encode( $response );
			}

			$settings        = json_decode( $workflow->workflow_settings, true );
			$workflow_status = $settings['status'];

			$response = array(
				'status'          => 'success',
				'workflow_id'     => $workflow_id,
				'workflow_name'   => $workflow->workflow_name,
				'workflow_status' => ( 'on' === $workflow_status ? esc_attr__( 'Active', 'flowmattic' ) : esc_attr__( 'Inactive', 'flowmattic' ) ),
				'status_code'     => $workflow_status,
			);
		}

		return wp_json_encode( $response );
	}

	/**
	 * Redirect the user to given URL.
	 *
	 * @access public
	 * @since 4.3.0
	 * @param array $fields The arguments.
	 * @return array
	 */
	public function redirect( $fields ) {
		$redirect_url = isset( $fields['redirect_url'] ) ? $fields['redirect_url'] : '';

		$response = array(
			'success' => false,
			'message' => esc_attr__( 'URL is required.', 'flowmattic' ),
		);

		// Set the request body.
		$this->request_body = array(
			'redirect_url' => $redirect_url,
		);

		if ( ! empty( $url ) ) {
			$response = array(
				'status'      => 'error',
				'redirect_to' => $redirect_url,
				'message'     => esc_attr__( 'Redirect URL is required.', 'flowmattic' ),
			);

			// Return the response.
			return wp_json_encode( $response );
		}

		wp_redirect( $redirect_url ); // phpcs:ignore
		exit;
	}

	/**
	 * Test action event ajax.
	 *
	 * @access public
	 * @since 4.0
	 * @param array $event_data Test event data.
	 * @return array
	 */
	public function test_event_action( $event_data ) {
		$event       = $event_data['event'];
		$settings    = $event_data['settings'];
		$fields      = isset( $event_data['fields'] ) ? $event_data['fields'] : ( isset( $settings['actionAppArgs'] ) ? $settings['actionAppArgs'] : array() );
		$workflow_id = $event_data['workflow_id'];

		// Replace action for testing.
		$event_data['action'] = $event;

		if ( 'redirect' === $event ) {
			$url = isset( $fields['redirect_url'] ) ? $fields['redirect_url'] : '';

			return wp_json_encode(
				array(
					'redirect_to' => $url,
				)
			);
		}

		$request = $this->run_action_step( $workflow_id, $event_data, $fields );

		return $request;
	}

	/**
	 * Return the request data.
	 *
	 * @access public
	 * @since 4.3.0
	 * @return array
	 */
	public function get_request_data() {
		return $this->request_body;
	}
}

new FlowMattic_Tools();
