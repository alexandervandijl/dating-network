<?php
if (!defined('ABSPATH')) { exit; }

class DN_Core
{
    private const APP_KEYS=['register','login','verify','dashboard','profile','discover','matches','chat'];

    public static function init(): void
    {
        add_action('wp_enqueue_scripts',[self::class,'assets']);
        add_action('init',[self::class,'handle_post']);
        add_filter('template_include',[self::class,'template'],99);
        add_filter('body_class',[self::class,'body_class']);
    }

    public static function page_url(string $key,array $args=[]): string
    {
        $id=(int)get_option('dn_page_'.$key);$url=$id?get_permalink($id):home_url('/');
        return $args?add_query_arg($args,$url):$url;
    }

    public static function current_page_key(): string
    {
        if(!is_page()){return '';}
        $id=(int)get_queried_object_id();
        foreach(array_merge(['home'],self::APP_KEYS) as $key){if($id&&$id===(int)get_option('dn_page_'.$key)){return $key;}}
        return '';
    }

    public static function is_app_page(): bool{return in_array(self::current_page_key(),self::APP_KEYS,true);}

    public static function template(string $template): string
    {
        $key=self::current_page_key();
        if($key==='home'&&is_readable(DN_DIR.'templates/home.php')){return DN_DIR.'templates/home.php';}
        if(in_array($key,self::APP_KEYS,true)&&is_readable(DN_DIR.'templates/app.php')){return DN_DIR.'templates/app.php';}
        return $template;
    }

    public static function assets(): void
    {
        $key=self::current_page_key();if(!$key){return;}
        wp_enqueue_style('dating-network',DN_URL.'assets/dn.css',[],DN_VERSION);
        if($key==='home'){wp_enqueue_style('dating-network-home',DN_URL.'assets/home.css',['dating-network'],DN_VERSION);}
        else{wp_enqueue_style('dating-network-app',DN_URL.'assets/app.css',['dating-network'],DN_VERSION);}
        wp_enqueue_style('dating-network-branding',DN_URL.'assets/branding.css',['dating-network'],DN_VERSION);
        wp_enqueue_style('dating-network-growth',DN_URL.'assets/growth.css',['dating-network-branding'],DN_VERSION);
        wp_enqueue_script('dating-network',DN_URL.'assets/dn.js',[],DN_VERSION,true);
    }

    public static function body_class(array $classes): array
    {
        if(self::current_page_key()==='home'){$classes[]='dn-home-page';}
        if(self::is_app_page()){$classes[]='dn-app-page';$classes[]='dn-page-'.self::current_page_key();}
        return $classes;
    }

    public static function handle_post(): void
    {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||empty($_POST['dn_action'])){return;}
        DN_Shortcodes::handle_post(sanitize_key(wp_unslash($_POST['dn_action'])));
    }

    public static function nonce(string $action): string{return wp_nonce_field('dn_'.$action,'dn_nonce',true,false);}
    public static function verify_nonce(string $action): bool{return isset($_POST['dn_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dn_nonce'])),'dn_'.$action);}
    public static function go(string $page,array $args=[]): void{wp_safe_redirect(self::page_url($page,$args));exit;}
}
