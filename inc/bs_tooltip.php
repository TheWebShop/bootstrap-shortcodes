<?php 

function bs_tooltip($params, $content = null){
    extract(shortcode_atts(array(
    	'id' => 'bs_tooltip_' . rand(1,100),
        'state' => '',
        'placement' => 'top',
        'href' => "#"
    ), $params));
	
    
    $placement = (in_array($placement, array('top', 'right', 'bottom', 'left'))) ? $placement : "top";
    $ArgState = ($state <> '') ? "'" . esc_attr( $state ) . "'" : ''; 
    $content = preg_replace('/<br class="nc".\/>/', '', $content);
    $title = explode("\n", wordwrap($content, 20, "\n"));
    // Maybe use $("[data-toggle='tooltip']").tooltip();
    $result = '<script>jQuery(function($) { $("#'.$id.'").tooltip('.$ArgState.');});</script>';
    $result .= '<a href="#" data-toggle="tooltip" data-placement="'.esc_attr($placement).'" id="'.$id.'" title="'.$title[0].'">'.esc_attr($content).'</a>';
    return force_balance_tags( $result );
}
add_shortcode('tooltip', 'bs_tooltip');
