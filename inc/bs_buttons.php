<?php
function bs_buttons( $params, $content=null ) {
    extract(shortcode_atts(array(
        'size' => 'default',
        'type' => 'default',
        'value' => 'button',
        'href' => "#"
    ), $params ) );

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<a class="btn btn-' . esc_attr($size) . ' btn-' . esc_attr($type) . '" href="' . esc_url($href) . '">' . esc_attr($value) . '</a>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_button', 'bs_buttons' );
