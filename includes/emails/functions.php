<?php
/**
 * Email Functions
 *
 * @package     Easy Digital Downloads
 * @subpackage  Email Functions
 * @copyright   Copyright (c) 2013, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Email Download Purchase Receipt
 *
 * Email the download link(s) and payment confirmation to the buyer.
 *
 * @access      private
 * @since       1.0
 * @return      void
 */
function edd_email_purchase_receipt( $payment_id, $admin_notice = true ) {
	global $edd_options;

	$payment_data = edd_get_payment_meta( $payment_id );
	$user_info = maybe_unserialize( $payment_data['user_info'] );

	if ( isset( $user_info['id'] ) && $user_info['id'] > 0 ) {
		$user_data = get_userdata($user_info['id']);
		$name = $user_data->display_name;
	} elseif ( isset( $user_info['first_name'] ) && isset( $user_info['last_name'] ) ) {
		$name = $user_info['first_name'] . ' ' . $user_info['last_name'];
	} else {
		$name = $user_info['email'];
	}

	$message = edd_get_email_body_header();
	$message .= edd_get_email_body_content( $payment_id, $payment_data );
	$message .= edd_get_email_body_footer();

	$from_name = isset( $edd_options['from_name'] ) ? $edd_options['from_name'] : get_bloginfo('name');
	$from_email = isset( $edd_options['from_email'] ) ? $edd_options['from_email'] : get_option('admin_email');

	$subject = apply_filters( 'edd_purchase_subject', isset( $edd_options['purchase_subject'] )
		? trim( $edd_options['purchase_subject'] )
		: __( 'Purchase Receipt', 'edd' ), $payment_id );

	$subject = edd_email_template_tags( $subject, $payment_data, $payment_id );

	$headers = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
	$headers .= "Reply-To: ". $from_email . "\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/html; charset=utf-8\r\n";

	// Allow add-ons to add file attachments
	$attachments = apply_filters( 'edd_receipt_attachments', array(), $payment_id, $payment_data );

	wp_mail( $payment_data['email'], $subject, $message, $headers, $attachments );

	if ( $admin_notice ) {
		do_action( 'edd_admin_sale_notice', $payment_id, $payment_data );
	}
}

/**
 * Email Test Download Purchase Receipt
 *
 * Email the download link(s) and payment confirmation to the admin accounts for testing.
 *
 * @access      private
 * @since       1.5
 * @return      void
 */
function edd_email_test_purchase_receipt() {
	global $edd_options;

	$default_email_body = __( "Dear", "edd" ) . " {name},\n\n";
	$default_email_body .= __( "Thank you for your purchase. Please click on the link(s) below to download your files.", "edd" ) . "\n\n";
	$default_email_body .= "{download_list}\n\n";
	$default_email_body .= "{sitename}";

	$email = isset( $edd_options['purchase_receipt'] ) ? $edd_options['purchase_receipt'] : $default_email_body;

	$message = edd_get_email_body_header();
	$message .= apply_filters( 'edd_purchase_receipt', edd_email_preview_templage_tags( $email ), 0, array() );
	$message .= edd_get_email_body_footer();

	$from_name = isset( $edd_options['from_name'] ) ? $edd_options['from_name'] : get_bloginfo('name');
	$from_email = isset( $edd_options['from_email'] ) ? $edd_options['from_email'] : get_option('admin_email');

	$subject = apply_filters( 'edd_purchase_subject', isset( $edd_options['purchase_subject'] )
		? trim( $edd_options['purchase_subject'] )
		: __( 'Purchase Receipt', 'edd' ), $payment_id );

	$headers = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
	$headers .= "Reply-To: ". $from_email . "\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/html; charset=utf-8\r\n";

	wp_mail( edd_get_admin_notice_emails(), $subject, $message, $headers );
}

/**
 * Sends the admin sale notice
 *
 * @access      private
 * @since       1.4.2
 * @return      void
 */
function edd_admin_email_notice( $payment_id = 0, $payment_data = array() ) {
	/* Send an email notification to the admin */
	$admin_email   = edd_get_admin_notice_emails();

	$user_info = maybe_unserialize( $payment_data['user_info'] );

	if ( isset( $user_info['id'] ) && $user_info['id'] > 0 ) {
		$user_data = get_userdata($user_info['id']);
		$name = $user_data->display_name;
	} elseif ( isset( $user_info['first_name'] ) && isset($user_info['last_name'] ) ) {
		$name = $user_info['first_name'] . ' ' . $user_info['last_name'];
	} else {
		$name = $user_info['email'];
	}

	$admin_subject = apply_filters( 'edd_admin_purchase_notification_subject', __( 'New download purchase', 'edd' ), $payment_id, $payment_data );

	$admin_message = __( 'Hello', 'edd' ) . "\n\n" . sprintf( __( 'A %s purchase has been made', 'edd' ), edd_get_label_plural() ) . ".\n\n";
	$admin_message .= sprintf( __( '%s sold:', 'edd' ), edd_get_label_plural() ) .  "\n\n";

	$download_list = '';
	$downloads = maybe_unserialize( $payment_data['downloads'] );

	if ( is_array( $downloads ) ) {
		foreach ( $downloads as $download ) {
			$id    = isset( $payment_data['cart_details'] ) ? $download['id'] : $download;
			$title = get_the_title( $id );
			if ( isset( $download['options'] ) ) {
				if ( isset( $download['options']['price_id'] ) ) {
					$title .= ' - ' . edd_get_price_option_name( $id, $download['options']['price_id'], $payment_id );
				}
			}
			$download_list .= html_entity_decode( $title, ENT_COMPAT, 'UTF-8' ) . "\n";
		}
	}

	$gateway = edd_get_gateway_admin_label( get_post_meta( $payment_id, '_edd_payment_gateway', true ) );

	$admin_message .= $download_list . "\n";
	$admin_message .= __( 'Purchased by: ', 'edd' )   . " " . html_entity_decode( $name, ENT_COMPAT, 'UTF-8' ) . "\n";
	$admin_message .= __( 'Amount: ', 'edd' )         . " " . html_entity_decode( edd_currency_filter( edd_format_amount( $payment_data['amount'] ) ), ENT_COMPAT, 'UTF-8' ) . "\n\n";
	$admin_message .= __( 'Payment Method: ', 'edd' ) . " " . $gateway . "\n\n";
	$admin_message .= __( 'Thank you', 'edd' );
	$admin_message = apply_filters( 'edd_admin_purchase_notification', $admin_message, $payment_id, $payment_data );

	$admin_headers = apply_filters( 'edd_admin_purchase_notification_headers', array(), $payment_id, $payment_data );

	$admin_attachments = apply_filters( 'edd_admin_purchase_notification_attachments', array(), $payment_id, $payment_data );

	wp_mail( $admin_email, $admin_subject, $admin_message, $admin_headers, $admin_attachments );
}
add_action( 'edd_admin_sale_notice', 'edd_admin_email_notice', 10, 2 );

/**
 * Retrieves the admin notice emails
 *
 * If not emails are set, the WordPress admin email is used instead
 *
 * @access      private
 * @since       1.0
 * @return      void
 */
function edd_get_admin_notice_emails() {
	global $edd_options;

	$emails = isset( $edd_options['admin_notice_emails'] ) && strlen( trim( $edd_options['admin_notice_emails'] ) ) > 0 ? $edd_options['admin_notice_emails'] : get_bloginfo( 'admin_email' );
	$emails = array_map( 'trim', explode( "\n", $emails ) );

	return apply_filters( 'edd_admin_notice_emails', $emails );
}