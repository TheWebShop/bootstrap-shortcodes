<?php
function bs_buttons( $params, $content=null ) {
    extract(shortcode_atts(array(
        'size' => '',
        'type' => 'default',
        'value' => 'button',
        'href' => "#",
        'target' => ''
    ), $params ) );

    $content = preg_replace( '/<br class="nc".\/>/', '', $content );
    $size_class = ($size && $size !== $type) ? ' btn-' . esc_attr($size) : '';
    $target = strtolower( trim( $target ) );
    $target_attr = '';
    if ( in_array( $target, array( '_blank', '_self', '_parent', '_top' ), true ) ) {
        $target_attr = ' target="' . esc_attr($target) . '"';
        if ( $target === '_blank' ) {
            $target_attr .= ' rel="noopener noreferrer"';
        }
    }
    $result = '<a class="btn btn-' . esc_attr($type) . $size_class . '" href="' . esc_url($href) . '"' . $target_attr . '>' . esc_attr($value) . '</a>';
    return force_balance_tags( $result );
}
add_shortcode( 'bs_button', 'bs_buttons' );
