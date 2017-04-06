<?php
function bs_badges( $params, $content=null ) {
    extract( shortcode_atts( array(
        'type' => 'default'
    ), $params ) );

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<span class="badge badge-' . $type . '">' . $content . '</span>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_badge', 'bs_badges' );
