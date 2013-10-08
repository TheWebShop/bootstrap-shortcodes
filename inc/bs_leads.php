<?php 
function bs_leads($params, $content = null){
    $content = preg_replace('/<br class="nc".\/>/', '', $content);
    $result = '<div class="lead">';
    $result .= do_shortcode($content );
    $result .= '</div>';

    return force_balance_tags( $result );
}
add_shortcode('leads', 'bs_leads');
