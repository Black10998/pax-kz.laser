#!/usr/bin/env php
<?php
define('ABSPATH','/tmp/wp/'); define('WP_CONTENT_DIR','/tmp/wp-content'); define('DAY_IN_SECONDS',86400); define('PCKZCE_VERSION','2.8.0');
$plugin_dir='/workspace/pckz-canonical-engine/';
$line_svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><ellipse cx="25" cy="10" rx="20" ry="3"/><ellipse cx="75" cy="10" rx="20" ry="3"/></svg>';
foreach(['__'=>fn($s)=>$s,'current_time'=>fn()=>gmdate('c'),'is_wp_error'=>fn($t)=>$t instanceof WP_Error,'wp_json_encode'=>fn($d)=>json_encode($d),'wp_parse_args'=>fn($a,$d=[])=>array_merge($d,is_array($a)?$a:[]),'sanitize_file_name'=>fn($f)=>$f,'sanitize_key'=>fn($k)=>$k,'sanitize_hex_color'=>fn($c)=>$c,'sanitize_title'=>fn($t)=>$t,'sanitize_svg_fragment'=>fn($s)=>$s,'esc_url_raw'=>fn($u)=>$u,'esc_url'=>fn($u)=>$u,'esc_attr'=>fn($t)=>$t,'esc_html'=>fn($t)=>$t,'esc_html__'=>fn($t)=>$t,'esc_textarea'=>fn($t)=>$t,'esc_xml'=>fn($t)=>$t,'content_url'=>fn($p='')=>'http://ex/wp-content'.$p,'home_url'=>fn($p='')=>'http://ex'.$p,'trailingslashit'=>fn($s)=>rtrim($s,'/\\').'/','plugin_dir_path'=>fn($f)=>dirname($f).'/','plugin_dir_url'=>fn($f)=>'file://'.dirname($f).'/','plugin_basename'=>fn($f)=>basename($f),'wp_upload_dir'=>fn()=>['path'=>'/tmp/up','url'=>'http://ex/up','error'=>false,'basedir'=>'/tmp/up','baseurl'=>'http://ex/up'],'wp_mkdir_p'=>fn($t)=>is_dir($t)||mkdir($t,0777,true),'wp_unique_filename'=>fn($d,$f)=>$f,'wp_generate_uuid4'=>fn()=>bin2hex(random_bytes(16)),'get_transient'=>fn()=>false,'set_transient'=>fn()=>true] as $n=>$fn){if(!function_exists($n)){$GLOBALS["s_$n"]=$fn;eval("function $n(...\$a){return (\$GLOBALS['s_$n'])(...\$a);}");}}
function wp_remote_get($url,$args=[]){global $line_svg; if(strpos($url,'line-type')!==false||strpos($url,'Line1')!==false) return ['response'=>['code'=>200],'body'=>$line_svg]; if(strpos($url,'Icon_background')!==false) return ['response'=>['code'=>200],'body'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>']; return new WP_Error('no','');}
function wp_remote_retrieve_response_code($r){return is_array($r)?($r['response']['code']??0):0;}
function wp_remote_retrieve_body($r){return is_array($r)?($r['body']??''):'';}
class WP_Error { private $m,$c,$d; public function __construct($c='',$m='',$d=''){$this->c=$c;$this->m=$m;$this->d=$d;} public function get_error_message(){return $this->m;} public function get_error_data(){return $this->d;} public function get_error_code(){return $this->c;}}
define('PCKZCE_PLUGIN_FILE',$plugin_dir.'pckz-canonical-engine.php'); define('PCKZCE_PLUGIN_DIR',$plugin_dir); define('PCKZCE_PLUGIN_URL','file://'.$plugin_dir); define('PCKZCE_PLUGIN_BASENAME','pckz-canonical-engine/pckz-canonical-engine.php');
require_once PCKZCE_PLUGIN_DIR.'includes/class-pckz-autoloader.php'; PCKZ_Autoloader::register();

$refs = PCKZ_Ledos_Preview::layer_refs();
$mm_w=529.1; $mm_h=116;
$objects=[];
$map=[
  ['id'=>'pckz-lines','role'=>'lines','ref'=>'lines','line_type'=>'type_1'],
  ['id'=>'pckz-icon-bg-left','role'=>'icon-bg-left','ref'=>'iconBgLeft','symbol'=>''],
  ['id'=>'pckz-icon-bg-right','role'=>'icon-bg-right','ref'=>'iconBgRight','symbol'=>''],
  ['id'=>'pckz-icon-left','role'=>'icon-left','ref'=>'iconLeft','symbol'=>'instagram'],
  ['id'=>'pckz-icon-right','role'=>'icon-right','ref'=>'iconRight','symbol'=>'instagram'],
  ['id'=>'pckz-text','role'=>'text','ref'=>'text','text'=>'AB 12'],
];
foreach($map as $spec){
  $bbox = PCKZ_Ledos_Preview::ref_to_mm_box($refs[$spec['ref']], $mm_w, $mm_h, 'bottom-left');
  // Simulate legacy wrong center_y bug
  $bbox['center_y_mm'] = $bbox['y_mm'] + $bbox['height_mm'];
  $entry = ['id'=>$spec['id'],'role'=>$spec['role'],'bbox'=>$bbox,'x_mm'=>$bbox['x_mm'],'y_mm'=>$bbox['y_mm'],'width_mm'=>$bbox['width_mm'],'height_mm'=>$bbox['height_mm'],'scale'=>['x'=>1,'y'=>1],'rotation_deg'=>0,'z_order'=>10,'color'=>'#fff'];
  if(!empty($spec['line_type'])) $entry['line_type']=$spec['line_type'];
  if(!empty($spec['symbol'])) $entry['symbol']=$spec['symbol'];
  if(!empty($spec['text'])) {$entry['text']=$spec['text']; $entry['font_family']='Russo One';}
  $objects[]=$entry;
}

$text_bbox = $objects[5]['bbox'];
$text_plate_paths = sprintf(
    '<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M %1$s %2$s L %3$s %2$s L %3$s %4$s L %1$s %4$s Z"/></g>',
    $text_bbox['x_mm'] + 5,
    $mm_h - ( $text_bbox['y_mm'] + $text_bbox['height_mm'] ) + 5,
    $text_bbox['x_mm'] + $text_bbox['width_mm'] - 5,
    $mm_h - $text_bbox['y_mm'] - 5
);

$canonical=['format'=>'pckzce-canonical-scene','version'=>2,'coordinate_system'=>'lightburn-mm-bottom-left','plate'=>['width_mm'=>$mm_w,'height_mm'=>$mm_h],'selections'=>['linien'=>'type_1','symbol_links'=>'instagram','symbol_rechts'=>'instagram'],'objects'=>$objects];
$package=PCKZ_Export_Engine::run(['canonical_scene'=>json_encode($canonical),'config'=>[],'canvas_json'=>'{}','design_id'=>1,'selections'=>$canonical['selections'],'text_plate_paths'=>$text_plate_paths]);
if(is_wp_error($package)){fwrite(STDERR,json_encode(['message'=>$package->get_error_message(),'validation'=>$package->get_error_data()],JSON_PRETTY_PRINT)."\n"); exit(1);} 
echo "OK geometry parity: ".count($objects)." objects\n";
