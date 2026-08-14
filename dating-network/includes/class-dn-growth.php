<?php
if (!defined('ABSPATH')) { exit; }

class DN_Growth
{
    private const SCHEMA = '1';
    private const GOAL = 100;

    public static function init(): void
    {
        self::maybe_install();
        add_action('init', [self::class, 'capture_campaign'], 2);
        add_action('user_register', [self::class, 'attribute_registration'], 20);
        add_filter('do_shortcode_tag', [self::class, 'extend_shortcode'], 55, 4);
        add_action('admin_menu', [self::class, 'admin_menu'], 30);
    }

    private static function maybe_install(): void
    {
        if ((string)get_option('dn_growth_schema', '') === self::SCHEMA) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'dn_growth_clicks';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            referrer_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source varchar(50) NOT NULL DEFAULT '',
            visitor_hash char(64) NOT NULL,
            landing_path varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY referrer_id (referrer_id),
            KEY source (source),
            KEY visitor_hash (visitor_hash),
            KEY created_at (created_at)
        ) {$charset};");
        update_option('dn_growth_schema', self::SCHEMA);
    }

    public static function capture_campaign(): void
    {
        if (is_admin()) { return; }
        $code = sanitize_key(wp_unslash($_GET['dn_ref'] ?? ''));
        $source = sanitize_key(wp_unslash($_GET['dn_src'] ?? ''));
        $source = substr($source, 0, 50);
        $referrer_id = $code !== '' ? self::referrer_by_code($code) : 0;

        if ($code !== '' && $referrer_id > 0) {
            self::set_cookie('dn_ref_code', $code, 30 * DAY_IN_SECONDS);
        }
        if ($source !== '') {
            self::set_cookie('dn_source', $source, 30 * DAY_IN_SECONDS);
        }
        if ($referrer_id <= 0 && $source === '') { return; }

        $visitor = sanitize_key(wp_unslash($_COOKIE['dn_growth_visitor'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/', $visitor)) {
            $visitor = substr(str_replace('-', '', wp_generate_uuid4()), 0, 32);
            self::set_cookie('dn_growth_visitor', $visitor, YEAR_IN_SECONDS);
        }
        $visitor_hash = hash_hmac('sha256', $visitor, wp_salt('auth'));
        $path = (string)(wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $path = substr(sanitize_text_field($path), 0, 255);

        global $wpdb;
        $table = $wpdb->prefix . 'dn_growth_clicks';
        $cutoff = wp_date('Y-m-d H:i:s', time() - HOUR_IN_SECONDS);
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE referrer_id=%d AND source=%s AND visitor_hash=%s AND created_at>=%s LIMIT 1",
            $referrer_id, $source, $visitor_hash, $cutoff
        ));
        if (!$exists) {
            $wpdb->insert($table, [
                'referrer_id' => $referrer_id,
                'source' => $source,
                'visitor_hash' => $visitor_hash,
                'landing_path' => $path,
                'created_at' => current_time('mysql'),
            ], ['%d','%s','%s','%s','%s']);
        }
    }

    public static function attribute_registration(int $user_id): void
    {
        $code = sanitize_key(wp_unslash($_COOKIE['dn_ref_code'] ?? ''));
        $source = sanitize_key(wp_unslash($_COOKIE['dn_source'] ?? ''));
        if ($source !== '') {
            update_user_meta($user_id, 'dn_signup_source', substr($source, 0, 50));
        }
        if ($code === '') { return; }
        $referrer_id = self::referrer_by_code($code);
        if ($referrer_id > 0 && $referrer_id !== $user_id && !get_user_meta($user_id, 'dn_referred_by', true)) {
            update_user_meta($user_id, 'dn_referred_by', (string)$referrer_id);
            update_user_meta($user_id, 'dn_referred_at', current_time('mysql'));
        }
    }

    private static function set_cookie(string $name, string $value, int $ttl): void
    {
        if (headers_sent()) { return; }
        setcookie($name, $value, [
            'expires' => time() + $ttl,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $value;
    }

    private static function referrer_by_code(string $code): int
    {
        if ($code === '') { return 0; }
        $users = get_users([
            'meta_key' => 'dn_ref_code',
            'meta_value' => $code,
            'number' => 1,
            'fields' => 'ID',
        ]);
        return $users ? (int)$users[0] : 0;
    }

    private static function code_for_user(int $user_id): string
    {
        $code = sanitize_key((string)get_user_meta($user_id, 'dn_ref_code', true));
        if ($code !== '') { return $code; }
        $code = 'u' . $user_id . '-' . strtolower(wp_generate_password(7, false, false));
        $code = sanitize_key($code);
        update_user_meta($user_id, 'dn_ref_code', $code);
        return $code;
    }

    public static function extend_shortcode(string $output, string $tag, $attr, $match): string
    {
        if ($tag === 'dating_network_dashboard' && is_user_logged_in()) {
            return $output . self::member_panel(get_current_user_id());
        }
        if ($tag === 'dating_network_home') {
            $needle = '<section id="hoe-het-werkt"';
            $progress = self::homepage_progress();
            if (str_contains($output, $needle)) {
                return str_replace($needle, $progress . $needle, $output);
            }
            return $output . $progress;
        }
        return $output;
    }

    private static function member_panel(int $user_id): string
    {
        global $wpdb;
        $code = self::code_for_user($user_id);
        $link = add_query_arg(['dn_ref' => $code, 'dn_src' => 'member_invite'], DN_Core::page_url('register'));
        $table = $wpdb->prefix . 'dn_growth_clicks';
        $unique = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT visitor_hash) FROM {$table} WHERE referrer_id=%d", $user_id));
        $referred = self::referred_count($user_id, false);
        $active_referred = self::referred_count($user_id, true);
        $active = self::active_count();
        $percent = min(100, (int)round(($active / self::GOAL) * 100));
        $text = 'Dating Network is gratis en blijft gratis. Word jij een van de eerste 100 singles? ' . $link;
        $wa = 'https://wa.me/?text=' . rawurlencode($text);
        $fb = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($link);

        ob_start(); ?>
        <div class="dn-wrap dn-growth-wrap">
            <section class="dn-card dn-growth-card">
                <div class="dn-growth-head"><div><span class="dn-page-kicker">HELP DE EERSTE 100 BOUWEN</span><h2>Nodig een single uit.</h2><p>Dating Network groeit alleen met echte mensen. Jouw persoonlijke link laat zien hoeveel mensen jij helpt binnenbrengen.</p></div><div class="dn-growth-goal"><strong><?php echo (int)$active; ?>/100</strong><span>match-klare profielen</span></div></div>
                <div class="dn-growth-progress"><i style="width:<?php echo (int)$percent; ?>%"></i></div>
                <div class="dn-growth-stats"><div><strong><?php echo $unique; ?></strong><span>unieke bezoekers</span></div><div><strong><?php echo $referred; ?></strong><span>aanmeldingen</span></div><div><strong><?php echo $active_referred; ?></strong><span>match-klaar</span></div></div>
                <label class="dn-growth-link">Jouw persoonlijke uitnodigingslink<input readonly value="<?php echo esc_attr($link); ?>" onclick="this.select()"></label>
                <div class="dn-form-actions"><button type="button" class="dn-button" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?php echo esc_js($link); ?>');this.textContent='✓ Gekopieerd'">Link kopiëren</button><a class="dn-button dn-button-ghost" target="_blank" rel="noopener" href="<?php echo esc_url($wa); ?>">WhatsApp</a><a class="dn-button dn-button-ghost" target="_blank" rel="noopener" href="<?php echo esc_url($fb); ?>">Facebook</a></div>
                <p class="dn-muted dn-growth-note">Klikken zijn indicatief en worden per bezoeker/referral maximaal één keer per uur geteld. Er worden hiervoor geen ruwe IP-adressen opgeslagen.</p>
            </section>
        </div>
        <?php return (string)ob_get_clean();
    }

    private static function homepage_progress(): string
    {
        $active = self::active_count();
        $percent = min(100, (int)round(($active / self::GOAL) * 100));
        $register = add_query_arg('dn_src', 'first100_home', DN_Core::page_url('register'));
        ob_start(); ?>
        <section class="dn-first100"><div><span>EERSTE 100</span><h2>Bouw mee aan de eerste 100 echte profielen.</h2><p>We tellen alleen profielen die compleet en actief genoeg zijn om daadwerkelijk mee te doen aan matching.</p><a class="dn-home-button" href="<?php echo esc_url($register); ?>">Word een van de eerste 100 →</a></div><div class="dn-first100-meter"><strong><?php echo (int)$active; ?><small>/100</small></strong><div><i style="width:<?php echo (int)$percent; ?>%"></i></div><span><?php echo max(0, self::GOAL - $active); ?> te gaan</span></div></section>
        <?php return (string)ob_get_clean();
    }

    private static function active_count(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key=%s AND meta_value=%s",
            'dn_profile_status', 'active'
        ));
    }

    private static function referred_count(int $referrer_id, bool $active_only): int
    {
        global $wpdb;
        $joins = " INNER JOIN {$wpdb->usermeta} c ON c.user_id=r.user_id AND c.meta_key='dn_consent'";
        if ($active_only) {
            $joins .= " INNER JOIN {$wpdb->usermeta} s ON s.user_id=r.user_id AND s.meta_key='dn_profile_status' AND s.meta_value='active'";
        }
        $sql = "SELECT COUNT(DISTINCT r.user_id) FROM {$wpdb->usermeta} r{$joins} WHERE r.meta_key='dn_referred_by' AND r.meta_value=%s";
        return (int)$wpdb->get_var($wpdb->prepare($sql, (string)$referrer_id));
    }

    public static function admin_menu(): void
    {
        add_submenu_page('dating-network', 'Groei & statistieken', 'Groei & statistieken', 'manage_options', 'dating-network-growth', [self::class, 'admin_page']);
    }

    public static function admin_page(): void
    {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $click_table = $wpdb->prefix . 'dn_growth_clicks';
        $stats = [
            'Accounts' => self::meta_users('dn_consent'),
            'E-mail bevestigd' => self::meta_users('dn_email_verified', '1'),
            'Match-klaar' => self::meta_users('dn_profile_status', 'active'),
            'Foto goedgekeurd' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_dn_photo_status' AND meta_value='approved'"),
            'Interesses verstuurd' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dn_likes"),
            'Matches totaal' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dn_matches"),
            'Chatberichten' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dn_messages"),
            'Iemand gevonden' => self::meta_users('dn_profile_status', 'success'),
        ];
        $clicks = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$click_table}");
        $unique = (int)$wpdb->get_var("SELECT COUNT(DISTINCT visitor_hash) FROM {$click_table}");
        $referred = self::meta_users('dn_referred_by');
        $today = current_time('Y-m-d') . ' 00:00:00';
        $registered_today = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} m ON m.user_id=u.ID AND m.meta_key='dn_consent' WHERE u.user_registered >= %s",
            $today
        ));
        $sources = $wpdb->get_results("SELECT meta_value source,COUNT(*) registrations FROM {$wpdb->usermeta} WHERE meta_key='dn_signup_source' AND meta_value<>'' GROUP BY meta_value ORDER BY registrations DESC LIMIT 20");
        $leaders = $wpdb->get_results("SELECT meta_value referrer_id,COUNT(*) registrations FROM {$wpdb->usermeta} WHERE meta_key='dn_referred_by' AND meta_value<>'' GROUP BY meta_value ORDER BY registrations DESC LIMIT 20");
        $knip_link = add_query_arg('dn_src', 'knipmodel', DN_Core::page_url('register'));
        $facebook_link = add_query_arg('dn_src', 'facebook', DN_Core::page_url('register'));
        ?>
        <div class="wrap dn-growth-admin">
            <h1>Dating Network · Groei & statistieken</h1>
            <p>De funnel telt echte platformactiviteit. Het doel “Eerste 100” gebruikt alleen <strong>match-klare actieve profielen</strong>.</p>
            <style>
                .dn-growth-admin .dn-admin-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;max-width:1250px;margin:20px 0}.dn-growth-admin .dn-stat{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px}.dn-growth-admin .dn-stat strong{display:block;font-size:30px;color:#BE1D4E}.dn-growth-admin .dn-stat span{color:#646970}.dn-growth-admin .dn-goal{background:#1D1D2C;color:#fff;border-radius:16px;padding:22px;max-width:850px}.dn-growth-admin .dn-goal strong{font-size:42px}.dn-growth-admin .dn-bar{height:12px;background:#3b3b4b;border-radius:99px;overflow:hidden;margin:12px 0}.dn-growth-admin .dn-bar i{display:block;height:100%;background:linear-gradient(90deg,#BE1D4E,#E74C6C)}.dn-growth-admin .dn-panels{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:18px;max-width:1250px;margin-top:20px}.dn-growth-admin .dn-panel{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px}.dn-growth-admin code{word-break:break-all}
            </style>
            <div class="dn-goal"><span>EERSTE 100 MATCH-KLARE PROFIELEN</span><br><strong><?php echo (int)$stats['Match-klaar']; ?>/100</strong><div class="dn-bar"><i style="width:<?php echo min(100,(int)$stats['Match-klaar']); ?>%"></i></div><small><?php echo max(0,100-(int)$stats['Match-klaar']); ?> complete actieve profielen te gaan.</small></div>
            <div class="dn-admin-cards"><div class="dn-stat"><strong><?php echo $registered_today; ?></strong><span>nieuwe accounts vandaag</span></div><div class="dn-stat"><strong><?php echo $unique; ?></strong><span>unieke campagnebezoekers</span></div><div class="dn-stat"><strong><?php echo $clicks; ?></strong><span>campagnebezoeken</span></div><div class="dn-stat"><strong><?php echo $referred; ?></strong><span>referral-aanmeldingen</span></div><?php foreach($stats as $label=>$value): ?><div class="dn-stat"><strong><?php echo (int)$value; ?></strong><span><?php echo esc_html($label); ?></span></div><?php endforeach; ?></div>
            <div class="dn-panels">
                <section class="dn-panel"><h2>Campagnelinks voor vanavond</h2><p><strong>Knipmodel Network</strong><br><code><?php echo esc_html($knip_link); ?></code></p><p><strong>Facebook</strong><br><code><?php echo esc_html($facebook_link); ?></code></p><p class="description">Gebruik <code>?dn_src=...</code> voor iedere campagne. Registraties worden dan aan de bron gekoppeld.</p></section>
                <section class="dn-panel"><h2>Aanmeldingen per bron</h2><?php if(!$sources): ?><p>Nog geen brondata.</p><?php else: ?><table class="widefat striped"><thead><tr><th>Bron</th><th>Aanmeldingen</th></tr></thead><tbody><?php foreach($sources as $row): ?><tr><td><?php echo esc_html((string)$row->source); ?></td><td><?php echo (int)$row->registrations; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
                <section class="dn-panel"><h2>Beste uitnodigers</h2><?php if(!$leaders): ?><p>Nog geen referral-aanmeldingen.</p><?php else: ?><table class="widefat striped"><thead><tr><th>Gebruiker</th><th>Aanmeldingen</th><th>Match-klaar</th></tr></thead><tbody><?php foreach($leaders as $row): $uid=(int)$row->referrer_id;$user=get_userdata($uid); ?><tr><td><?php echo esc_html($user ? $user->display_name : 'ID '.$uid); ?></td><td><?php echo (int)$row->registrations; ?></td><td><?php echo self::referred_count($uid,true); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
            </div>
        </div>
        <?php
    }

    private static function meta_users(string $key, ?string $value = null): int
    {
        global $wpdb;
        if ($value === null) {
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key=%s", $key));
        }
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key=%s AND meta_value=%s", $key, $value));
    }
}
