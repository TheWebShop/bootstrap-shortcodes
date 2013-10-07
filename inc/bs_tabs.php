<?php 
//-------------- 
//  [tabs]
//    [thead]
//      [tab href="#link" title="title"]
//      [dropdown title="title"]
//        [tab href="#link" title="title"]
//      [/dropdown]
//    [/thead]
//    [tcontents]
//      [tcontent id="link"]
//      [/tcontent]
//    [/tcontents]
//  [/tabs]
//  ---------------

function bs_tabs($params, $content = null){
  $content = preg_replace('/<br class="nc".\/>/', '', $content);
  $result = '<div class="tab_wrap">';
  $result .= do_shortcode($content );
  $result .= '</div>'; 
  return force_balance_tags( $result );
}
add_shortcode('tabs', 'bs_tabs');

function bs_thead($params, $content = null){
  $content = preg_replace('/<br class="nc".\/>/', '', $content);
  $result = '<ul class="nav nav-tabs">';
  $result .= do_shortcode($content );
  $result .= '</ul>'; 
  return force_balance_tags( $result );
}
add_shortcode('thead', 'bs_thead');

function bs_tab($params, $content = null){
  extract(shortcode_atts(array(
    'href' => '#',
    'title' => '',
    'class' => ''
    ), $params));
  $content = preg_replace('/<br class="nc".\/>/', '', $content);

  $result = '<li class="'.$class.'">';
  $result .= '<a data-toggle="tab" href="'.$href.'">'.$title.'</a>';
  $result .= '</li>'; 
  return force_balance_tags( $result );
}
add_shortcode('tab', 'bs_tab');

function bs_dropdown($params, $content = null){
  global $bs_timestamp;
  extract(shortcode_atts(array(
    'title' => '',
    'id' => '',
    'class' => '',
    ), $params));
  $content = preg_replace('/<br class="nc".\/>/', '', $content);
  $result = '<li class="dropdown">';
  $result .= '<a class="'.$class.'" id="'.$id.'" class="dropdown-toggle" data-toggle="dropdown">'.$title.'<b class="caret"></b></a>';
  $result .='<ul class="dropdown-menu">';
  $result .= do_shortcode($content);
  $result .= '</ul></li>'; 
  return force_balance_tags( $result );
}
add_shortcode('dropdown', 'bs_dropdown');

function bs_tcontents($params, $content = null){
  $content = preg_replace('/<br class="nc".\/>/', '', $content);
  $result = '<div class="tab-content">';
  $result .= do_shortcode($content );
  $result .= '</div>'; 
  return force_balance_tags( $result );
}
add_shortcode('tcontents', 'bs_tcontents');

function bs_tcontent($params, $content = null){
  extract(shortcode_atts(array(
    'id' => '',
    'class'=>'',
    ), $params));
  $content = preg_replace('/<br class="nc".\/>/', '', $content);
  $class= ($class=='active')?'active in':'';
  $result = '<div class="tab-pane fade '.$class.'" id='.$id.'>';
  $result .= do_shortcode($content );
  $result .= '</div>'; 
  return force_balance_tags( $result );
}
add_shortcode('tcontent', 'bs_tcontent');
