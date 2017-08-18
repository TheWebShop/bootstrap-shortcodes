<?php
function bs_tooltip( $params, $content=null ) {
    extract( shortcode_atts( array(
        'placement' => 'top',
        'href' => "#"
    ), $params ) );

    $placement = (in_array( $placement, array( 'top', 'right', 'bottom', 'left' ) ))? $placement: 'top';
    $content = preg_replace('/<br class="nc".\/>/', '', $content);
    $title = explode( '\n', wordwrap( $content, 20, '\n' ) );
    $result = '<a href="#" data-toggle="tooltip" title="' . $title[0] . '" data-html="true" data-placement="' . esc_attr( $placement ) . '">' . esc_attr( $content ) . '</a>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_tooltip', 'bs_tooltip' );
