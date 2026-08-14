<?php
if (!defined('ABSPATH')) { exit; }

class DN_Updater
{
    private const META_URL='https://raw.githubusercontent.com/alexandervandijl/dating-network/main/update.json';
    private const CACHE_KEY='dn_update_meta_v2';

    public static function init(): void
    {
        add_filter('site_transient_update_plugins',[self::class,'check']);
        add_filter('plugins_api',[self::class,'info'],20,3);
        add_filter('upgrader_pre_download',[self::class,'verify_download'],20,4);
    }

    private static function meta(bool $fresh=false): ?array
    {
        if($fresh){delete_site_transient(self::CACHE_KEY);}else{$cached=get_site_transient(self::CACHE_KEY);if(is_array($cached)){return $cached;}}
        $response=wp_remote_get(self::META_URL,['timeout'=>10,'headers'=>['Accept'=>'application/json']]);
        if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200){return null;}
        $data=json_decode(wp_remote_retrieve_body($response),true);if(!is_array($data)||empty($data['version'])||empty($data['download_url'])){return null;}
        set_site_transient(self::CACHE_KEY,$data,300);return $data;
    }

    public static function check($transient)
    {
        if(!is_object($transient)){return $transient;}$meta=self::meta();if(!$meta){return $transient;}
        $plugin=plugin_basename(DN_FILE);
        if(version_compare(DN_VERSION,(string)$meta['version'],'<')){
            $o=new stdClass();$o->slug='dating-network';$o->plugin=$plugin;$o->new_version=(string)$meta['version'];$o->url=(string)($meta['homepage']??'https://dating.alexandervandijl.nl/');$o->package=(string)$meta['download_url'];$o->requires=(string)($meta['requires']??'6.5');$o->requires_php=(string)($meta['requires_php']??'8.0');$transient->response[$plugin]=$o;
        }else{unset($transient->response[$plugin]);}
        return $transient;
    }

    public static function info($result,string $action,object $args)
    {
        if($action!=='plugin_information'||($args->slug??'')!=='dating-network'){return $result;}$m=self::meta();if(!$m){return $result;}$o=new stdClass();$o->name=(string)($m['name']??'Dating Network');$o->slug='dating-network';$o->version=(string)$m['version'];$o->homepage=(string)($m['homepage']??'https://dating.alexandervandijl.nl/');$o->download_link=(string)$m['download_url'];$o->requires=(string)($m['requires']??'6.5');$o->requires_php=(string)($m['requires_php']??'8.0');$o->sections=(array)($m['sections']??[]);return $o;
    }

    public static function verify_download($reply,string $package,$upgrader,$hook_extra)
    {
        $m=self::meta(true);if(!$m||empty($m['checksum_sha256'])||$package!==(string)$m['download_url']){return $reply;}
        $file=download_url($package,60);if(is_wp_error($file)){return $file;}$actual=hash_file('sha256',$file);$expected=strtolower((string)$m['checksum_sha256']);if(!$actual||!hash_equals($expected,strtolower($actual))){@unlink($file);return new WP_Error('dn_checksum','Dating Network-update geweigerd: checksum komt niet overeen.');}return $file;
    }
}
