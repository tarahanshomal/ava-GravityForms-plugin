<?php
/** Real WordPress; simulated commercial Gravity Forms interfaces; all HTTP blocked. */
error_reporting(E_ALL & ~E_DEPRECATED);
if(!defined('WP_CLI')||!WP_CLI||getenv('AVA_GF_RUN_INTEGRATION')!=='1')throw new RuntimeException('Requires WP-CLI and AVA_GF_RUN_INTEGRATION=1.');
$home=wp_parse_url(home_url(),PHP_URL_HOST);$site=wp_parse_url(site_url(),PHP_URL_HOST);
if(wp_get_environment_type()!=='local'||!defined('DISABLE_WP_CRON')||!DISABLE_WP_CRON||!is_string($home)||substr($home,-8)!=='.invalid'||!is_string($site)||substr($site,-8)!=='.invalid')WP_CLI::error('Disposable .invalid WordPress local environment with cron disabled required.');
if(class_exists('GFForms')||defined('AVA_GF_API_KEY'))WP_CLI::error('Use an empty fixture without commercial Gravity Forms or a real API key constant.');
$plugin='ava-GravityForms-plugin/ava-gravityforms-plugin.php';
if(!is_file(WP_PLUGIN_DIR.'/'.$plugin))WP_CLI::error('Install the plugin into the disposable fixture first.');
$original=get_option('ava_gf_settings',false);
if(is_array($original)&&(!empty($original['api_key'])||($original['enabled']??'no')==='yes'))WP_CLI::error('Refusing configured integration.');
require_once ABSPATH.'wp-admin/includes/admin.php';
if (true) {
class GFAPI {public static function get_entry($id){return $GLOBALS['ava_gf_smoke_entry'];}}
class GFForms {public static $version='2.9.0';public static function include_feed_addon_framework(){}}
class GFAddOn {public static $registered=array();public static function register($name){self::$registered[]=$name;}}
class GFFeedAddOn extends GFAddOn {
    public function add_note($entry_id,$message,$type='note'){$GLOBALS['ava_gf_smoke_notes'][]=$message;}
    public function add_feed_error($message,$feed,$entry,$form){$GLOBALS['ava_gf_smoke_notes'][]=$message;}
    public function get_field_value($form,$entry,$field_id){return $entry[(string)$field_id]??'';}
}
class GFCommon {public static function replace_variables($text,$form,$entry,$url_encode=false,$esc_html=false,$nl2br=true,$format='html',$aux=false){return strtr($text,array('{Name:1}'=>$entry['1']??'','{form_title}'=>$form['title']??'','{entry_id}'=>(string)($entry['id']??'')));}}
function gform_get_meta($id,$key){return get_option('ava_gf_smoke_meta_'.$id.'_'.$key,null);}
function gform_update_meta($id,$key,$value,$form_id=null){return update_option('ava_gf_smoke_meta_'.$id.'_'.$key,$value,false);}
function rgar($array,$key,$default=null){return is_array($array)&&array_key_exists($key,$array)?$array[$key]:$default;}
function rgars($array,$keys,$default=null){foreach(explode('/',$keys)as$key){if(!is_array($array)||!array_key_exists($key,$array))return $default;$array=$array[$key];}return$array;}
}
$checks=0;$labels=array();$requests=array();$mode='blocked';$failure=null;$users=array();$original_request=$_REQUEST;$original_post=$_POST;$original_user=get_current_user_id();$active=is_plugin_active($plugin);$screen=$GLOBALS['current_screen']??null;
$entry_id=random_int(700000000,799999999);$feed_id=91;$guards=array();$GLOBALS['ava_gf_smoke_notes']=array();
$check=static function($ok,$label)use(&$checks,&$labels){if(!$ok)throw new RuntimeException('FAIL: '.$label);++$checks;$labels[]='PASS: '.$label;};
$mock=static function($pre,$args,$url)use(&$requests,&$mode){
    if($mode==='blocked'||strpos($url,'https://ava-fixture.invalid/api/v1/sms')!==0)return new WP_Error('ava_integration_offline','All external HTTP disabled.');
    $requests[]=array('url'=>$url,'args'=>$args);
    if($mode==='timeout')return new WP_Error('http_request_failed','synthetic timeout');
    if($mode==='reject')return array('headers'=>array(),'body'=>'{"error":"secret-raw-body"}','response'=>array('code'=>403,'message'=>'Forbidden'),'cookies'=>array());
    $id=($args['method']??'GET')==='GET'?basename(wp_parse_url($url,PHP_URL_PATH)):'synthetic-job-'.count($requests);
    return array('headers'=>array(),'body'=>wp_json_encode(array('id'=>$id,'status'=>$mode==='delivered'?'delivered':'pending','requires_review'=>true,'review_status'=>'pending','campaign_status'=>'draft')),'response'=>array('code'=>202,'message'=>'Accepted'),'cookies'=>array());
};
add_filter('pre_http_request',$mock,PHP_INT_MAX,3);add_filter('pre_wp_mail','__return_true',PHP_INT_MAX);
try{
$check(is_wp_error(wp_remote_get('https://external.invalid/')),'network preemption active');
$check(wp_mail('nobody@example.invalid','synthetic','no delivery')===true,'mail preempted');
deactivate_plugins($plugin);delete_option('ava_gf_settings');$result=activate_plugin($plugin);$check(!is_wp_error($result),'plugin activates in real WordPress');$check(is_plugin_active($plugin),'plugin active option recorded');
$check(\AvaGF\Settings::get()['enabled']==='no','integration defaults disabled');
do_action('gform_loaded');$check(in_array('AvaGF\\AddOn',GFAddOn::$registered,true),'gform_loaded registers addon via simulated GF contract');
$admin=wp_insert_user(array('user_login'=>'ava_gf_smoke_'.$entry_id,'user_pass'=>wp_generate_password(32),'user_email'=>'ava-gf-'.$entry_id.'@example.invalid','role'=>'administrator'));if(is_wp_error($admin))throw new RuntimeException('Synthetic admin creation failed.');$users[]=$admin;wp_set_current_user($admin);set_current_screen('settings_page_ava-gravityforms');
\AvaGF\Admin::register();$check(isset($GLOBALS['wp_registered_settings']['ava_gf_settings']),'native settings registration');$check($GLOBALS['wp_registered_settings']['ava_gf_settings']['show_in_rest']===false,'credentials excluded from REST');
$fixture=array_merge(\AvaGF\Settings::defaults(),array('enabled'=>'yes','api_url'=>'https://ava-fixture.invalid','api_key'=>'ava_live_'.str_repeat('x',32)));update_option('ava_gf_settings',$fixture);$check(\AvaGF\Settings::get()['api_key']===$fixture['api_key'],'real database persists connection');
$blank=$fixture;$blank['api_key']='';update_option('ava_gf_settings',$blank);$check(\AvaGF\Settings::get()['api_key']===$fixture['api_key'],'blank key preserves saved credential through WP sanitizer');
$bad=$blank;$bad['api_url']='http://127.0.0.1';update_option('ava_gf_settings',$bad);$check(\AvaGF\Settings::get()['api_url']===$fixture['api_url'],'unsafe endpoint cannot replace valid origin');
ob_start();\AvaGF\Admin::page();$html=ob_get_clean();$check(strpos($html,$fixture['api_key'])===false,'connection form hides saved key');$check(strpos($html,'name="_wpnonce"')!==false,'WordPress settings nonce present');$check(strpos($html,'options.php')!==false,'settings use WordPress permission handler');
$mode='success';$feed=array('id'=>$feed_id,'form_id'=>71,'is_active'=>true,'meta'=>array('feedName'=>'Synthetic','recipientMode'=>'field','phoneField'=>'2','fixedPhones'=>'','message'=>'سلام {Name:1}'));$entry=array('id'=>$entry_id,'form_id'=>71,'status'=>'active','1'=>'نمونه','2'=>'۰۹۱۲۰۰۰۰۰۰۱');$form=array('id'=>71,'title'=>'فرم نمونه','fields'=>array((object)array('id'=>2,'type'=>'phone')));
$guards[]='ava_gf_delivery_'.$entry_id.'_'.$feed_id;
$addon=\AvaGF\AddOn::get_instance();$fields=wp_json_encode($addon->feed_settings_fields());$check(strpos($fields,'feed_condition')!==false&&strpos($fields,'phoneField')!==false,'native GF field mapping and condition contract');
$result=$addon->process_feed($feed,$entry,$form);$check(!is_wp_error($result)&&count($requests)===1,'feed callback produces exactly one intercepted HTTP call');$args=$requests[0]['args'];$payload=json_decode($args['body'],true);$check($payload===array('phone'=>'09120000001','text'=>'سلام نمونه'),'normalized field and rendered merge-tag JSON');
$check($args['sslverify']===true&&$args['redirection']===0&&$args['reject_unsafe_urls']===true,'WordPress HTTP transport safety parameters');$check($args['headers']['Authorization']==='Bearer '.$fixture['api_key']&&preg_match('/^[A-Za-z0-9_-]{16,100}$/D',$args['headers']['Idempotency-Key']),'Bearer authentication and valid idempotency key');
$check(get_option($guards[0],false)!==false,'atomic deduplication guard persisted to real database');$addon->process_feed($feed,$entry,$form);$check(count($requests)===1,'repeated feed callback deduplicated in real database');$notes=wp_json_encode($GLOBALS['ava_gf_smoke_notes']);$check(strpos($notes,'09120000001')===false&&strpos($notes,$fixture['api_key'])===false,'entry notes hide full phone and secret');
$first_record=get_option($guards[0]);$check(\AvaGF\Delivery::claim($guards[0],array('form_id'=>999,'results'=>array()))===null,'insert-only SQL duplicate claim denied by real database');$check(get_option($guards[0])===$first_record,'duplicate claim cannot overwrite stored ledger');
ob_start();\AvaGF\Admin::entry($form,$entry);$sidebar=ob_get_clean();$check(strpos($sidebar,'<form')===false&&strpos($sidebar,'form="ava-gf-refresh-')!==false,'entry sidebar uses external form association');ob_start();do_action('admin_footer');$footer=ob_get_clean();$check(strpos($footer,'name="_wpnonce"')!==false&&strpos($footer,'name="feed_id"')!==false,'footer form includes nonce and scoped feed ID');
$die_filter=static function(){return static function($message,$title='',$args=array()){throw new RuntimeException('synthetic_wp_die');};};add_filter('wp_die_handler',$die_filter,PHP_INT_MAX);add_filter('wp_die_ajax_handler',$die_filter,PHP_INT_MAX);add_filter('wp_die_json_handler',$die_filter,PHP_INT_MAX);
$redirect_filter=static function($url){throw new RuntimeException('synthetic_wp_redirect');};add_filter('wp_redirect',$redirect_filter,-999999);
$GLOBALS['ava_gf_smoke_entry']=$entry;$_POST=array('entry_id'=>$entry_id,'feed_id'=>$feed_id);$_REQUEST=$_POST;
wp_set_current_user(0);$count=count($requests);try{\AvaGF\Admin::refresh();$check(false,'report refresh capability enforced');}catch(RuntimeException$error){$check($error->getMessage()==='synthetic_wp_die','report refresh capability enforced');}$check(count($requests)===$count,'unauthorized report refresh no HTTP');wp_set_current_user($admin);
$_REQUEST['_wpnonce']='invalid-nonce';try{\AvaGF\Admin::refresh();$check(false,'report refresh real nonce enforced');}catch(RuntimeException$error){$check($error->getMessage()==='synthetic_wp_die','report refresh real nonce enforced');}$check(count($requests)===$count,'invalid nonce no report request');
$_REQUEST['_wpnonce']=wp_create_nonce('ava_gf_refresh_'.$entry_id.'_'.$feed_id);$mode='delivered';try{\AvaGF\Admin::refresh();$check(false,'report refresh redirects to original entry');}catch(RuntimeException$error){$check($error->getMessage()==='synthetic_wp_redirect','report refresh redirects to original entry');}$check(count($requests)===$count+1&&end($requests)['args']['method']==='GET','authorized report refresh only reads one feed');$check(get_option($guards[0])['results'][0]['status']==='delivered','authorized report refresh persists provider status');
remove_filter('wp_redirect',$redirect_filter,-999999);remove_filter('wp_die_handler',$die_filter,PHP_INT_MAX);remove_filter('wp_die_ajax_handler',$die_filter,PHP_INT_MAX);remove_filter('wp_die_json_handler',$die_filter,PHP_INT_MAX);$_POST=$original_post;$_REQUEST=$original_request;
$entry['id']++;$guards[]='ava_gf_delivery_'.$entry['id'].'_'.$feed_id;$mode='timeout';$unknown=\AvaGF\Delivery::process($feed,$entry,$form);$check(is_array($unknown)&&$unknown[0]['status']==='unknown','transport ambiguity recorded as unknown');$count=count($requests);\AvaGF\Delivery::process($feed,$entry,$form);$check(count($requests)===$count,'uncertain request never repeated');
$mode='delivered';$report=\AvaGF\Api::report('synthetic-job-1');$check(!is_wp_error($report)&&$report['status']==='delivered','report reads provider status without sending');$check(end($requests)['args']['method']==='GET','report uses read-only GET');
$entry['id']++;$entry['status']='spam';$count=count($requests);$check(\AvaGF\Delivery::process($feed,$entry,$form)===null&&count($requests)===$count,'spam never reaches API');
$entry['status']='active';$feed['meta']['recipientMode']='fixed';$feed['meta']['fixedPhones']='09120000001,bad';$check(is_wp_error(\AvaGF\Delivery::process($feed,$entry,$form))&&count($requests)===$count,'bad fixed list rejected without partial send');
wp_set_current_user(0);ob_start();\AvaGF\Admin::page();$html=ob_get_clean();$check($html==='','unauthorized connection page denied');wp_set_current_user($admin);
$deleted=$fixture;$deleted['delete_api_key']='yes';update_option('ava_gf_settings',$deleted);$check(\AvaGF\Settings::get()['api_key']==='','explicit credential removal');
$check(get_option('blogname')!==false,'unrelated WordPress state preserved');
}catch(Throwable$error){$failure=$error;}finally{
$_POST=$original_post;$_REQUEST=$original_request;wp_set_current_user($original_user);$GLOBALS['current_screen']=$screen;foreach($users as$user)wp_delete_user($user);foreach($guards as$guard)delete_option($guard);remove_filter('sanitize_option_ava_gf_settings',array('AvaGF\\Settings','sanitize'));if($original===false)delete_option('ava_gf_settings');else update_option('ava_gf_settings',$original);if($active)activate_plugin($plugin,'',false,true);else deactivate_plugins($plugin,true);
}
if($failure)WP_CLI::error($failure->getMessage());echo implode(PHP_EOL,$labels).PHP_EOL;echo 'RESULT: '.$checks.' real WordPress checks; '.count($requests).' intercepted HTTP requests; simulated GF contracts; no real SMS or mail.'.PHP_EOL;
