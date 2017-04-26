<?php

function bs_card( $params, $content=null ){
    extract( shortcode_atts( array(
        'title' => 'Card title',
        'header' => 'false',
        'footer' => '',
        'type'=> ''
        ), $params ) );
    
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    
    $cardinverse = ( $type == 'primary' or 
                     $type == 'success' or
                     $type == 'info' or 
                     $type == 'warning' or 
                     $type == 'danger' ) ? ' card-inverse' : '';

    $result =  '<div class="mb-3 card'. (($type !='') ? ' card-' . $type . $cardinverse : '') .'" '.(($type == 'inverse') ? ' style="background-color: #333; border-color: #333;"':'').'>';

    if($title !==''):
        if($header=='true'):
            $result .= '<h4 class="card-header'. (($type !='') ? ' card-' . $type : '') .'">' . $title . '</h4>';
            $result .= '<div class="card-block">';  
        else:
            $result .= '<div class="card-block">';
            $result .= '<h4 class="card-title">' . $title . '</h4>';
        endif;
    else:
        $result .= '<div class="card-block">';
    endif;
    $result .= do_shortcode( $content );
    $result .= '    </div>';
    if($footer != ''):
        $result .= '<footer class="card-footer'. (($type !='') ? ' card-' . $type : '') .'">'. $footer .'</footer>';
    endif;
    $result .= '</div>';

    return force_balance_tags( $result );
}
add_shortcode( 'bs_card', 'bs_card' );

