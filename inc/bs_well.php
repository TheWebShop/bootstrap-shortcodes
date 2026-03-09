<?php
function bs_well( $params, $content=null ) {
    extract( shortcode_atts( array(
        'size' => ''
    ), $params));

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $size_class = $size ? ' well-' . esc_attr($size) : '';
    $result =  '<div class="well' . $size_class . '">';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_well', 'bs_well' );
