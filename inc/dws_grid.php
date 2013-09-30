<?php 

function dws_row($params, $content = null){
    extract(shortcode_atts(array(
        'class' => 'row'
    ), $params));
    $content = preg_replace('/<br class="nc".\/>/', '', $content);
    $result = '<div class="'.$class.'">';
    $result .= do_shortcode($content );
    $result .= '</div>'; 
    return force_balance_tags( $result );
}
add_shortcode('row', 'dws_row');

function dws_span($params,$content=null){
    extract(shortcode_atts(array(
        'class' => 'col-xs-1'
        ), $params));

    $result = '<div class="'.$class.'">';
    $result .= do_shortcode($content );
    $result .= '</div>'; 
    return force_balance_tags( $result );
}
add_shortcode('col', 'dws_span');