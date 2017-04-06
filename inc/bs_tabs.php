<?php
/*--------------
[bs_tabs]
    [bs_thead]
        [bs_tab href="#link" title="title"]
        [bs_dropdown title="title"]
            [bs_tab href="#link" title="title"]
        [/bs_dropdown]
    [/bs_thead]
    [bs_tcontents]
        [bs_tcontent id="link"]
        [/bs_tcontent]
    [/bs_tcontents]
[/bs_tabs]
---------------*/

function bs_tabs( $params, $content=null ){
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<div class="card">';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_tabs', 'bs_tabs' );

function bs_thead( $params, $content=null) {
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<ul class="nav nav-tabs" role="tablist">';
    $result .= do_shortcode( $content );
    $result .= '</ul>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_thead', 'bs_thead' );

function bs_tab( $params, $content=null ) {
    extract( shortcode_atts( array(
        'href' => '#',
        'title' => '',
        'class' => ''
        ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );

    $result = '<li class="nav-item ' . $class . '">';
    $result .= '<a class="nav-link active" data-toggle="tab" role="tab" href="' . $href . '">' . $title . '</a>';
    $result .= '</li>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_tab', 'bs_tab' );


function bs_dropdown( $params, $content=null ) {
    global $bs_timestamp;
    extract( shortcode_atts( array(
        'title' => '',
        'id' => '',
        'class' => '',
        ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<li class="nav-item dropdown">';
    $result .= '
    <a class="nav-link dropdown-toggle ' . $class . '" id="' . $id . '" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" href="#">' . $title . '</a>';
    $result .= '<ul class="dropdown-menu">';
    $result .= do_shortcode( $content );
    $result .= '</ul></li>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_dropdown', 'bs_dropdown' );

function bs_tcontents( $params, $content=null ) {
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<div class="tab-content">';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_tcontents', 'bs_tcontents' );

function bs_tcontent( $params, $content=null ) {
    extract(shortcode_atts(array(
        'id' => '',
        'class'=>'',
        ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $class = ($class=='active')? 'active in': '';
    $result = '<div role="tabpanel" class="tab-pane ' . $class . '" id=' . $id . '>';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_tcontent', 'bs_tcontent' );
