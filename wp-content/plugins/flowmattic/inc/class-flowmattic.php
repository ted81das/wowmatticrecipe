<?php
/**
 * FlowMattic Main Class File.
 *
 * @package flowmattic
 * @since 1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main FlowMattic Class.
 *
 * @since 1.0
 */
class FlowMattic {

	/**
	 * The one, true instance of this object.
	 *
	 * @since 1.0
	 * @static
	 * @access private
	 * @var object
	 */
	private static $instance;

	/**
	 * Workflow class instance.
	 *
	 * @since 1.0
	 * @access public
	 * @var object
	 */
	public $workflow;

	/**
	 * Integrations.
	 *
	 * @access public
	 * @since 1.0
	 * @var array
	 */
	public $integrations = array();

	/**
	 * Connects.
	 *
	 * @access public
	 * @since 3.0
	 * @var array
	 */
	public $connects = array();

	/**
	 * Custom Apps.
	 *
	 * @access public
	 * @since 3.0
	 * @var array
	 */
	public $custom_apps = array();

	/**
	 * Workflows.
	 *
	 * @access public
	 * @since 1.0
	 * @var array
	 */
	public $workflows_db = array();

	/**
	 * Tasks.
	 *
	 * @access public
	 * @since 1.0
	 * @var array
	 */
	public $tasks_db = array();

	/**
	 * Connects.
	 *
	 * @access public
	 * @since 3.0
	 * @var array
	 */
	public $connects_db = array();

	/**
	 * Custom Apps.
	 *
	 * @access public
	 * @since 3.0
	 * @var array
	 */
	public $custom_apps_db = array();

	/**
	 * Chatbots.
	 *
	 * @access public
	 * @since 4.0
	 * @var array
	 */
	public $chatbots = array();

	/**
	 * Chatbots database.
	 *
	 * @access public
	 * @since 4.0
	 * @var array
	 */
	public $chatbots_db = array();

	/**
	 * Chatbot threads database.
	 *
	 * @access public
	 * @since 4.0
	 * @var array
	 */
	public $chatbot_threads_db = array();

	/**
	 * Variables.
	 *
	 * @access public
	 * @since 4.0
	 * @var array
	 */
	public $variables = array();

	/**
	 * Variables database.
	 *
	 * @access public
	 * @since 4.0
	 * @var array
	 */
	public $variables_db = array();

	/**
	 * RSS Feed database.
	 *
	 * @access public
	 * @since 4.1.0
	 * @var array
	 */
	public $rss_feed_db = array();

	/**
	 * API Polling.
	 *
	 * @access public
	 * @since 4.1.0
	 * @var array
	 */
	public $api_polling = array();

	/**
	 * Updater.
	 *
	 * @access public
	 * @since 1.0
	 * @var array
	 */
	public $updater = array();

	/**
	 * Applications.
	 *
	 * @access public
	 * @since 1.0
	 * @var array
	 */
	public $apps = array();

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since 1.0
	 * @static
	 * @access public
	 */
	public static function get_instance() {

		// If an instance hasn't been created and set to $instance create an instance and set it to $instance.
		if ( null === self::$instance ) {
			self::$instance = new FlowMattic();
		}
		return self::$instance;
	}

	/**
	 * Initializes the plugin by setting localization, hooks, filters,
	 * and administrative functions.
	 *
	 * @since 1.0
	 * @access private
	 */
	private function __construct() {
		// Instantiate the updater.
		$this->updater = FlowMattic_Updater::getInstance( FLOWMATTIC_PLUGIN_FILE );

		// Instantiate the workflow class.
		$this->workflow = new FlowMattic_Workflow();

		// Instantiate the connects class.
		$this->connects = new FlowMattic_Connects();

		// Instantiate the custom_apps class.
		$this->custom_apps = new FlowMattic_Custom_Apps();

		// Instantiate the workflows database.
		$this->workflows_db = new FlowMattic_Database_Workflows();

		// Instantiate the connects database.
		$this->connects_db = new FlowMattic_Database_Connects();

		// Instantiate the custom apps database.
		$this->custom_apps_db = new FlowMattic_Database_Custom_Apps();

		// Instantiate the chatbots class.
		$this->chatbots = new FlowMattic_Chatbots();

		// Instantiate the chatbots database.
		$this->chatbots_db = new FlowMattic_Database_Chatbots();

		// Instantiate the chatbot threads database.
		$this->chatbot_threads_db = new FlowMattic_Database_Chatbot_Threads();

		// Initialise the variables.
		$this->variables = new FlowMattic_Variables();

		// Instantiate the variables database.
		$this->variables_db = new FlowMattic_Database_Variables();

		// Instantiate the RSS Feed database.
		$this->rss_feed_db = new FlowMattic_Database_Rss_Feed();

		// Instantiate the API Polling class.
		$this->api_polling = new FlowMattic_Polling();

		// Instantiate the tasks database.
		$this->tasks_db = new FlowMattic_Database_Tasks();

		// Initialise admin.
		new FlowMattic_Admin();

		$this->apps = new FlowMattic_Applications();
	}

	/**
	 * Create database tables.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function create_tables() {
		$flowmattic_database = new FlowMattic_Database();
		$flowmattic_database->create_tables();
	}

	/**
	 * Check license validation.
	 *
	 * @since 1.0
	 * @access public
	 * @return bool|object
	 */
	public function check_license() {
		$license_key   = get_option( 'flowmattic_license_key', '' );
		$license_email = get_option( 'flowmattic_license_email', '' );

		FlowMattic_Updater::addOnDelete(
			function() {
				delete_option( 'flowmattic_license_key' );
			}
		);

		// If the license key is empty, return false.
		if ( empty( $license_key ) ) {
			return false;
		}

		$license = get_transient( 'flowmattic_license_response_check' );

		if ( false === $license ) {
			if ( FlowMattic_Updater::CheckWPPlugin( $license_key, $license_email, $license_message, $response_object, FLOWMATTIC_PLUGIN_FILE ) ) {
				set_transient( 'flowmattic_license_response_check', $response_object, HOUR_IN_SECONDS * 24 );
				return $response_object;
			} else {
				return false;
			}
		} else {
			if ( is_string( $license ) && false !== strpos( $license, 'expired' ) ) {
				return false;
			}

			return $license;
		}
	}
}
