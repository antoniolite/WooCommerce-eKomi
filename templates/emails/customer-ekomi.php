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

<?php echo wptexturize( wpautop( $review_message ) ); ?>
<table cellspacing="0" cellpadding="0" style="width: 100%; border: none;" border="0">
  <tr align="center">
    <td align="center"><a class="email_btn" href="<?php echo esc_url( $order->ekomi_review_link ); ?>" target="_blank" style="text-decoration: none; background-color: <?php echo esc_attr( $base ); ?>; color: <?php echo $base_text;?>; border-radius: 3px !important; padding: font-family:Arial; font-weight:bold; line-height:100%; padding: 0.5rem;"><?php echo __( 'Rate Order now', 'woocommerce-ekomi' );?></a></td>
  </tr>
</table>

<?php do_action( 'woocommerce_email_footer' ); ?>
