<?php
require __DIR__ . '/smoke-bootstrap.php';
$plate_w = 529.1; $plate_h = 116;
$config = PCKZ_Plate_Calibration::default_product_config();
$refs = PCKZ_Ledos_Preview::layer_refs();
$text_box = PCKZ_Ledos_Preview::ref_to_mm_box($refs['text'], $plate_w, $plate_h, 'bottom-left');
$y_top_svg = $plate_h - $text_box['y_mm'] - $text_box['height_mm'];
$y_bot_svg = $plate_h - $text_box['y_mm'];
$text_plate_paths = sprintf('<g id="pckz-text-engrave"><path d="M %s %s L %s %s L %s %s L %s %s Z"/></g>',
  $text_box['x_mm']+2, $y_top_svg+2, $text_box['x_mm']+$text_box['width_mm']-2, $y_top_svg+2,
  $text_box['x_mm']+$text_box['width_mm']-2, $y_bot_svg-2, $text_box['x_mm']+2, $y_bot_svg-2);
$svg = '<?xml version="1.0"?><svg viewBox="0 0 529.1 116"><g id="pckz-lines"><ellipse cx="200" cy="60" rx="40" ry="8"/></g></svg>';
$objects=[['id'=>'pckz-text','role'=>'text','bbox'=>$text_box,'x_mm'=>$text_box['x_mm'],'y_mm'=>$text_box['y_mm'],'width_mm'=>$text_box['width_mm'],'height_mm'=>$text_box['height_mm'],'text'=>'AB 123 CD','font_family'=>'Russo One']];
$canonical=['format'=>'pckzce-canonical-scene','version'=>2,'coordinate_system'=>'lightburn-mm-bottom-left','plate'=>['width_mm'=>$plate_w,'height_mm'=>$plate_h],'objects'=>$objects,'selections'=>['custom_text'=>'AB 123 CD']];
$package=['canonical_scene'=>$canonical,'config'=>$config,'layout'=>PCKZ_Canonical_Scene::to_layout($canonical),'selections'=>$canonical['selections'],'production_vector_svg'=>$svg,'text_plate_paths'=>$text_plate_paths,'design_id'=>1];
$scene=PCKZ_Production_Scene::from_canonical_layout($package);
if(is_wp_error($scene)){echo $scene->get_error_message();exit(1);}
foreach($scene['layers'] as $layer){
  echo ($layer['layer_id']??'?').' role='.($layer['role']??'?')."\n";
  echo '  placement='.json_encode($layer['placement_bbox_mm']??null)."\n";
  echo '  measured='.json_encode($layer['measured_bbox_mm']??null)."\n";
}
echo 'parity='.(PCKZ_Export_Parity::validate($canonical,$scene)['status'])."\n";
