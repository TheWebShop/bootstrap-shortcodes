<?php 
/**
 *DesignWall shortcodes tabs
 *@package DesignWall Shorcodes
 *@since 1.0
*/

/**
 * Collapse
 */

function dws_collapse($params, $content = null){
	extract(shortcode_atts(array(
		'id'=>''
 		), $params));
	$content = preg_replace('/<br class="nc".\/>/', '', $content);
	$result = '<div class="accordion" id="'.$id.'">';
	$result .= do_shortcode($content );
	$result .= '</div>'; 
	return force_balance_tags( $result );
}
add_shortcode('collapse', 'dws_collapse');


function dws_citem($params, $content = null){
	extract(shortcode_atts(array(
		'id'=>'',
		'title'=>'Collapse title',
		'parent' => ''
 		), $params));
	$content = preg_replace('/<br class="nc".\/>/', '', $content);
	$result = ' <div class="accordion-group">';
	$result .= ' <div class="accordion-heading">';
	$result .= '<a class="accordion-toggle" data-toggle="collapse" data-parent="#'.$parent.'" href="#'.$id.'">';
	$result .= $title;
	$result .= '</a>';
	$result .= '</div>';
	$result .= '<div id="'.$id.'" class="accordion-body collapse">';
	$result .= '<div class="accordion-inner">';
	$result .= do_shortcode($content );
	$result .= '</div>'; 
	$result .= '</div>'; 
	$result .= '</div>'; 
	return force_balance_tags( $result );
}
add_shortcode('citem', 'dws_citem');


