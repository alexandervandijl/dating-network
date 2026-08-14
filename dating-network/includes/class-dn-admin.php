<?php
if (!defined('ABSPATH')) { exit; }

class DN_Admin
{
    public static function init(): void
    {
        add_action('admin_menu',[self::class,'menu']);
        add_action('admin_init',[self::class,'settings']);
    }

    public static function menu(): void
    {
        add_menu_page('Dating Network','Dating Network','manage_options','dating-network',[self::class,'page'],'dashicons-heart',30);
    }

    public static function settings(): void
    {
        register_setting('dn_settings','dn_from_name',['sanitize_callback'=>'sanitize_text_field']);
        register_setting('dn_settings','dn_from_email',['sanitize_callback'=>'sanitize_email']);
    }

    public static function page(): void
    {
        if(!current_user_can('manage_options')){return;}global $wpdb;
        $active=(int)count(get_users(['meta_key'=>'dn_profile_status','meta_value'=>'active','fields'=>'ID']));
        $success=(int)count(get_users(['meta_key'=>'dn_profile_status','meta_value'=>'success','fields'=>'ID']));
        $reports=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dn_reports WHERE status='open'");
        $matches=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dn_matches WHERE status='active'");
        ?>
        <div class="wrap"><h1>Dating Network</h1><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:900px;margin:20px 0"><div class="card"><h2><?php echo $active; ?></h2><p>Actieve singles</p></div><div class="card"><h2><?php echo $matches; ?></h2><p>Actieve matches</p></div><div class="card"><h2>❤️ <?php echo $success; ?></h2><p>Iemand gevonden</p></div><div class="card"><h2><?php echo $reports; ?></h2><p>Open meldingen</p></div></div>
        <form method="post" action="options.php"><?php settings_fields('dn_settings'); ?><table class="form-table"><tr><th>Afzendernaam</th><td><input class="regular-text" name="dn_from_name" value="<?php echo esc_attr((string)get_option('dn_from_name','Dating Network')); ?>"></td></tr><tr><th>Afzender e-mail</th><td><input class="regular-text" type="email" name="dn_from_email" value="<?php echo esc_attr((string)get_option('dn_from_email',get_option('admin_email'))); ?>"></td></tr></table><?php submit_button(); ?></form>
        <h2>Open meldingen</h2><?php $rows=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}dn_reports WHERE status='open' ORDER BY created_at DESC LIMIT 50"); if(!$rows): ?><p>Geen open meldingen.</p><?php else: ?><table class="widefat striped"><thead><tr><th>Datum</th><th>Melder</th><th>Gemeld</th><th>Reden</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?php echo esc_html((string)$r->created_at); ?></td><td><?php echo (int)$r->reporter_id; ?></td><td><?php echo (int)$r->reported_id; ?></td><td><?php echo esc_html((string)$r->reason); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
        <?php
    }
}
