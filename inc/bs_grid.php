<?php

function bs_row( $params, $content=null ) {
    extract( shortcode_atts( array(
        'class' => 'row'
    ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<div class="' . $class . '">';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode('bs_row', 'bs_row');

function bs_span( $params, $content=null ) {
    extract( shortcode_atts( array(
        'class' => 'col-sm-1'
        ), $params ) );

    $result = '<div class="' . $class . '">';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_col', 'bs_span' );