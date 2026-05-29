#!/usr/bin/env php
<?php
define('ABSPATH', '/tmp/wp/');
define('DAY_IN_SECONDS', 86400);
define('WP_CONTENT_DIR', '/tmp/wp-content');

$plugin_dir = dirname( __DIR__ ) . '/';
$icon_path  = $plugin_dir . 'public/images/icons/instagram-white.svg';
$icon_url = 'file://' . $icon_path;
$line_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><ellipse cx="25" cy="10" rx="20" ry="3" fill="#f00"/><ellipse cx="75" cy="10" rx="20" ry="3" fill="#f00"/></svg>';
$line_url = 'https://example.com/line-type-1.svg';

foreach ([
    '__'=>fn($s)=>$s,'current_time'=>fn($t='mysql')=>gmdate('Y-m-d H:i:s'),
    'is_wp_error'=>fn($t)=>$t instanceof WP_Error,'wp_json_encode'=>fn($d)=>json_encode($d),
    'wp_parse_args'=>fn($a,$d=[])=>array_merge($d,is_array($a)?$a:[]),
    'sanitize_file_name'=>fn($f)=>$f,'sanitize_key'=>fn($k)=>$k,'sanitize_hex_color'=>fn($c)=>$c,
    'sanitize_title'=>fn($t)=>$t,'sanitize_svg_fragment'=>fn($s)=>$s,
    'esc_url_raw'=>fn($u)=>$u,'esc_url'=>fn($u)=>$u,'esc_attr'=>fn($t)=>$t,'esc_html'=>fn($t)=>$t,
    'esc_html__'=>fn($t)=>$t,'esc_textarea'=>fn($t)=>$t,'esc_xml'=>fn($t)=>$t,
    'content_url'=>fn($p='')=>'http://ex/wp-content'.$p,'home_url'=>fn($p='')=>'http://ex'.$p,
    'trailingslashit'=>fn($s)=>rtrim($s,'/\\').'/',
    'plugin_dir_path'=>fn($f)=>dirname($f).'/','plugin_dir_url'=>fn($f)=>'file://'.dirname($f).'/' ,
    'plugin_basename'=>fn($f)=>basename($f),
    'wp_upload_dir'=>fn()=>['path'=>'/tmp/up','url'=>'http://ex/up','error'=>false,'basedir'=>'/tmp/up','baseurl'=>'http://ex/up'],
    'wp_mkdir_p'=>fn($t)=>is_dir($t)||mkdir($t,0777,true),'wp_unique_filename'=>fn($d,$f)=>$f,
    'wp_generate_uuid4'=>fn()=>bin2hex(random_bytes(16)),'get_transient'=>fn()=>false,'set_transient'=>fn()=>true,
    'DAY_IN_SECONDS'=>86400,
] as $n=>$fn) { if (!function_exists($n)) { $GLOBALS["s_$n"]=$fn; eval("function $n(...\$a){return (\$GLOBALS['s_$n'])(...\$a);}"); } }

function wp_remote_get($url, $args=[]) {
    global $line_svg, $icon_path;
    if ($url === 'https://example.com/line-type-1.svg') return ['response'=>['code'=>200],'body'=>$line_svg];
    return new WP_Error('no','');
}
function wp_remote_retrieve_response_code($r) { return is_array($r)?($r['response']['code']??0):0; }
function wp_remote_retrieve_body($r) { return is_array($r)?($r['body']??''):''; }

class WP_Error { private $m,$c,$d; public function __construct($c='',$m='',$d=''){$this->c=$c;$this->m=$m;$this->d=$d;} public function get_error_message(){return $this->m;} public function get_error_data(){return $this->d;} public function get_error_code(){return $this->c;} }

define( 'PCKZCE_PLUGIN_FILE', $plugin_dir . 'pckz-canonical-engine.php' );
define( 'PCKZCE_PLUGIN_DIR', $plugin_dir );
define( 'PCKZCE_PLUGIN_URL', 'file://' . $plugin_dir );
define( 'PCKZCE_VERSION', '2.9.4' );
define('PCKZCE_PLUGIN_BASENAME', 'pckz-canonical-engine/pckz-canonical-engine.php');
require_once PCKZCE_PLUGIN_DIR.'includes/class-pckz-autoloader.php';
PCKZ_Autoloader::register();

$canonical = [
  'format'=>'pckzce-canonical-scene','version'=>2,'coordinate_system'=>'lightburn-mm-bottom-left',
  'plate'=>['width_mm'=>525,'height_mm'=>145],
  'design_px'=>['width'=>3651,'height'=>2132],
  'selections'=>['linien'=>'type_1','symbol_links'=>'instagram'],
  'objects'=>[
    ['id'=>'pckz-lines','role'=>'lines','line_type'=>'type_1','svg_ref'=>$line_url,
     'bbox'=>['x_mm'=>88,'y_mm'=>40,'width_mm'=>348,'height_mm'=>65,'center_x_mm'=>262,'center_y_mm'=>72.5],
     'x_mm'=>88,'y_mm'=>40,'width_mm'=>348,'height_mm'=>65,'scale'=>['x'=>1,'y'=>1],'rotation_deg'=>0,'z_order'=>10,'color'=>'#FF0000'],
    ['id'=>'pckz-text','role'=>'text','text'=>'AB 12','font_family'=>'Russo One',
     'bbox'=>['x_mm'=>113.6,'y_mm'=>50,'width_mm'=>200,'height_mm'=>30,'center_x_mm'=>213.6,'center_y_mm'=>65],
     'x_mm'=>113.6,'y_mm'=>50,'width_mm'=>200,'height_mm'=>30,'scale'=>['x'=>1,'y'=>1],'rotation_deg'=>0,'z_order'=>30,'color'=>'#FFF'],
  ],
];

$text_plate_paths = '<g id="pckz-text-engrave" fill="#FFFFFF"><path d="M 118.6 90 L 308.6 90 L 308.6 110 L 118.6 110 Z"/></g>';

$production_vector_svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 525 145">'
	. '<metadata id="pckz-export-meta"><pckz:export coordinate-system="lightburn-mm-bottom-left"/></metadata>'
	. '<g id="pckz-engrave"><g id="pckz-line-0"><path d="M 88 72 L 436 72" fill="#ff0000"/></g></g></svg>';

$package = PCKZ_Export_Engine::run(
	array(
		'canonical_scene'       => json_encode( $canonical ),
		'config'                => array(),
		'canvas_json'           => '{}',
		'design_id'             => 1,
		'selections'            => $canonical['selections'],
		'production_vector_svg' => $production_vector_svg,
		'text_plate_paths'      => $text_plate_paths,
	)
);
if (is_wp_error($package)) {
  fwrite(STDERR, json_encode(['message'=>$package->get_error_message(),'validation'=>$package->get_error_data()], JSON_PRETTY_PRINT)."
");
  exit(1);
}
echo "OK layers=".count($package['production_scene']['layers']??[])."
";
