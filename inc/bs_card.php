<?php

function bs_card( $params, $content=null ){
    extract( shortcode_atts( array(
        'title' => 'Card title',
        'header' => 'false',
        'footer' => '',
        'type'=> '',
        'inverse'=> 'false',
        'class'=> ''
        ), $params ) );
    
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    
    $cardinverse = ( $inverse == 'true' ) ? ' text-white' : '';

    //backward compatibility
    if($type === 'inverse') :
        $type = 'dark';
    endif;

    if(strpos($type, 'outline-') !== false) :
        $type = preg_replace('/outline/', '', $type, 1);
        $cardtype = ' border' . $type;
        $headerbg = ' border' . $type;
    else:
        $cardtype = ($type !='') ? ' bg-' . $type : '';
        $headerbg = '';
    endif;


    $result =  '<div class="mb-3'.(($class != '') ? ' '. $class : '').' card'. $cardtype . $cardinverse .'">';

    if($title !==''):
        if($header=='true'):
            $result .= '<h4 class="card-header'.$headerbg.'">' . $title . '</h4>';
            $result .= '<div class="card-body">';
        else:
            $result .= '<div class="card-body">';
            $result .= '<h4 class="card-title">' . $title . '</h4>';
        endif;
    else:
        $result .= '<div class="card-body">';
    endif;
    $result .= do_shortcode( $content );
    $result .= '    </div>';
    if($footer != ''):
        $result .= '<footer class="card-footer'.$headerbg.'">'. $footer .'</footer>';
    endif;
    $result .= '</div>';

    return force_balance_tags( $result );
}
add_shortcode( 'bs_card', 'bs_card' );

