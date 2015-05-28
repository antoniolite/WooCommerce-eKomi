<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_Email_Customer_Ekomi' ) ) :

/**
 * eKomi Review Reminder Email
 *
 * This Email is being sent after the order has been marked as completed to transfer the eKomi Rating Link to the customer.
 *
 * @class     WC_Email_Customer_Ekomi
 * @version    1.0.0
 * @author     David Táboas
 */
class WC_Email_Customer_Ekomi extends WC_Email {

  /**
   * Constructor
   */
  function __construct() {

    $this->id             = 'customer_ekomi';
    $this->title          = __( 'eKomi Review Reminder', 'woocommerce-ekomi' );
    $this->description    = __( 'This E-Mail is being sent to a customer to transfer eKomi order review link to a customer.', 'woocommerce-ekomi' );

    $this->heading        = __( 'Please rate your Order', 'woocommerce-ekomi' );
    $this->subject        = __( 'Please rate your {site_title} order from {order_date}', 'woocommerce-ekomi' );

    $dir = plugin_dir_path( __FILE__ );
    $this->template_base  = $dir . '/templates/';

    $this->template_html  = 'emails/customer-ekomi.php';
    $this->template_plain = 'emails/plain/customer-ekomi.php';



    $this->message =  sprintf( __( 'Dear %s %s,', 'woocommerce-ekomi' ), $order->billing_first_name, $order->billing_last_name )
                      . PHP_EOL . PHP_EOL
                      . sprintf( __( 'You have recently shopped at %s. Thank you! We would be glad if you spent some time to write a review about your order. To do so please follow follow the link.', 'woocommerce-ekomi' ), get_bloginfo( 'name' ) );

    $this->review_message = $this->get_option( 'review_message', $this->message );

    // Triggers for this email
    add_action( 'woocommerce_ekomi_review_notification', array( $this, 'trigger' ) );

    // Call parent constuctor
    parent::__construct();
  }

  /**
   * trigger function.
   *
   * @access public
   * @return void
   */
  function trigger( $order_id ) {

    if ( $order_id ) {
      $this->object     = wc_get_order( $order_id );
      $this->recipient  = $this->object->billing_email;

      $this->find['order-date']      = '{order_date}';
      $this->find['order-number']    = '{order_number}';

      $this->replace['order-date']   = date_i18n( wc_date_format(), strtotime( $this->object->order_date ) );
      $this->replace['order-number'] = $this->object->get_order_number();
    }

    if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
      return;
    }
    $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
  }

  /**
   * get_content_html function.
   *
   * @access public
   * @return string
   */
  function get_content_html() {
    ob_start();
    wc_get_template( $this->template_html, array(
      'order'     => $this->object,
      'email_heading' => $this->get_heading(),
      'sent_to_admin' => false,
      'plain_text'    => false
    ) );
    return ob_get_clean();
  }

  /**
   * get_content_plain function.
   *
   * @access public
   * @return string
   */
  function get_content_plain() {
    ob_start();
    wc_get_template( $this->template_plain, array(
      'order'     => $this->object,
      'email_heading' => $this->get_heading(),
      'sent_to_admin' => false,
      'plain_text'    => true
    ) );
    return ob_get_clean();
  }

  /**
   * Initialize Settings Form Fields
   *
   * @since 0.1
   */
  public function init_form_fields() {

      $this->form_fields = array(
          'enabled'    => array(
              'title'   => __('Enable/Disable','woocommerce-ekomi'),
              'type'    => 'checkbox',
              'label'   => __('Enable this email notification','woocommerce-ekomi'),
              'default' => 'yes'
          ),
          'subject'    => array(
              'title'       => __('Subject','woocommerce-ekomi'),
              'type'        => 'text',
              'description' => sprintf( __('This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.','woocommerce-ekomi'), $this->subject ),
              'placeholder' => '',
              'default'     => ''
          ),
          'heading'    => array(
              'title'       => 'Email Heading',
              'type'        => 'text',
              'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'woocommerce-ekomi'), $this->heading ),
              'placeholder' => '',
              'default'     => ''
          ),
          'review_message' => array(
              'title'      => 'Email Message',
              'type'      => 'textarea',
              'description' => sprintf( __( 'This controls the message content. Leave blank to use the default heading: <code>%s</code>.', 'woocommerce-ekomi'), $this->message ),
              'placeholder' => '',
              'default' => ''
          ),
          'email_type' => array(
              'title'       => 'Email type',
              'type'        => 'select',
              'description' => 'Choose which format of email to send.',
              'default'     => 'html',
              'class'       => 'email_type',
              'options'     => array(
                  'plain'     => 'Plain text',
                  'html'      => 'HTML', 'woocommerce',
                  'multipart' => 'Multipart', 'woocommerce',
              )
          )
      );
  }

}

endif;

return new WC_Email_Customer_Ekomi();