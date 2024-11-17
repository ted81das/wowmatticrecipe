<?php
/**
 * Application Name: FlowMattic Email
 * Description: Add Email module to FlowMattic.
 * Version: 1.1
 * Author: InfiWebs
 * Author URI: https://www.infiwebs.com
 * Textdomain: flowmattic
 *
 * @package FlowMattic
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email module integration class.
 *
 * @since 1.1
 */
class FlowMattic_Email {
	/**
	 * Constructor.
	 *
	 * @access public
	 * @since 1.1
	 * @return void
	 */
	public function __construct() {
		// Enqueue custom view for filter.
		add_action( 'flowmattic_enqueue_views', array( $this, 'enqueue_views' ) );

		flowmattic_add_application(
			'email',
			array(
				'name'         => esc_attr__( 'Email by FlowMattic', 'flowmattic' ),
				'icon'         => FLOWMATTIC_PLUGIN_URL . 'inc/apps/email/icon.svg',
				'instructions' => 'Send email using built-in WP mail function or custom SMTP',
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
	 * @since 1.1
	 * @return void
	 */
	public function enqueue_views() {
		wp_enqueue_script( 'flowmattic-app-view-email', FLOWMATTIC_PLUGIN_URL . 'inc/apps/email/view-email.js', array( 'flowmattic-workflow-utils' ), FLOWMATTIC_VERSION, true );
	}

	/**
	 * Set actions.
	 *
	 * @access public
	 * @since 1.1
	 * @return array
	 */
	public function get_actions() {
		return array(
			'send_email' => array(
				'title'       => esc_attr__( 'Send Email', 'flowmattic' ),
				'description' => esc_attr__( 'Send an email', 'flowmattic' ),
			),
		);
	}

	/**
	 * Run the action step.
	 *
	 * @access public
	 * @since 1.1
	 * @param string $workflow_id  Workflow ID.
	 * @param object $step         Workflow current step.
	 * @param array  $capture_data Data captured by the WordPress action.
	 * @return array
	 */
	public function run_action_step( $workflow_id, $step, $capture_data ) {
		global $phpmailer;

		// (Re)create it, if it's gone missing.
		if ( ! ( $phpmailer instanceof PHPMailer\PHPMailer\PHPMailer ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		}

		$action         = $step['action'];
		$fields         = isset( $step['fields'] ) ? $step['fields'] : ( isset( $step['actionAppArgs'] ) ? $step['actionAppArgs'] : array() );
		$email_provider = ( isset( $fields['email_provider'] ) ) ? $fields['email_provider'] : '';
		$from_name      = ( isset( $fields['from_name'] ) ) ? $fields['from_name'] : '';
		$from_email     = ( isset( $fields['from_email'] ) ) ? $fields['from_email'] : '';
		$reply_to_email = ( isset( $fields['reply_to_email'] ) ) ? $fields['reply_to_email'] : '';
		$to_email       = ( isset( $fields['to_email'] ) ) ? $fields['to_email'] : '';
		$cc_email       = ( isset( $fields['cc_email'] ) ) ? $fields['cc_email'] : '';
		$bcc_email      = ( isset( $fields['bcc_email'] ) ) ? $fields['bcc_email'] : '';
		$email_subject  = ( isset( $fields['email_subject'] ) ) ? $fields['email_subject'] : '';
		$email_body     = ( isset( $fields['email_body'] ) ) ? $fields['email_body'] : '';
		$array_index    = ( isset( $fields['array_index'] ) ) ? $fields['array_index'] : '';
		$attachments    = ( isset( $step['attachments'] ) ) ? $step['attachments'] : array();

		// For SMTP.
		$host_name       = ( isset( $fields['host_name'] ) ) ? $fields['host_name'] : '';
		$smtp_username   = ( isset( $fields['smtp_username'] ) ) ? $fields['smtp_username'] : '';
		$smtp_password   = ( isset( $fields['smtp_password'] ) ) ? $fields['smtp_password'] : '';
		$encryption_type = ( isset( $fields['encryption_type'] ) ) ? $fields['encryption_type'] : 'TLS';
		$smtp_port       = ( isset( $fields['smtp_port'] ) ) ? $fields['smtp_port'] : 587;

		$response_array = array();

		$error_message = '';

		// Fix the HTML entities in email body.
		$email_body = html_entity_decode( $email_body );

		// Get attachment file data.
		if ( ! empty( $attachments ) ) {
			foreach ( $attachments as $key => $attachment ) {
				$file = flowmattic_attachment_url_to_path( $attachment );

				if ( $file ) {
					$attachments[ $key ] = $file;
				}
			}
		}

		if ( 'wp' !== $email_provider ) {
			// Initialize PHPMailer.
			$phpmailer = new PHPMailer\PHPMailer\PHPMailer( true );

			// Set the character encoding.
			$phpmailer->CharSet = 'UTF-8'; // @codingStandardsIgnoreLine

			if ( 'smtp' === $email_provider ) {
				// SMTP configuration.
				// @codingStandardsIgnoreStart
				$phpmailer->isSMTP();
				$phpmailer->Host       = $host_name;
				$phpmailer->SMTPAuth   = true;
				$phpmailer->Username   = $smtp_username;
				$phpmailer->Password   = $smtp_password;
				$phpmailer->SMTPSecure = $encryption_type;
				$phpmailer->Port       = $smtp_port;
				// @codingStandardsIgnoreEnd
			}

			// Send email.
			try {
				// Sender info.
				$phpmailer->setFrom( $from_email, $from_name );

				// Reply to.
				if ( '' !== $reply_to_email ) {
					$phpmailer->addReplyTo( $reply_to_email, $from_name );
				}

				// Add a recipient.
				$phpmailer->addAddress( $to_email );

				// Add CC.
				if ( '' !== $cc_email ) {
					$cc_emails = explode( ',', $cc_email );
					foreach ( $cc_emails as $key => $email_id ) {
						$phpmailer->addCC( $email_id );
					}
				}

				// Add BCC.
				if ( '' !== $bcc_email ) {
					$bcc_emails = explode( ',', $bcc_email );
					foreach ( $bcc_emails as $key => $email_id ) {
						$phpmailer->addBCC( $email_id );
					}
				}

				// Email subject.
				$phpmailer->Subject = $email_subject; // @codingStandardsIgnoreLine

				// Set email format to HTML.
				$phpmailer->isHTML( true );

				// Email body content.
				$phpmailer->Body = stripslashes( $email_body ); // @codingStandardsIgnoreLine

				if ( ! empty( $attachments ) ) {
					foreach ( $attachments as $key => $attachment ) {
						try {
							$phpmailer->addAttachment( $attachment );
						} catch ( PHPMailer\PHPMailer\Exception $e ) {
							continue;
						}
					}
				}

				$phpmailer->send();
			} catch ( PHPMailer\PHPMailer\Exception $e ) {
				$error_message = 'Message could not be sent. Error: ' . $e->getMessage(); // @codingStandardsIgnoreLine
			} catch ( Error $e ) {
				$error_message = $e->getMessage();
			}
		}

		// If WP default, use wp_mail.
		if ( 'wp' === $email_provider ) {
			$to      = $to_email;
			$subject = $email_subject;
			$body    = stripslashes( $email_body );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );

			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

			// Add CC.
			if ( '' !== $cc_email ) {
				$cc_emails = explode( ',', $cc_email );
				foreach ( $cc_emails as $key => $email_id ) {
					$headers[] = 'Cc: ' . $email_id;
				}
			}

			// Add BCC.
			if ( '' !== $bcc_email ) {
				$bcc_emails = explode( ',', $bcc_email );
				foreach ( $bcc_emails as $key => $email_id ) {
					$headers[] = 'Bcc: ' . $email_id;
				}
			}

			$send = wp_mail( $to, $subject, $body, $headers, $attachments );

			if ( ! $send ) {
				$error_message = esc_html__( 'Error sending email', 'flowmattic' );
			}
		}

		if ( '' === $error_message ) {
			$response = wp_json_encode(
				array(
					'status'  => 'success',
					'message' => esc_html__( 'Message has been sent', 'flowmattic' ),
				)
			);
		} else {
			$response = wp_json_encode(
				array(
					'status'  => 'error',
					'message' => $error_message,
				)
			);
		}

		return $response;
	}

	/**
	 * Test action event ajax.
	 *
	 * @access public
	 * @since 1.1
	 * @param array $event_data Test event data.
	 * @return array
	 */
	public function test_event_action( $event_data ) {
		$event          = $event_data['event'];
		$fields         = isset( $event_data['fields'] ) ? $event_data['fields'] : ( isset( $settings['actionAppArgs'] ) ? $settings['actionAppArgs'] : array() );
		$workflow_id    = $event_data['workflow_id'];
		$response_array = array();

		$event_data['action'] = $event;

		// Set attachments.
		if ( isset( $event_data['settings']['attachments'] ) ) {
			$event_data['attachments'] = $event_data['settings']['attachments'];
		}

		$request = $this->run_action_step( $workflow_id, $event_data, $fields );

		return $request;
	}
}

new FlowMattic_Email();
