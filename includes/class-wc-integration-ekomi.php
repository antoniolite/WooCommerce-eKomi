<?php
/**
 * Integration Demo Integration.
 *
 * @package  WC_Integration_eKomi_Integration
 * @category Integration
 * @author   David Táboas
 */

if ( ! class_exists( 'WC_Integration_eKomi_Integration' ) ) :

class WC_Integration_eKomi_Integration extends WC_Integration {


  public $debug = "yes";
  public $log;

  /**
   * Init and hook in the integration.
   */
  public function __construct() {
    global $woocommerce;

    if($this->debug) $this->log = new WC_Logger();

    $this->id                 = 'ekomi-integration';
    $this->method_title       = __( 'eKomi Integration', 'woocommerce-ekomi' );
    $this->method_description = __( 'Integration with eKomi through API.', 'woocommerce-ekomi' );

    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables.
    $this->shop_id            = $this->get_option('shop_id');
    $this->interface_id       = $this->get_option('interface_id');
    $this->interface_password = $this->get_option('interface_password');
    $this->days_until_email   = $this->get_option('days_until_email');


    // eKomi
    $this->version_ekomi = 'cust-1.0.0';
    if ( !username_exists('ekomi') && $this->is_enabled() ) {
      wp_create_user( __( 'eKomi Customer', 'woocommerce-ekomi' ), wp_generate_password(), 'ekomi@loremipsumdolorom.com' );
      $this->user_ekomi = get_user_by( 'email', 'ekomi@loremipsumdolorom.com' );
      wp_update_user( array( 'ID' => $this->user_ekomi->ID, 'role' => 'customer' ) );
    }

    // Cronjobs & Hooks
    if ( $this->is_enabled() ) {
      $this->user_ekomi = get_user_by( 'email', 'ekomi@loremipsumdolorom.com' );
      add_action( 'woocommerce_ekomi', array( $this, 'get_reviews' ) );
      add_action( 'woocommerce_ekomi', array( $this, 'put_products' ) );
      add_action( 'woocommerce_ekomi', array( $this, 'send_mails' ) );
      add_action( 'woocommerce_order_status_completed', array( $this, 'put_order' ), 0, 1 );

    }

    // Actions.
    add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );


  }

  public function log($message)
  {
    $this->log->add("ekomi",$message);
  }

  /**
   * Checks whether eKomi API is enabled
   *
   * @return boolean
   */
  public function is_enabled() {
    return ( empty( $this->shop_id ) || empty( $this->interface_id ) || empty( $this->interface_password )  ) ? false : true;
  }

  /**
   * Initialize integration settings form fields.
   */
  public function init_form_fields() {
    $this->form_fields = array(

      'shop_id' => array(
        'title'       => _x( 'Shop ID', 'ekomi', 'woocommerce-ekomi' ),
        'type'        => 'text',
        'description' => _x( 'Insert your Shop ID here.', 'ekomi', 'woocommerce-ekomi' ),
        'desc_tip'    => true,
        'id'          => 'woocommerce_ekomi_shop_id',
        'css'         => 'min-width:300px;',
      ),
      'interface_id' => array(
        'title'       => _x( 'Interface ID', 'ekomi', 'woocommerce-ekomi' ),
        'type'        => 'text',
        'description' => _x( 'Insert your Interface ID here.', 'ekomi', 'woocommerce-ekomi' ),
        'desc_tip'    => true,
        'id'          => 'woocommerce_ekomi_interface_id',
        'css'         => 'min-width:300px;',
      ),
      'interface_password' => array(
        'title'       => _x( 'Interface Password', 'ekomi', 'woocommerce-ekomi' ),
        'type'        => 'text',
        'description' => _x( 'Insert your Interface Password here.', 'ekomi', 'woocommerce-ekomi' ),
        'desc_tip'    => true,
        'id'          => 'woocommerce_ekomi_interface_password',
        'css'         => 'min-width:300px;',
      ),
      'days_until_email' => array(
        'title'             => _x( 'Days until Email', 'ekomi', 'woocommerce-ekomi' ),
        'type'              => 'number',
        'description'       => _x( 'Number of days between an order being marked as completed and review email to customer.', 'ekomi', 'woocommerce-ekomi' ),
        'desc_tip'          => true,
        'id'                => 'woocommerce_ekomi_day_diff',
        'custom_attributes' => array( 'min' => 0, 'step' => 1 ),
        'default'           => 7,
      ),

    );
  }

  /**
   * PHP 5.3 backwards compatibility for getting date diff
   *
   * @param  string $from date from
   * @param  string $to   date to
   * @return array  array containing year, month, date diff
   */
  public function get_date_diff( $from, $to ) {
    $diff = abs( strtotime( $to ) - strtotime( $from ) );
    $years = floor( $diff / (365*60*60*24) );
    $months = floor( ( $diff - $years * 365*60*60*24 ) / ( 30*60*60*24 ) );
    $days = floor( ( $diff - $years * 365*60*60*24 - $months*30*60*60*24 ) / ( 60*60*24 ) );
    return array( 'y' => $years, 'm' => $months, 'd' => $days );
  }



  /**
   * Transfers all Shop Products including IDs and Titles to eKomi
   */
  public function put_products() {
    $posts = get_posts( array( 'post_type' => array( 'product' ), 'post_status' => 'publish', 'showposts' => -1 ) );
    if ( !empty( $posts ) ) {
      foreach ( $posts as $post ) {
        $product = get_product( $post );
        if ( $product->is_type( 'variable' ) ) {
          $variations = $product->get_available_variations();
          if ( !empty( $variations ) ) {
            foreach ( $variations as $variation ) {
              $this->put_product( get_product( $variation['variation_id'] ) );
            }
          }
        } elseif ( $product->is_type( 'simple' ) ) {
          $this->put_product( $product );
        }
      }
    }
  }

  /**
   * Transfers a single Product to eKomi
   *
   * @param object  $product
   */
  public function put_product( $product ) {
    $response = unserialize( file_get_contents(
      'https://api.ekomi.de/v3/putProduct?auth=' . $this->interface_id . '|' . $this->interface_password . '&version=' . $this->version_ekomi . '&product_id=' . urlencode( esc_attr( $this->get_product_id( $product ) ) ) . '&product_name=' . urlencode( esc_attr( $this->get_product_name( $product ) ) )
    ) );

    return ( $response['done'] == 1 ) ? true : false;
  }

  /**
   * Returns the product id. If is variation returns variation id instead.
   *
   * @param  object $product
   * @return integer
   */
  public function get_product_id( $product ) {
    return ( isset( $product->variation_id ) ? $product->variation_id : $product->id );
  }

  /**
   * Gets the Product's name based on it's type
   *
   * @param object  $product
   * @return string
   */
  public function get_product_name( $product ) {
    return isset( $product->variation_id ) ? get_the_title( $product->id ) . ' (' . implode( ', ', $product->get_variation_attributes() ) . ')' : get_the_title( $product->id );
  }

  /**
   * Transfers a single Order to eKomi
   *
   * @param integer $order_id
   * @return boolean
   */
  public function put_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! isset( $order->ekomi_review_link ) ) {
      $items = $order->get_items();
      $product_ids = array();
      if ( !empty( $items ) ) {
        foreach ( $items as $item ) {
          $product_ids[] = ( empty( $item[ 'variation_id' ] ) ? $item[ 'product_id' ] : $item[ 'variation_id' ] );
        }
      }
      $response = unserialize( file_get_contents(
        'https://api.ekomi.de/v3/putOrder?auth=' . $this->interface_id . '|' . $this->interface_password . '&version=' . $this->version_ekomi . '&order_id=' . urlencode( esc_attr( $order->id ) ) . '&product_ids=' . urlencode( esc_attr( implode( ',', $product_ids ) ) )
      ) );


      if ( $response['done'] == 1 && isset( $response['link'] ) )
        update_post_meta( $order->id, '_ekomi_review_link', $response[ 'link' ] );
      return ( $response['done'] == 1 && isset( $response['link'] ) ) ? true : false;
    }
    return false;
  }

  /**
   * Send Customer Email notifications if necessary (loops through orders and checks day difference after completion)
   */
  public function send_mails() {
    $order_query = new WP_Query(
      array( 'post_type' => 'shop_order', 'post_status' => 'wc-completed', 'meta_query' =>
        array(
          array(
            'key'     => '_ekomi_review_link',
            'compare' => 'EXISTS',
          ),
          array(
            'key'     => '_ekomi_review_mail_sent',
            'compare' => 'NOT EXISTS',
          ),
        ),
      )
    );


    while ( $order_query->have_posts() ) {
      $order_query->next_post();
      $order = wc_get_order( $order_query->post->ID );

      $diff = $this->get_date_diff( $order->completed_date, date( 'Y-m-d H:i:s' ) );
      if ( $diff[ 'd' ] >= (int) $this->days_until_email ) {
        $mails = WC()->mailer()->get_emails();
        if ( !empty( $mails ) ) {
          foreach ( $mails as $mail ) {
            if ( $mail->id == 'customer_ekomi' ) {
              $mail->trigger( $order->id );
              update_post_meta( $order->id, '_ekomi_review_mail_sent', 1 );
              update_post_meta( $order->id, '_ekomi_review_link', '' );
            }
          }
        }
      }
    }
  }

  /**
   * Grabs the reviews from eKomi and saves them as review within the Shop
   *
   * @return boolean
   */
  public function get_reviews() {

    $response = file_get_contents(
      'https://api.ekomi.de/get_productfeedback.php?interface_id=' . $this->interface_id . '&interface_pw=' . $this->interface_password . '&version=' . $this->version_ekomi . '&type=csv&product=all'
    );

    if ( !empty( $response ) ) {
      $reviews = explode( PHP_EOL, $response );
      if ( !empty( $reviews ) ) {
        foreach ( $reviews as $review ) {
          $review = str_getcsv( $review );
          if ( !empty( $review[0] ) ) {
            if ( ! $this->review_exists( $review[1] ) && $this->is_product( (int) esc_attr( $review[2] ) ) ) {
              $product = get_product( (int) esc_attr( $review[2] ) );
              $data = array(
                'comment_post_ID' => $product->id,
                'comment_author' => $this->user_ekomi->user_login,
                'comment_author_email' => $this->user_ekomi->user_email,
                'comment_content' => preg_replace( '/\v+|\\\[rn]/', '<br/>', esc_attr( $review[4] ) ),
                'comment_date' => date( 'Y-m-d H:i:s', (int) esc_attr( $review[0] ) ),
                'comment_approved' => 1,
              );
              $comment_id = wp_insert_comment( $data );
              if ( $comment_id ) {
                add_comment_meta( $comment_id, 'rating', (int) esc_attr( $review[3] ), true );
                add_comment_meta( $comment_id, 'order_id', esc_attr( $review[1] ), true );
              }
            }
          }
        }
      }
    }
    return ( is_array( $reviews ) ) ? true : false;
  }

  public function is_product( $id ) {
    return ( get_post_status( $id ) == 'publish' && ( get_post_type( $id ) == 'product' || get_post_type( $id ) == 'product_variation' ) ) ? true : false;
  }

  /**
   * Checks if a review already exists by using a eKomi order ID
   *
   * @param string  $review_order_id
   * @return boolean
   */
  public function review_exists( $review_order_id ) {
    $comments_query = new WP_Comment_Query;
    $comments       = $comments_query->query( array( 'meta_key' => 'order_id', 'meta_value' => $review_order_id ) );
    return empty( $comments ) ? false : true;
  }


}

endif;