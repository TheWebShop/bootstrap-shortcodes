<?php
function bs_well( $params, $content=null ) {
    extract( shortcode_atts( array(
        'size' => 'unknown'
    ), $params));

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result =  '<div class="well well-' . $size . '">';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_well', 'bs_well' );