<?php
/** Public contract behavior tests. HTTP is always intercepted; no SMS is sent. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = array();
$GLOBALS['hooks'] = array();
$GLOBALS['errors'] = array();
$GLOBALS['http'] = array();
$GLOBALS['notes'] = array();
$GLOBALS['entry_meta'] = array();
$GLOBALS['can_manage'] = true;
$GLOBALS['registered'] = array();
$GLOBALS['http_callback'] = null;
$GLOBALS['force_stale_option'] = '';
$GLOBALS['database_failure'] = false;
$GLOBALS['cas_once_conflict'] = false;
$GLOBALS['valid_nonce'] = true;
class WP_Error {
    private $code, $message, $data;
    public function __construct($code='', $message='', $data=null) {$this->code=$code;$this->message=$message;$this->data=$data;}
    public function get_error_code(){return $this->code;}
    public function get_error_message(){return $this->message;}
    public function get_error_data(){return $this->data;}
}
function absint($value){return abs((int)$value);}
function is_wp_error($value){return $value instanceof WP_Error;}
function get_option($key,$default=false){if($key===$GLOBALS['force_stale_option'])return $default;return array_key_exists($key,$GLOBALS['options'])?$GLOBALS['options'][$key]:$default;}
function maybe_serialize($value){return is_array($value)||is_object($value)?serialize($value):$value;}
function maybe_unserialize($value){$decoded=@unserialize($value);return false!==$decoded||$value==='b:0;'?$decoded:$value;}
function wp_cache_delete($key,$group=''){return true;}
class SyntheticWPDB {
    public $options='wp_options';
    public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
    public function get_var($query){return isset($GLOBALS['options'][$query['args'][0]])?maybe_serialize($GLOBALS['options'][$query['args'][0]]):null;}
    public function query($query){if($GLOBALS['database_failure'])return false;if(strpos($query['sql'],'UPDATE ')===0){$args=$query['args'];if($GLOBALS['cas_once_conflict']){$GLOBALS['cas_once_conflict']=false;$GLOBALS['options'][$args[1]]['results'][0]['status']='delivered';return 0;}if(!array_key_exists($args[1],$GLOBALS['options'])||maybe_serialize($GLOBALS['options'][$args[1]])!==$args[2])return 0;$GLOBALS['options'][$args[1]]=maybe_unserialize($args[0]);return 1;}if(strpos($query['sql'],'INSERT IGNORE')!==false){$args=$query['args'];if(array_key_exists($args[0],$GLOBALS['options']))return 0;$GLOBALS['options'][$args[0]]=unserialize($args[1]);return 1;}return false;}
    public function esc_like($value){return addcslashes($value,'_%\\');}
    public function get_col($query){$pattern=$query['args'][0];$prefix=stripslashes(substr($pattern,0,-1));return array_values(array_filter(array_keys($GLOBALS['options']),function($name)use($prefix){return strpos($name,$prefix)===0;}));}
}
$GLOBALS['wpdb']=new SyntheticWPDB();
function add_option($key,$value='',$deprecated='',$autoload=true){if(array_key_exists($key,$GLOBALS['options']))return false;$GLOBALS['options'][$key]=$value;return true;}
function update_option($key,$value,$autoload=null){$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['options'][$key]);return true;}
function add_settings_error($setting,$code,$message){$GLOBALS['errors'][]=array($setting,$code,$message);}
function __($value,$domain=''){return $value;}
function esc_html__($value,$domain=''){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function esc_attr__($value,$domain=''){return esc_attr($value);}
function esc_html($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function esc_attr($value){return esc_html($value);}
function esc_url($value){return esc_html($value);}
function wp_parse_url($value,$component=-1){return parse_url($value,$component);}
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);}
function wp_strip_all_tags($value,$remove_breaks=false){return strip_tags($value);}
function sanitize_text_field($value){return trim(strip_tags((string)$value));}
function sanitize_textarea_field($value){return trim(strip_tags((string)$value));}
function sanitize_key($value){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$value));}
function wp_unslash($value){return $value;}
function wp_salt($scheme='auth'){return 'synthetic-ava-gf-salt';}
function wp_generate_uuid4(){static $n=0;return '00000000-0000-4000-8000-'.str_pad((string)++$n,12,'0',STR_PAD_LEFT);}
function wp_hash($value,$scheme='auth'){return hash_hmac('md5',$value,'synthetic-ava-gf-salt');}
function current_time($format,$gmt=false){return 'mysql'===$format?'2026-11-05 10:00:00':1793872800;}
function wp_safe_remote_request($url,$args){$GLOBALS['http'][]=array($url,$args);if(is_callable($GLOBALS['http_callback']))return call_user_func($GLOBALS['http_callback'],$url,$args);return $GLOBALS['next_http'];}
function wp_remote_retrieve_response_code($response){return $response['response']['code']??0;}
function wp_remote_retrieve_body($response){return $response['body']??'';}
function add_filter($hook,$callback,$priority=10,$args=1){$GLOBALS['hooks'][$hook][]=array($callback,$priority,$args);}
function add_action($hook,$callback,$priority=10,$args=1){add_filter($hook,$callback,$priority,$args);}
function is_admin(){return true;}
function current_user_can($cap){return $GLOBALS['can_manage'];}
function plugin_basename($file){return basename($file);}
function register_setting($group,$option,$args){$GLOBALS['registered'][$option]=array($group,$args);}
function add_options_page(...$args){$GLOBALS['menu']=$args;}
function wp_enqueue_style(...$args){}
function plugins_url($path,$file){return 'https://wordpress.invalid/plugins/'.$path;}
function home_url($path=''){return 'https://wordpress.invalid'.$path;}
function admin_url($path){return 'https://wordpress.invalid/wp-admin/'.$path;}
function settings_fields($group){echo '<input type="hidden" name="_wpnonce" value="synthetic-nonce">';}
function settings_errors($group){}
function wp_nonce_field($action){echo '<input name=\"_wpnonce\" value=\"synthetic-nonce\">';}
function wp_die($message,$title='',$args=array()){throw new RuntimeException('wp_die:'.$message);}
function check_admin_referer($action){if(!$GLOBALS['valid_nonce'])wp_die('nonce denied');return true;}
function wp_safe_redirect($location){$GLOBALS['redirect']=$location;throw new RuntimeException('synthetic_redirect');}
function submit_button($label='Save'){echo '<button>'.esc_html($label).'</button>';}
function checked($value,$expected=true,$echo=true){$v=$value===$expected?' checked':'';if($echo)echo $v;return $v;}
function disabled($value,$expected=true,$echo=true){$v=$value===$expected?' disabled':'';if($echo)echo $v;return $v;}
function selected($value,$expected=true,$echo=true){$v=$value===$expected?' selected':'';if($echo)echo $v;return $v;}
function rgar($array,$key,$default=null){return is_array($array)&&array_key_exists($key,$array)?$array[$key]:$default;}
function rgars($array,$keys,$default=null){foreach(explode('/',$keys)as$key){if(!is_array($array)||!array_key_exists($key,$array))return $default;$array=$array[$key];}return $array;}
function gform_get_meta($id,$key){return $GLOBALS['entry_meta'][$id][$key]??null;}
function gform_update_meta($id,$key,$value,$form_id=null){$GLOBALS['entry_meta'][$id][$key]=$value;}
class GFCommon {
    public static function replace_variables($text,$form,$entry,$url_encode=false,$esc_html=false,$nl2br=true,$format='html',$aux=false){return strtr($text,array('{Name:1}'=>$entry['1']??'','{form_title}'=>$form['title']??'','{entry_id}'=>(string)($entry['id']??'')));}
    public static function add_note($entry_id,$user_id,$user_name,$note,$type='note'){$GLOBALS['notes'][]=$note;}
}
class GFAPI {public static function get_entry($id){return $GLOBALS['gfapi_entry'];}}
class GFForms {public static $version='2.9.0';public static function include_feed_addon_framework(){}public static function get_page(){return 'form_settings';}}
class GFAddOn {public static $registered=array();public static function register($name){self::$registered[]=$name;}}
class GFFeedAddOn extends GFAddOn {
    public function init(){}public function init_admin(){}public function init_frontend(){}
    public function get_field_value($form,$entry,$field_id){return $entry[(string)$field_id]??'';}
    public function add_feed_error($message,$feed,$entry,$form){$GLOBALS['notes'][]=$message;}
    public function add_note($entry_id,$message,$type='note'){$GLOBALS['notes'][]=$message;}
    public function is_gravityforms_supported(){return true;}
    public function log_debug($message){}public function log_error($message){}
}
require dirname(__DIR__).'/ava-gravityforms-plugin.php';
foreach(array('settings','phone','api','delivery','admin','addon')as$file){$p=dirname(__DIR__).'/includes/class-'.$file.'.php';if(is_file($p))require_once $p;}
use AvaGF\Settings;
use AvaGF\Phone;
use AvaGF\Api;
use AvaGF\Delivery;
use AvaGF\Admin;
$checks=0;
function check($condition,$label){global$checks;++$checks;if(!$condition){fwrite(STDERR,'FAIL: '.$label."\n");exit(1);}}
function same($actual,$expected,$label){check($actual===$expected,$label);}
function reply($code=202,$body=null){return array('response'=>array('code'=>$code),'body'=>null===$body?json_encode(array('id'=>'synthetic-job-1','status'=>'pending','requires_review'=>true,'review_status'=>'pending','campaign_status'=>'draft')):$body);}
function configured(){return array_merge(Settings::defaults(),array('enabled'=>'yes','api_key'=>'ava_live_'.str_repeat('a',24)));}
function calls(){return count($GLOBALS['http']);}
function feed($id=21,$meta=array()){return array('id'=>$id,'form_id'=>7,'is_active'=>true,'meta'=>array_merge(array('feedName'=>'Synthetic feed','recipientMode'=>'field','phoneField'=>'2','fixedPhones'=>'','message'=>'سلام {Name:1}، فرم {form_title} ثبت شد.'),$meta));}
function entry($id=31){return array('id'=>$id,'form_id'=>7,'status'=>'active','1'=>'نمونه','2'=>'۰۹۱۲۰۰۰۰۰۰۱');}
function form(){return array('id'=>7,'title'=>'فرم نمونه','fields'=>array((object)array('id'=>2,'type'=>'phone')));}
same(Settings::get()['enabled'],'no','new installations disabled');
same(Settings::get()['api_url'],'https://ava.tarpardaz.com','default Ava origin');
foreach(array('09123456789','+989123456789','00989123456789','989123456789','9123456789','۰۹۱۲۳۴۵۶۷۸۹','٠٩١٢٣٤٥٦٧٨٩','(0912) 345-6789')as$phone)same(Phone::normalize($phone),'09123456789','Iran number normalization');
foreach(array('02112345678','+14155552671','',array(),'09123456789oops')as$phone)check(is_wp_error(Phone::normalize($phone)),'invalid number rejected');
check(strpos(Phone::mask('09123456789'),'09123456789')===false,'recipient masking hides full number');
same(Settings::valid_url('https://AVA.example/'),'https://ava.example','origin normalized');
foreach(array('http://ava.example','https://127.0.0.1','https://localhost','https://user:pass@ava.example','https://ava.example/api/v1','https://ava.example?x=1','https://ava.example#x','https://ava.example:8443',array())as$url)same(Settings::valid_url($url),false,'unsafe endpoint refused');
$GLOBALS['options'][Settings::OPTION]=configured();
$clean=Settings::sanitize(array('enabled'=>'yes','api_url'=>'https://panel.example','api_key'=>'','sender'=>'3000505'));
same($clean['api_key'],configured()['api_key'],'blank secret preserves existing credential');
same($clean['sender'],'3000505','authorized sender accepts digit syntax');
$clean=Settings::sanitize(array('enabled'=>'yes','api_url'=>'javascript:bad','api_key'=>"bad\r\nkey",'sender'=>'<script>'));
same($clean['api_url'],configured()['api_url'],'invalid URL preserves prior value');
same($clean['api_key'],configured()['api_key'],'invalid credential preserves prior value');
same(Settings::sanitize(array('api_url'=>'https://ava.example'))['enabled'],'no','unchecked connection disabled');
same(Settings::sanitize(array('api_url'=>'https://ava.example','delete_api_key'=>'yes'))['api_key'],'','explicit credential deletion');
$GLOBALS['next_http']=reply();
$result=Api::send('+989123456789','سلام نمونه','synthetic_request_1234');
check(!is_wp_error($result),'moderation response accepted');
same($result['status'],'pending','pending is preserved rather than promoted to delivered');
same($result['requires_review'],true,'moderation requirement preserved');
same($result['review_status'],'pending','review status preserved');
same($result['campaign_status'],'draft','campaign state preserved');
$request=end($GLOBALS['http']);$args=$request[1];
same($request[0],'https://ava.tarpardaz.com/api/v1/sms','regular SMS endpoint');
same(json_decode($args['body'],true),array('phone'=>'09123456789','text'=>'سلام نمونه'),'exact synthetic SMS payload');
same($args['headers']['Authorization'],'Bearer '.configured()['api_key'],'Bearer credential');
same($args['headers']['Idempotency-Key'],'synthetic_request_1234','stable idempotency header');
same($args['redirection'],0,'redirects prohibited');
same($args['sslverify'],true,'TLS verification enabled');
same($args['reject_unsafe_urls'],true,'WordPress safe URL checking requested');
same($args['limit_response_size'],65536,'response bounded');
$s=configured();$s['sender']='3000505';Api::send('09123456789','نمونه','synthetic_request_1234',$s);$request=end($GLOBALS['http']);
same(json_decode($request[1]['body'],true)['sender'],'3000505','optional sender included');
$before=calls();
foreach(array('','',str_repeat('a',1001),"\xff")as$text)check(is_wp_error(Api::send('09123456789',$text,'synthetic_request_1234')),'invalid message rejected');
check(is_wp_error(Api::send('09123456789','نمونه','short')),'short request ID rejected');
check(is_wp_error(Api::send('bad','نمونه','synthetic_request_1234')),'bad recipient rejected');
check(is_wp_error(Api::send('09123456789','نمونه','synthetic_request_1234',Settings::defaults())),'unconfigured API rejected');
same(calls(),$before,'invalid input never reaches HTTP');
foreach(array(400,401,403,404,409,422,429)as$code){$GLOBALS['next_http']=reply($code,'{"error":"secret-raw-body"}');$error=Api::send('09123456789','نمونه','synthetic_request_1234');check(is_wp_error($error),'HTTP rejection is an error');check(strpos($error->get_error_message(),'secret-raw-body')===false,'upstream error body hidden');}
foreach(array(new WP_Error('timeout','secret-raw-body'),reply(500,'secret-raw-body'),reply(0,'missing-status'),reply(302,'redirect'),reply(202,'{}'),reply(200,'<html>bad</html>'),reply(202,'{"id":"bad/<script>","status":"pending"}'),reply(202,'{"id":"synthetic","status":"unrecognized"}'))as$response){$GLOBALS['next_http']=$response;$before=calls();$error=Api::send('09123456789','نمونه','synthetic_request_1234');check(is_wp_error($error),'ambiguous response is an error');same($error->get_error_data()['uncertain'],true,'ambiguous POST uncertainty retained');same(calls(),$before+1,'ambiguous POST never automatically repeated');}
foreach(array('pending','sending','unknown','accepted','sent','delivered','undelivered','rejected','blocked','cancelled','simulated','expired')as$status){$GLOBALS['next_http']=reply(200,json_encode(array('id'=>'synthetic-report','status'=>$status)));$report=Api::report('synthetic-report');check(!is_wp_error($report),'known report status accepted');same($report['status'],$status,'report status preserved');$request=end($GLOBALS['http']);same($request[1]['method'],'GET','report uses GET');check(!isset($request[1]['headers']['Idempotency-Key']),'report never sends mutation header');}
$GLOBALS['next_http']=reply();
$GLOBALS['options'][Settings::OPTION]=Settings::defaults();$before=calls();same(Delivery::process(feed(),entry(),form()),null,'disabled delivery skips');same(calls(),$before,'disabled delivery no request');
$GLOBALS['options'][Settings::OPTION]=configured();foreach(array('partial','trash')as$status){$inactive=entry();$inactive['status']=$status;$before=calls();same(Delivery::process(feed(),$inactive,form()),null,'inactive '.$status.' entry skips');same(calls(),$before,'inactive entry never reaches HTTP');}$spam=entry();$spam['status']='spam';same(Delivery::process(feed(),$spam,form()),null,'spam entry skips');same(calls(),$before,'spam never calls API');
$result=Delivery::process(feed(),entry(),form());check(is_array($result)&&count($result)===1,'phone field produces one result');same(calls(),$before+1,'one admitted delivery request');
$request=end($GLOBALS['http']);$payload=json_decode($request[1]['body'],true);same($payload['phone'],'09120000001','phone field normalized');same($payload['text'],'سلام نمونه، فرم فرم نمونه ثبت شد.','merge tags rendered as text');
$summary=json_encode($result,JSON_UNESCAPED_UNICODE);check(strpos($summary,'09120000001')===false&&strpos($summary,configured()['api_key'])===false&&strpos($summary,$payload['text'])===false,'summary hides full number, key and message');
check(isset($GLOBALS['options']['ava_gf_delivery_31_21']),'durable atomic entry/feed guard recorded');
$before=calls();same(Delivery::process(feed(),entry(),form()),null,'duplicate callback skips');same(calls(),$before,'duplicate callback never calls HTTP');
$multi=feed(22,array('recipientMode'=>'fixed','fixedPhones'=>"09120000001, +989120000001\n۰۹۱۲۰۰۰۰۰۰۲"));$result=Delivery::process($multi,entry(32),form());check(is_array($result)&&count($result)===2,'fixed recipients deduplicated after normalization');same(calls(),$before+2,'only unique fixed numbers sent');
$before=calls();$bad=feed(23,array('recipientMode'=>'fixed','fixedPhones'=>'09120000001,bad'));check(is_wp_error(Delivery::process($bad,entry(33),form())),'one invalid fixed recipient rejects entire feed');same(calls(),$before,'invalid recipient list no partial send');
$too_many=feed(24,array('recipientMode'=>'fixed','fixedPhones'=>'09120000001,09120000002,09120000003,09120000004,09120000005,09120000006'));check(is_wp_error(Delivery::process($too_many,entry(34),form())),'more than five fixed recipients blocked');same(calls(),$before,'recipient cap blocks before API');
$no_phone=entry(35);unset($no_phone['2']);check(is_wp_error(Delivery::process(feed(25),$no_phone,form())),'missing mapped field refused');same(calls(),$before,'missing field never calls API');
$blank=feed(26,array('message'=>''));check(is_wp_error(Delivery::process($blank,entry(36),form())),'blank rendered message refused');same(calls(),$before,'blank message never calls API');
$GLOBALS['next_http']=new WP_Error('timeout','secret-raw-body');$result=Delivery::process(feed(27),entry(37),form());check(is_array($result)&&$result[0]['status']==='unknown','transport ambiguity stored as unknown');$before=calls();same(Delivery::process(feed(27),entry(37),form()),null,'unknown callback never retried');same(calls(),$before,'unknown result permanently deduplicated');
$GLOBALS['next_http']=reply();$nested=null;$GLOBALS['http_callback']=function()use(&$nested){$nested=Delivery::process(feed(28),entry(38),form());return reply();};$before=calls();Delivery::process(feed(28),entry(38),form());$GLOBALS['http_callback']=null;same($nested,null,'concurrent callback loses atomic claim');same(calls(),$before+1,'atomic guard prevents concurrent double send');
$GLOBALS['options']['ava_gf_delivery_39_29']=array('status'=>'processing');$before=calls();same(Delivery::process(feed(29),entry(39),form()),null,'unfinished prior worker remains guarded');same(calls(),$before,'interrupted worker never replayed');
if(class_exists('AvaGF\\Admin')){Admin::register();check(isset($GLOBALS['registered'][Settings::OPTION]),'settings option registered');same($GLOBALS['registered'][Settings::OPTION][1]['show_in_rest'],false,'settings secret excluded from REST');Admin::menu();same($GLOBALS['menu'][2],'manage_options','connection menu requires administrator capability');ob_start();Admin::page();$html=ob_get_clean();check(strpos($html,configured()['api_key'])===false,'admin page hides saved API secret');check(strpos($html,'name="_wpnonce"')!==false,'settings submit nonce present');$GLOBALS['can_manage']=false;ob_start();Admin::page();$html=ob_get_clean();same($html,'','unauthorized connection page has no output');$GLOBALS['can_manage']=true;}
if(class_exists('AvaGF\\AddOn')){$addon=\AvaGF\AddOn::get_instance();$fields=$addon->feed_settings_fields();$encoded=json_encode($fields,JSON_UNESCAPED_UNICODE);foreach(array('feedName','recipientMode','phoneField','fixedPhones','message','feed_condition')as$name)check(strpos($encoded,$name)!==false,'native feed field '.$name.' exists');$before=calls();$addon->process_feed(feed(40),entry(40),form());same(calls(),$before+1,'GF feed process invokes delivery');$notes=json_encode($GLOBALS['notes'],JSON_UNESCAPED_UNICODE);check(strpos($notes,'09120000001')===false&&strpos($notes,configured()['api_key'])===false,'entry note preserves recipient/key privacy');}
$GLOBALS['next_http']=reply();
$GLOBALS['can_manage']=false;$before=calls();try{Admin::refresh();check(false,'refresh denies subscriber');}catch(RuntimeException$error){check(strpos($error->getMessage(),'wp_die:')===0,'refresh denies subscriber');}same(calls(),$before,'unauthorized refresh never calls API');
$GLOBALS['can_manage']=true;$GLOBALS['valid_nonce']=false;$_POST['entry_id']=31;$_POST['feed_id']=21;try{Admin::refresh();check(false,'refresh requires nonce');}catch(RuntimeException$error){check(strpos($error->getMessage(),'nonce denied')!==false,'refresh rejects invalid nonce');}same(calls(),$before,'invalid nonce never calls API');$GLOBALS['valid_nonce']=true;
$GLOBALS['gfapi_entry']=entry();$GLOBALS['next_http']=reply(200,json_encode(array('id'=>'synthetic-job-1','status'=>'delivered','requires_review'=>false,'review_status'=>'approved','campaign_status'=>'completed')));try{Admin::refresh();check(false,'refresh redirects');}catch(RuntimeException$error){same($error->getMessage(),'synthetic_redirect','report refresh redirects to entry');}same(calls(),$before+1,'refresh performs one status read');$last=end($GLOBALS['http']);same($last[1]['method'],'GET','refresh only GET');same($GLOBALS['options']['ava_gf_delivery_31_21']['results'][0]['status'],'delivered','refresh persists verified report');
$changed=configured();$changed['api_key']='ava_live_'.str_repeat('b',24);$GLOBALS['options'][Settings::OPTION]=$changed;$before=calls();try{Admin::refresh();}catch(RuntimeException$error){same($error->getMessage(),'synthetic_redirect','changed-connection refresh redirects safely');}same(calls(),$before,'changed connection never reads original message identifiers');$GLOBALS['options'][Settings::OPTION]=configured();
$_POST['feed_id']=999999;$before=calls();try{Admin::refresh();}catch(RuntimeException$error){same($error->getMessage(),'synthetic_redirect','missing feed refresh redirects safely');}same(calls(),$before,'unrelated feed ID never requests API');$_POST['feed_id']=21;
$GLOBALS['gfapi_entry']=entry();$GLOBALS['gfapi_entry']['form_id']=999;$before=calls();try{Admin::refresh();}catch(RuntimeException$error){same($error->getMessage(),'synthetic_redirect','mismatched form refresh redirects safely');}same(calls(),$before,'mismatched entry form cannot read stored message');$GLOBALS['gfapi_entry']=entry();
check(strpos(Delivery::status_label(array('status'=>'delivered','requires_review'=>true,'review_status'=>'approved')),'تحویل')!==false,'terminal delivery status wins over review flag');check(strpos(Delivery::status_label(array('status'=>'pending','review_status'=>'approved','campaign_status'=>'draft')),'تأییدشده')!==false,'approved draft explicitly awaits start');
if(method_exists(Delivery::class,'claim')){$record=array('form_id'=>7,'results'=>array(array('status'=>'unknown')));same(Delivery::claim('ava_gf_delivery_990001_990001',$record),true,'insert-only database claim succeeds once');$different=array('form_id'=>8,'results'=>array());same(Delivery::claim('ava_gf_delivery_990001_990001',$different),null,'duplicate insert claim denied');same($GLOBALS['options']['ava_gf_delivery_990001_990001'],$record,'duplicate claim cannot overwrite first worker');$GLOBALS['database_failure']=true;$before=calls();$error=Delivery::process(feed(61),entry(61),form());check(is_wp_error($error),'database insertion failure blocks admission');same(calls(),$before,'database insertion failure never calls HTTP');$GLOBALS['database_failure']=false;$GLOBALS['options']['ava_gf_delivery_62_62']=$record;$GLOBALS['force_stale_option']='ava_gf_delivery_62_62';$before=calls();same(Delivery::process(feed(62),entry(62),form()),null,'stale cache cannot override duplicate database claim');same(calls(),$before,'stale cache cannot duplicate API request');$GLOBALS['force_stale_option']='';}
$cas_key='ava_gf_delivery_990002_990002';$cas_record=array('form_id'=>7,'results'=>array(array('phone'=>'0912***0001','id'=>'job-one','status'=>'pending'),array('phone'=>'0912***0002','id'=>'job-two','status'=>'pending')));Delivery::claim($cas_key,$cas_record);
$GLOBALS['cas_once_conflict']=true;same(Delivery::update_result($cas_key,1,array('status'=>'sent'),'job-two'),true,'compare-and-swap retries concurrent write conflict');same($GLOBALS['options'][$cas_key]['results'][0]['status'],'delivered','concurrent recipient report preserved');same($GLOBALS['options'][$cas_key]['results'][1]['status'],'sent','intended recipient update retained after conflict');same(Delivery::update_result($cas_key,1,array('status'=>'rejected'),'wrong-id'),false,'report cannot overwrite a different provider identifier');same($GLOBALS['options'][$cas_key]['results'][1]['status'],'sent','provider identity mismatch preserves prior result');$GLOBALS['database_failure']=true;same(Delivery::update_result($cas_key,1,array('status'=>'accepted'),'job-two'),false,'database update error returned safely');$GLOBALS['database_failure']=false;
$interleave_feed=feed(63,array('recipientMode'=>'fixed','fixedPhones'=>'09120000001,09120000002'));$iteration=0;$GLOBALS['http_callback']=function()use(&$iteration){$iteration++;if($iteration===2)Delivery::update_result('ava_gf_delivery_63_63',0,array('status'=>'delivered'),'synthetic-job-1');return reply();};$before=calls();Delivery::process($interleave_feed,entry(63),form());$GLOBALS['http_callback']=null;same(calls(),$before+2,'two intended recipients dispatched once');same($GLOBALS['options']['ava_gf_delivery_63_63']['results'][0]['status'],'delivered','report during later recipient POST is never lost');same($GLOBALS['options']['ava_gf_delivery_63_63']['results'][1]['status'],'pending','later recipient persists independent moderation result');
define('AVA_GF_API_KEY','ava_live_'.str_repeat('z',24));same(Settings::get()['api_key'],AVA_GF_API_KEY,'wp-config secret override applied');same(Settings::sanitize(array('api_url'=>'https://ava.example','api_key'=>''))['api_key'],configured()['api_key'],'wp-config secret never copied to database');
echo 'PASS: '.$checks.' behavioral checks; HTTP mocked; no SMS sent.'.PHP_EOL;
