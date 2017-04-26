<?php
function bs_buttons( $params, $content=null ) {
    extract(shortcode_atts(array(
        'size' => 'default',
        'mode' => 'default',
        'type' => 'secondary',
        'value' => 'button',
        'href' => "#"
    ), $params ) );

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $result = '<a class="btn' . (($size != 'md' ) ? ' btn-'.$size : '') . (($mode == 'block' ) ? ' btn-block' : '') . ' btn-' . $type . '" href="' . (($href != '') ? $href : '#') . '">' . (($value != '') ? $value : 'Button') . '</a>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_button', 'bs_buttons' );
