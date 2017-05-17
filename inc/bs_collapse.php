<?php
function bs_collapse( $params, $content=null ){
    extract( shortcode_atts( array(
        'id'=>'',
        'class'=>''
         ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<div id="' . $id . '" role="tablist" aria-multiselectable="true"'.(($class != '') ? ' class="'.$class.'"' : '' ).'>';
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
        'open' => 'false'
         ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    
    $result =  '<div class="card">';
    $result .= '    <div class="card-header" role="tab" id="heading_' . $id . '">';
    $result .= '        <h5 class="mb-0">';
    $result .= '<a data-toggle="collapse" aria-expanded="'.($open=='true'? 'true' : 'false').'" aria-controls="heading_' . $id . '" data-parent="#' . $parent . '" href="#' . $id . '">';
    $result .= $title;
    $result .= '</a>';
    $result .= '        </h5>';
    $result .= '    </div>';
    $result .= '    <div id="' . $id . '" class="collapse '.($open=='true'? 'show' : '').'" role="tabpanel" aria-labelledby="heading_' . $id . '">';
    $result .= '        <div class="card-block">';
    $result .= do_shortcode( $content );
    $result .= '        </div>';
    $result .= '    </div>';
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode('bs_citem', 'bs_citem' );
