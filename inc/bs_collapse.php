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
        'parent' => ''
         ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result =  '<div class="panel panel-default">';
    $result .= '    <div class="panel-heading">';
    $result .= '        <h4 class="panel-title">';
    $result .= '<a class="accordion-toggle" data-toggle="collapse" data-parent="#' . $parent . '" href="#' . $id . '">';
    $result .= $title;
    $result .= '</a>';
    $result .= '        </h4>';
    $result .= '    </div>';
    $result .= '    <div id="' . $id . '" class="panel-collapse collapse">';
    $result .= '        <div class="panel-body">';
    $result .= do_shortcode( $content );
    $result .= '        </div>';
    $result .= '    </div>';
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode('bs_citem', 'bs_citem' );
