<?php
function bs_buttons( $params, $content=null ) {
    extract(shortcode_atts(array(
        'size' => '',
        'type' => 'default',
        'value' => 'button',
        'href' => "#"
    ), $params ) );

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $size_class = ($size && $size !== $type) ? ' btn-' . esc_attr($size) : '';
    $result = '<a class="btn btn-' . esc_attr($type) . $size_class . '" href="' . esc_url($href) . '">' . esc_attr($value) . '</a>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_button', 'bs_buttons' );
