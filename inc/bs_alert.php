<?php
function bs_notice( $params, $content=null ) {
    extract( shortcode_atts( array(
        'type' => 'unknown',
		'dismissible' => 1
    ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result =  '<div class="alert alert-'.$type.($dismissible==1 ? ' alert-dismissible' : '').'">';
    $result .= $dismissible==1 ? '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' : '';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_notification', 'bs_notice' );