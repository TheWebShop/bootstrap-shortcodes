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

/**
 * The pane id a tab's href points at, or '' when it is not a plain local anchor.
 */
function bs_tab_pane_id( $href ) {
    $href = trim( (string) $href );
    if ( '' === $href || '#' !== $href[0] ) {
        return '';
    }
    $id = substr( $href, 1 );
    return preg_match( '/^[A-Za-z][\w:.-]*$/', $id ) ? $id : '';
}

function bs_tabs( $params, $content=null ){
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<div class="tab_wrap">';
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

    // Wire the tab to its pane when the href is a local anchor (the editor always emits one).
    $pane     = bs_tab_pane_id( $href );
    $selected = in_array( 'active', preg_split( '/\s+/', trim( $class ) ), true );
    $aria     = $pane ? ' id="tab-' . esc_attr( $pane ) . '" aria-controls="' . esc_attr( $pane ) . '"' : '';
    $aria    .= ' aria-selected="' . ( $selected ? 'true' : 'false' ) . '"';
    $result = '<li class="' . esc_attr($class) . '" role="presentation">';
    $result .= '<a data-toggle="tab" href="' . esc_url($href) . '" role="tab"' . $aria . '>' . esc_attr($title) . '</a>';
    $result .= '</li>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_tab', 'bs_tab' );

function bs_dropdown( $params, $content=null ) {
    extract( shortcode_atts( array(
        'title' => '',
        'id' => '',
        'class' => '',
        ), $params ) );
    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<li class="dropdown" role="presentation">';
    $class_attr = trim( esc_attr( $class ) . ' dropdown-toggle' );
    $result .= '<a class="' . $class_attr . '" id="' . esc_attr($id) . '" data-toggle="dropdown">' . esc_attr($title) . '<b class="caret"></b></a>';
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
    $labelled = '' !== $id ? ' aria-labelledby="tab-' . esc_attr( $id ) . '"' : '';
    $result = '<div class="tab-pane fade ' . esc_attr($class) . '" id="' . esc_attr($id) . '" role="tabpanel"' . $labelled . '>';
    $result .= do_shortcode( $content );
    $result .= '</div>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_tcontent', 'bs_tcontent' );
