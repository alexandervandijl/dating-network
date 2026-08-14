<?php
if (!defined('ABSPATH')) { exit; }

class DN_Reputation
{
    private const SCHEMA = '1';

    public static function init(): void
    {
        self::maybe_install();
        add_action('init', [self::class, 'handle_frontend_report'], 5);
        add_filter('do_shortcode_tag', [self::class, 'extend_shortcode'], 35, 4);
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_dn_reputation_action', [self::class, 'handle_admin_action']);
    }

    private static function maybe_install(): void
    {
        if ((string)get_option('dn_reputation_schema', '') === self::SCHEMA) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'dn_reputation_events';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            polarity varchar(12) NOT NULL DEFAULT 'neutral',
            category varchar(60) NOT NULL,
            note text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY polarity (polarity),
            KEY created_at (created_at)
        ) {$charset};");
        update_option('dn_reputation_schema', self::SCHEMA);
    }

    public static function handle_frontend_report(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_user_logged_in()) { return; }
        if (sanitize_key(wp_unslash($_POST['dn_action'] ?? '')) !== 'report') { return; }
        if (!DN_Core::verify_nonce('report')) { return; }

        $reporter = get_current_user_id();
        $reported = max(0, (int)($_POST['user'] ?? 0));
        if (!$reported || $reported === $reporter || !get_userdata($reported)) { return; }

        $allowed = ['not_single','promotion','harassment','pressure','sexual','abuse','scam','fake','discrimination','safety','other'];
        $reason = sanitize_key(wp_unslash($_POST['reason'] ?? 'other'));
        if (!in_array($reason, $allowed, true)) { $reason = 'other'; }
        $details = sanitize_textarea_field(wp_unslash($_POST['details'] ?? ''));
        $match_id = max(0, (int)($_POST['match_id'] ?? 0));
        if ($match_id) { $details = 'Chat #' . $match_id . ($details !== '' ? ' — ' . $details : ''); }

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'dn_reports', [
            'reporter_id' => $reporter,
            'reported_id' => $reported,
            'reason' => $reason,
            'details' => $details,
            'status' => 'open',
            'created_at' => current_time('mysql'),
        ], ['%d','%d','%s','%s','%s','%s']);

        if (class_exists('DN_Reputation')) {
            self::add_event($reported, 'negative', 'user_report_' . $reason, 'Melding door gebruiker ID ' . $reporter . ($details !== '' ? ': ' . $details : ''), $reporter);
        }

        DN_Core::go('matches', ['dn_msg' => 'reported']);
    }

    public static function extend_shortcode(string $output, string $tag, array $attr, array $match): string
    {
        if ($tag === 'dating_network_chat' && is_user_logged_in()) {
            $output = self::enhance_chat_report_form($output);
        }
        if ($tag === 'dating_network_dashboard' && is_user_logged_in()) {
            $positive = self::counts(get_current_user_id())['positive'];
            if ($positive > 0) {
                $card = '<div class="dn-wrap"><div class="dn-card" style="border-left:4px solid #2f8f57"><strong>✅ Positieve waardering</strong><p>Je hebt ' . (int)$positive . ' positieve waardering' . ($positive === 1 ? '' : 'en') . ' van Dating Network gekregen voor goed gedrag. Dit is privé en geeft geen betaalde of kunstmatige voorrang in matching.</p></div></div>';
                $output = $card . $output;
            }
        }
        return $output;
    }

    private static function enhance_chat_report_form(string $output): string
    {
        $old = '<select name="reason"><option value="not_single">Deze persoon is niet single</option><option value="promotion">Promotie / externe links</option><option value="harassment">Ongewenst gedrag</option><option value="fake">Nep of misleidend profiel</option><option value="other">Anders</option></select><button class="dn-button dn-button-ghost">Rapporteren</button>';
        if (!str_contains($output, $old)) { return $output; }
        $match_id = max(0, (int)($_GET['match'] ?? 0));
        $new = '<select name="reason"><option value="not_single">Deze persoon is niet single</option><option value="harassment">Lastigvallen / intimidatie</option><option value="pressure">Druk zetten / grenzen niet respecteren</option><option value="sexual">Ongewenst seksueel gedrag</option><option value="abuse">Belediging / agressief gedrag</option><option value="promotion">Promotie of extern contact proberen te delen</option><option value="scam">Oplichting / geld / investering</option><option value="fake">Nep of misleidend profiel</option><option value="discrimination">Discriminatie / haatdragend gedrag</option><option value="safety">Ander veiligheidsrisico</option><option value="other">Anders</option></select><textarea name="details" rows="3" maxlength="1000" placeholder="Vertel kort wat er is gebeurd. Dit helpt de beheerder bij beoordeling."></textarea><input type="hidden" name="match_id" value="' . (int)$match_id . '"><button class="dn-button dn-button-ghost">Rapporteren</button>';
        $output = str_replace($old, $new, $output);
        $output = str_replace("<button class=\"dn-link-danger\" onclick=\"return confirm('Deze gebruiker blokkeren?')\">Blokkeren</button>", "<button class=\"dn-link-danger\" onclick=\"return confirm('Deze gebruiker voor jou blokkeren? De chat stopt direct en jullie worden niet meer aan elkaar getoond.')\">Blokkeren voor mij</button>", $output);
        return $output;
    }

    public static function menu(): void
    {
        add_submenu_page('dating-network', 'Gebruikers & vertrouwen', 'Gebruikers & vertrouwen', 'manage_options', 'dating-network-trust', [self::class, 'page']);
    }

    public static function page(): void
    {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $selected = max(0, (int)($_GET['user'] ?? 0));
        if ($selected) { self::user_page($selected); return; }

        $users = get_users(['number'=>250, 'orderby'=>'registered', 'order'=>'DESC']);
        ?>
        <div class="wrap">
            <h1>Dating Network · Gebruikers & vertrouwen</h1>
            <p>Hier leg je zowel goed als problematisch gedrag vast. Deze signalen zijn intern, niet publiek en beïnvloeden matching niet automatisch.</p>
            <table class="widefat striped"><thead><tr><th>Gebruiker</th><th>Profiel</th><th>✅ Positief</th><th>⚠️ Negatief</th><th>Open meldingen</th><th>Beheerstatus</th><th></th></tr></thead><tbody>
            <?php foreach ($users as $user):
                $uid=(int)$user->ID;
                $counts=self::counts($uid);
                $open=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dn_reports WHERE reported_id=%d AND status='open'", $uid));
                $moderation=(string)get_user_meta($uid,'dn_admin_moderation_status',true) ?: 'normaal';
            ?>
                <tr><td><strong><?php echo esc_html($user->display_name ?: $user->user_login); ?></strong><br><small><?php echo esc_html($user->user_email); ?> · ID <?php echo $uid; ?></small></td><td><?php echo esc_html((string)get_user_meta($uid,'dn_profile_status',true) ?: '—'); ?></td><td><strong style="color:#137333">+<?php echo (int)$counts['positive']; ?></strong></td><td><strong style="color:#b42318">-<?php echo (int)$counts['negative']; ?></strong></td><td><?php echo $open; ?></td><td><?php echo esc_html($moderation); ?></td><td><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dating-network-trust&user='.$uid)); ?>">Open dossier</a></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    private static function user_page(int $user_id): void
    {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $user=get_userdata($user_id);
        if (!$user) { echo '<div class="wrap"><h1>Gebruiker niet gevonden</h1></div>'; return; }
        $events=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dn_reputation_events WHERE user_id=%d ORDER BY created_at DESC,id DESC LIMIT 200", $user_id));
        $reports=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dn_reports WHERE reported_id=%d ORDER BY created_at DESC LIMIT 100", $user_id));
        $counts=self::counts($user_id);
        $moderation=(string)get_user_meta($user_id,'dn_admin_moderation_status',true) ?: 'normaal';
        ?>
        <div class="wrap">
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=dating-network-trust')); ?>">← Alle gebruikers</a></p>
            <h1><?php echo esc_html($user->display_name ?: $user->user_login); ?> · vertrouwensdossier</h1>
            <p><strong><?php echo esc_html($user->user_email); ?></strong> · ID <?php echo $user_id; ?> · profiel: <?php echo esc_html((string)get_user_meta($user_id,'dn_profile_status',true)); ?> · beheerstatus: <strong><?php echo esc_html($moderation); ?></strong></p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:800px;margin:20px 0"><div class="card"><h2 style="color:#137333">+<?php echo (int)$counts['positive']; ?></h2><p>Positieve waarderingen</p></div><div class="card"><h2 style="color:#b42318">-<?php echo (int)$counts['negative']; ?></h2><p>Negatieve signalen</p></div><div class="card"><h2><?php echo count($reports); ?></h2><p>Gebruikersmeldingen totaal</p></div></div>
            <?php self::action_forms($user_id); ?>
            <h2>Gebruikersmeldingen</h2>
            <?php if (!$reports): ?><p>Geen meldingen over deze gebruiker.</p><?php else: ?><table class="widefat striped"><thead><tr><th>Datum</th><th>Melder</th><th>Reden</th><th>Toelichting</th><th>Status</th></tr></thead><tbody><?php foreach($reports as $report): $reporter=get_userdata((int)$report->reporter_id); ?><tr><td><?php echo esc_html((string)$report->created_at); ?></td><td><?php echo esc_html($reporter ? $reporter->display_name : 'ID '.(int)$report->reporter_id); ?></td><td><?php echo esc_html((string)$report->reason); ?></td><td><?php echo esc_html((string)$report->details); ?></td><td><?php echo esc_html((string)$report->status); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
            <h2>Beheersignalen en waarderingen</h2>
            <?php if (!$events): ?><p>Nog geen beheersignalen.</p><?php else: ?><table class="widefat striped"><thead><tr><th>Datum</th><th>Type</th><th>Categorie</th><th>Notitie</th><th>Door</th></tr></thead><tbody><?php foreach($events as $event): $actor=get_userdata((int)$event->actor_user_id); ?><tr><td><?php echo esc_html((string)$event->created_at); ?></td><td><?php echo $event->polarity==='positive'?'✅ Positief':($event->polarity==='negative'?'⚠️ Negatief':'ℹ️ Actie'); ?></td><td><?php echo esc_html((string)$event->category); ?></td><td><?php echo esc_html((string)$event->note); ?></td><td><?php echo esc_html($actor?$actor->display_name:'Systeem'); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        </div>
        <?php
    }

    private static function action_forms(int $user_id): void
    {
        $url=admin_url('admin-post.php'); ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;max-width:1100px;margin:24px 0">
            <form class="card" method="post" action="<?php echo esc_url($url); ?>"><h2>✅ Positief waarderen</h2><?php wp_nonce_field('dn_reputation_'.$user_id); ?><input type="hidden" name="action" value="dn_reputation_action"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="dn_rep_action" value="positive"><p><select name="category" required><option value="respectful">Respectvol</option><option value="reliable">Betrouwbaar</option><option value="good_communication">Goede communicatie</option><option value="kept_agreement">Afspraak nagekomen</option><option value="helpful">Behulpzaam / constructief</option><option value="other_positive">Anders positief</option></select></p><p><textarea class="large-text" rows="3" name="note" placeholder="Waarom verdient deze gebruiker een positieve waardering?"></textarea></p><button class="button button-primary">Positieve waardering toevoegen</button></form>
            <form class="card" method="post" action="<?php echo esc_url($url); ?>"><h2>⚠️ Negatief signaal</h2><?php wp_nonce_field('dn_reputation_'.$user_id); ?><input type="hidden" name="action" value="dn_reputation_action"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="dn_rep_action" value="negative"><p><select name="category" required><option value="rude">Onbeleefd / respectloos</option><option value="pressure">Druk zetten / grensoverschrijdend</option><option value="dishonest">Oneerlijk / misleidend</option><option value="not_single">Niet single</option><option value="promotion">Promotie / extern contact</option><option value="harassment">Intimidatie / lastigvallen</option><option value="fake">Nep of misleidend profiel</option><option value="safety">Veiligheidsrisico</option><option value="other_negative">Anders negatief</option></select></p><p><textarea class="large-text" rows="3" name="note" placeholder="Leg vast wat er is gebeurd."></textarea></p><button class="button">Negatief signaal toevoegen</button></form>
            <form class="card" method="post" action="<?php echo esc_url($url); ?>"><h2>🛡️ Beheeractie</h2><?php wp_nonce_field('dn_reputation_'.$user_id); ?><input type="hidden" name="action" value="dn_reputation_action"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><p><select name="dn_rep_action" required><option value="warn">Waarschuwing registreren</option><option value="pause">Profiel pauzeren</option><option value="ban">Volledig blokkeren</option><option value="restore">Beheerblokkade opheffen</option></select></p><p><textarea class="large-text" rows="3" name="note" placeholder="Interne reden/notitie"></textarea></p><button class="button button-secondary" onclick="return confirm('Deze beheeractie uitvoeren?')">Actie uitvoeren</button></form>
        </div><?php
    }

    public static function handle_admin_action(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Geen toegang.'); }
        $user_id=max(0,(int)($_POST['user_id']??0));
        if (!$user_id || !get_userdata($user_id)) { wp_die('Gebruiker niet gevonden.'); }
        check_admin_referer('dn_reputation_'.$user_id);
        $action=sanitize_key(wp_unslash($_POST['dn_rep_action']??''));
        $category=sanitize_key(wp_unslash($_POST['category']??''));
        $note=sanitize_textarea_field(wp_unslash($_POST['note']??''));

        if ($action==='positive') {
            self::add_event($user_id,'positive',$category?:'positive',$note);
        } elseif ($action==='negative') {
            self::add_event($user_id,'negative',$category?:'negative',$note);
        } elseif ($action==='warn') {
            self::add_event($user_id,'neutral','warning',$note);
            update_user_meta($user_id,'dn_admin_warning_at',current_time('mysql'));
        } elseif ($action==='pause') {
            self::add_event($user_id,'negative','admin_pause',$note);
            update_user_meta($user_id,'dn_admin_moderation_status','paused');
            update_user_meta($user_id,'dn_profile_status','paused');
            self::close_matches($user_id,'ended');
        } elseif ($action==='ban') {
            self::add_event($user_id,'negative','admin_ban',$note);
            update_user_meta($user_id,'dn_admin_moderation_status','blocked');
            update_user_meta($user_id,'dn_profile_status','blocked');
            self::close_matches($user_id,'blocked');
        } elseif ($action==='restore') {
            self::add_event($user_id,'neutral','admin_restore',$note);
            delete_user_meta($user_id,'dn_admin_moderation_status');
            self::restore_profile_status($user_id);
        }
        wp_safe_redirect(admin_url('admin.php?page=dating-network-trust&user='.$user_id.'&updated=1'));
        exit;
    }

    public static function add_event(int $user_id, string $polarity, string $category, string $note='', ?int $actor=null): void
    {
        global $wpdb;
        if (!in_array($polarity,['positive','negative','neutral'],true)) { $polarity='neutral'; }
        $wpdb->insert($wpdb->prefix.'dn_reputation_events',[
            'user_id'=>$user_id,
            'actor_user_id'=>$actor ?? get_current_user_id(),
            'polarity'=>$polarity,
            'category'=>substr($category,0,60),
            'note'=>$note,
            'created_at'=>current_time('mysql'),
        ],['%d','%d','%s','%s','%s','%s']);
    }

    private static function counts(int $user_id): array
    {
        global $wpdb;
        $table=$wpdb->prefix.'dn_reputation_events';
        return [
            'positive'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id=%d AND polarity='positive'",$user_id)),
            'negative'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id=%d AND polarity='negative'",$user_id)),
        ];
    }

    private static function close_matches(int $user_id, string $status): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}dn_matches SET status=%s,ended_at=%s WHERE status='active' AND (user_low=%d OR user_high=%d)",$status,current_time('mysql'),$user_id,$user_id));
    }

    private static function restore_profile_status(int $user_id): void
    {
        $verified=get_user_meta($user_id,'dn_email_verified',true)==='1';
        $single=get_user_meta($user_id,'dn_single_confirmed',true)==='1'||get_user_meta($user_id,'dn_is_single',true)==='1';
        $consent=get_user_meta($user_id,'dn_consent',true)!=='0';
        $complete=true;
        foreach(['dn_gender','dn_dob','dn_city','dn_country','dn_relationship_goal','dn_age_min','dn_age_max'] as $key){if(trim((string)get_user_meta($user_id,$key,true))===''){$complete=false;break;}}
        update_user_meta($user_id,'dn_profile_status',($verified&&$single&&$consent&&$complete&&(DN_Match::age($user_id)??0)>=18)?'active':'incomplete');
    }
}
