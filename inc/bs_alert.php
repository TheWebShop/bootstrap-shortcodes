<?php
function bs_notice( $params, $content=null ) {
    extract( shortcode_atts( array(
        'type' => 'unknown',
        'dismissible' => 'true',
        'class' => ''
    ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result =  '<div class="alert alert-'.$type.($dismissible=='true'? ' alert-dismissible fade show' : '').(($class != '') ? ' '.$class:'').'">';
    $result .= $dismissible=='true'? '<button type="button" class="close" data-dismiss="alert" aria-hidden="Close"><span aria-hidden="true">&times;</span></button>' : '';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_notification', 'bs_notice' );
