<?php

function bs_collapse( $params, $content=null ){
    extract( shortcode_atts( array(
        'id'=>''
         ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<div class="panel-group" id="' . $id . '">';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_collapse', 'bs_collapse' );


function bs_citem( $params, $content=null ){
    extract( shortcode_atts( array(
        'id'=> '',
        'title'=> 'Collapse title',
        'parent' => '',
        'open' => 'false',
         ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result =  '<div class="panel panel-default">';
    $result .= '    <div class="panel-heading" role="tab" id="heading_' . $id . '">';
    $result .= '        <h4 class="panel-title">';
    $result .= '<a class="accordion-toggle collapsed" data-toggle="collapse" aria-controls="heading_' . $id . '" data-parent="#' . $parent . '" href="#' . $id . '">';
    $result .= $title;
    $result .= '</a>';
    $result .= '        </h4>';
    $result .= '    </div>';
    $result .= '    <div id="' . $id . '" class="panel-collapse collapse '.($open=='true'? 'in' : '').'" role="tabpanel" aria-labelledby="heading_' . $id . '">';
    $result .= '        <div class="panel-body">';
    $result .= do_shortcode( $content );
    $result .= '        </div>';
    $result .= '    </div>';
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode('bs_citem', 'bs_citem' );
