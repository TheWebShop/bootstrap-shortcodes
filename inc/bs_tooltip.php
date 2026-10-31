<?php
function bs_tooltip( $params, $content=null ) {
    extract( shortcode_atts( array(
        'placement' => 'top',
        'trigger' => 'hover',
        'href' => "#"
    ), $params ) );

    $placement = (in_array( $placement, array( 'top', 'right', 'bottom', 'left' ) ))? $placement: 'top';
    $content = preg_replace('/<br class="nc".\/>/', '', $content);
    // The tooltip shows the same text as the link. Until 3.6 the title was cut at the
    // first word-wrap boundary (about 20 characters) because of a mis-quoted "\n".
    $title = trim( $content );
    $result = '<a href="#" data-toggle="tooltip" title="' . esc_attr( $title ) . '" data-placement="' . esc_attr( $placement ) . '" data-trigger="' . esc_attr( $trigger ) . '">' . esc_attr( $content ) . '</a>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_tooltip', 'bs_tooltip' );
