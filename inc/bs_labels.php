<?php
function bs_labels( $params, $content=null ) {
    extract( shortcode_atts( array(
        'type' => 'default'
    ), $params ) );

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<span class="label label-' . $type . '">' . $content . '</span>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_label', 'bs_labels' );
