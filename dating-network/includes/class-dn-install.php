<?php
if (!defined('ABSPATH')) { exit; }

class DN_Install
{
    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'dn_';
        $queries = [
            "CREATE TABLE {$prefix}likes (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, from_user bigint(20) unsigned NOT NULL, to_user bigint(20) unsigned NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY unique_like (from_user,to_user), KEY to_user (to_user)) {$charset};",
            "CREATE TABLE {$prefix}matches (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, user_low bigint(20) unsigned NOT NULL, user_high bigint(20) unsigned NOT NULL, status varchar(20) NOT NULL DEFAULT 'active', created_at datetime NOT NULL, ended_at datetime NULL, PRIMARY KEY (id), UNIQUE KEY unique_pair (user_low,user_high), KEY status (status)) {$charset};",
            "CREATE TABLE {$prefix}messages (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, match_id bigint(20) unsigned NOT NULL, sender_id bigint(20) unsigned NOT NULL, body text NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), KEY match_id (match_id), KEY sender_id (sender_id)) {$charset};",
            "CREATE TABLE {$prefix}blocks (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, blocker_id bigint(20) unsigned NOT NULL, blocked_id bigint(20) unsigned NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY unique_block (blocker_id,blocked_id), KEY blocked_id (blocked_id)) {$charset};",
            "CREATE TABLE {$prefix}reports (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, reporter_id bigint(20) unsigned NOT NULL, reported_id bigint(20) unsigned NOT NULL, reason varchar(50) NOT NULL, details text NULL, status varchar(20) NOT NULL DEFAULT 'open', created_at datetime NOT NULL, PRIMARY KEY (id), KEY reported_id (reported_id), KEY status (status)) {$charset};"
        ];
        foreach ($queries as $query) { dbDelta($query); }
        self::create_pages();
        self::setup_front_page();
        update_option('dn_version', DN_VERSION);
        if (!get_option('dn_from_name')) { update_option('dn_from_name', 'Dating Network'); }
    }

    public static function maybe_upgrade(): void
    {
        if ((string) get_option('dn_version', '') !== DN_VERSION) { self::activate(); }
    }

    private static function create_pages(): void
    {
        $pages = [
            'home' => ['Home', '[dating_network_home]'],
            'register' => ['Aanmelden', '[dating_network_register]'],
            'login' => ['Inloggen', '[dating_network_login]'],
            'verify' => ['Account bevestigen', '[dating_network_verify]'],
            'dashboard' => ['Mijn account', '[dating_network_dashboard]'],
            'profile' => ['Mijn profiel', '[dating_network_profile]'],
            'discover' => ['Ontdek singles', '[dating_network_discover]'],
            'matches' => ['Mijn matches', '[dating_network_matches]'],
            'chat' => ['Chat', '[dating_network_chat]'],
        ];
        foreach ($pages as $key => [$title, $content]) {
            $id = (int) get_option('dn_page_' . $key);
            if ($id && get_post($id)) {
                $post = get_post($id);
                if ($post && trim((string) $post->post_content) !== $content) { wp_update_post(['ID'=>$id,'post_content'=>$content]); }
                continue;
            }
            $id = wp_insert_post(['post_title'=>$title,'post_content'=>$content,'post_status'=>'publish','post_type'=>'page','comment_status'=>'closed']);
            if (!is_wp_error($id)) { update_option('dn_page_' . $key, (int) $id); }
        }
    }

    private static function setup_front_page(): void
    {
        $home=(int)get_option('dn_page_home');
        if ($home && get_post($home) && (int)get_option('page_on_front')===0) {
            update_option('show_on_front','page');
            update_option('page_on_front',$home);
        }
    }
}
