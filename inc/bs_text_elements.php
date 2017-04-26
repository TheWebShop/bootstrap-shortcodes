<?php
//lead text
function bs_lead( $params, $content=null ){

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<div class="lead">';
    $result .= do_shortcode( $content );
    $result .= '</div>';

    return force_balance_tags( $result );
}
add_shortcode( 'bs_lead', 'bs_lead' );


//blockquote
function bs_blockquote( $params, $content=null ){
    
    extract( shortcode_atts( array(
        'align' => '',
        'cite' => ''
        ), $params ) );
    
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    
    $result = '<blockquote class="blockquote'.(($align == 'right' or $align == 'reverse') ? ' blockquote-reverse' : '').'">';
    $result .= do_shortcode( $content );
    if($cite != '') : $result .= '<footer class="blockquote-footer"><cite title="'.$cite.'">'.$cite.'</cite></footer>'; endif;
    $result .= '</blockquote>';

    return force_balance_tags( $result );
}
add_shortcode( 'bs_blockquote', 'bs_blockquote' );

