<?php
/**
 * Customer eKomi review notification
 *
 * @author David Täboas
 * @version 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$base     = get_option( 'woocommerce_email_base_color' );
$base_text   = wc_light_or_dark( $base, '#202020', '#ffffff' );
$text     = get_option( 'woocommerce_email_text_color' );

?>

<?php do_action( 'woocommerce_email_header', $email_heading ); ?>

<p><?php echo sprintf( __( 'Dear %s %s,', 'woocommerce-ekomi' ), $order->billing_first_name, $order->billing_last_name ); ?></p>
<p><?php echo sprintf( __( 'You have recently shopped at %s. Thank you! We would be glad if you spent some time to write a review about your order. To do so please follow follow the link.', 'woocommerce-ekomi' ), get_bloginfo( 'name' ) ); ?></p>
<table cellspacing="0" cellpadding="0" style="width: 100%; border: none;" border="0">
  <tr align="center">
    <td align="center"><a class="email_btn" href="<?php echo esc_url( $order->ekomi_review_link ); ?>" target="_blank" style="text-decoration: none; background-color: <?php echo esc_attr( $base ); ?>; color: <?php echo $base_text;?>; border-radius: 3px !important; padding: font-family:Arial; font-weight:bold; line-height:100%; padding: 0.5rem;"><?php echo __( 'Rate Order now', 'woocommerce-ekomi' );?></a></td>
  </tr>
</table>

<?php do_action( 'woocommerce_email_footer' ); ?>
