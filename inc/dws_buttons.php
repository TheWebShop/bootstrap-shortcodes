<?php 
/**
 *DesignWall shortcodes grid
 *@package DesignWall Shorcodes
 *@since 1.0
*/

/**
 * Button
 */
function dws_buttons($params, $content = null){
	extract(shortcode_atts(array(
		'size' => 'default',
		'type' => 'default',
		'value' => 'button',
		'href' => "#"
	), $params));

	$content = preg_replace('/<br class="nc".\/>/', '', $content);
	$result = '<a class="btn btn-'.$size.' btn-'.$type.'" href="'.$href.'">'.$value.'</a>';
	return force_balance_tags( $result );
}
add_shortcode('button', 'dws_buttons');
