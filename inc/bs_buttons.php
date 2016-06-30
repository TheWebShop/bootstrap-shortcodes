<?php
function bs_buttons( $params, $content=null ) {
    extract(shortcode_atts(array(
        'size' => 'default',
        'type' => 'default',
        'value' => 'button',
        'href' => "#",
        'target' => '_self'
    ), $params ) );

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<a class="btn btn-' . esc_attr($size). ' btn-' . esc_attr($type) . '" href="' . esc_url($href) . '" target="' . esc_attr($target) . '"" >' . $value . '</a>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_button', 'bs_buttons' );
