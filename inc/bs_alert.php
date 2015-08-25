<?php
function bs_notice( $params, $content=null ) {
    extract( shortcode_atts( array(
        'type' => 'unknown',
        'dismissible' => 'true'
    ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result =  '<div class="alert alert-'.$type.($dismissible=='true'? ' alert-dismissible' : '').'">';
    $result .= $dismissible=='true'? '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' : '';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_notification', 'bs_notice' );
