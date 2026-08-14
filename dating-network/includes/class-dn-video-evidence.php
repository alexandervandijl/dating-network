<?php
if (!defined('ABSPATH')) { exit; }

class DN_Video_Evidence
{
    private const SCHEMA = '1';
    private const RETENTION_DAYS = 30;
    private const PENDING_MINUTES = 15;
    private const MAX_BYTES = 524288;

    public static function init(): void
    {
        self::maybe_install();
        self::cleanup();
        add_filter('do_shortcode_tag', [self::class, 'extend_video_room'], 92, 4);
        add_action('rest_api_init', [self::class, 'rest_routes']);
        add_filter('rest_request_after_callbacks', [self::class, 'link_after_report'], 10, 3);
        add_action('admin_menu', [self::class, 'admin_menu'], 50);
        add_action('admin_post_dn_video_evidence_image', [self::class, 'serve_image']);
        add_action('admin_notices', [self::class, 'admin_notice']);
    }

    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'dn_video_evidence';
    }

    private static function maybe_install(): void
    {
        if ((string)get_option('dn_video_evidence_schema', '') === self::SCHEMA) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_id bigint(20) unsigned NOT NULL DEFAULT 0,
            evidence_id varchar(64) NOT NULL,
            match_id bigint(20) unsigned NOT NULL,
            reporter_id bigint(20) unsigned NOT NULL,
            reported_id bigint(20) unsigned NOT NULL,
            mime_type varchar(32) NOT NULL,
            image_base64 longtext NOT NULL,
            sha256 char(64) NOT NULL,
            created_at datetime NOT NULL,
            delete_after datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY evidence_id (evidence_id),
            KEY report_id (report_id),
            KEY match_reporter (match_id,reporter_id),
            KEY delete_after (delete_after)
        ) {$charset};");
        update_option('dn_video_evidence_schema', self::SCHEMA);
    }

    private static function cleanup(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE delete_after < %s", current_time('mysql')));
    }

    private static function match_for_user(int $match_id, int $user_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dn_matches WHERE id=%d AND status='active' AND (user_low=%d OR user_high=%d)",
            $match_id, $user_id, $user_id
        ));
    }

    private static function other_user_id($row, int $user_id): int
    {
        return (int)$row->user_low === $user_id ? (int)$row->user_high : (int)$row->user_low;
    }

    public static function extend_video_room(string $output, string $tag, $attr, $match): string
    {
        if ($tag !== 'dating_network_chat' || !is_user_logged_in() || empty($_GET['dn_video'])) { return $output; }
        if (!str_contains($output, 'id="dn-video-remote"') || !str_contains($output, 'id="dn-video-report"')) { return $output; }

        $match_id = max(0, (int)($_GET['match'] ?? 0));
        if (!self::match_for_user($match_id, get_current_user_id())) { return $output; }

        $notice = '<div class="dn-video-evidence-notice"><strong>📸 Bewijs bij een veiligheidsmelding</strong><span>Dating Network neemt videogesprekken niet op. Als één van jullie op <em>Melden &amp; ophangen</em> klikt, kan op dat moment één stilstaand beeld van de video van de gemelde persoon worden vastgelegd voor veiligheidsbeoordeling. Het beeld is alleen voor bevoegde beheerders zichtbaar en wordt standaard na 30 dagen verwijderd.</span></div>';
        $output = str_replace('<div class="dn-form-actions">', $notice . '<div class="dn-form-actions">', $output);
        $output = str_replace('Er wordt geen screenshot, audio of video meegestuurd. De melding en gespreksmetadata komen wel in het interne veiligheidsdossier.', 'Bij een melding wordt, als de videostream technisch beschikbaar is, één bewijsscreenshot van de video van je match opgeslagen. Audio en volledige video worden niet opgeslagen.');

        $endpoint = esc_url_raw(rest_url('dating-network/v1/video-evidence/capture'));
        $nonce = wp_create_nonce('wp_rest');

        ob_start(); ?>
        <style>
            .dn-video-evidence-notice{margin:14px 0;padding:13px 15px;border:1px solid #e5ced6;border-radius:14px;background:#fff7fa;display:grid;gap:4px}.dn-video-evidence-notice strong{color:#8f153d}.dn-video-evidence-notice span{font-size:.9rem;line-height:1.5;color:#686875}.dn-video-evidence-state{margin-top:10px;font-size:.85rem}.dn-video-evidence-state.is-ok{color:#137333}.dn-video-evidence-state.is-warning{color:#9a6700}
        </style>
        <script>
        (() => {
            const endpoint = <?php echo wp_json_encode($endpoint); ?>;
            const nonce = <?php echo wp_json_encode($nonce); ?>;
            const matchId = <?php echo (int)$match_id; ?>;
            const reportButton = document.getElementById('dn-video-report');
            const remoteVideo = document.getElementById('dn-video-remote');
            const dialog = document.querySelector('.dn-video-report-dialog');
            let uploading = false, uploaded = false;

            const state = document.createElement('p');
            state.className = 'dn-video-evidence-state';
            if (dialog) dialog.appendChild(state);

            const capture = async () => {
                if (uploading || uploaded || !remoteVideo || remoteVideo.readyState < 2 || !remoteVideo.videoWidth || !remoteVideo.videoHeight) {
                    if (!uploaded && state) { state.textContent = 'Bewijsscreenshot kon nog niet worden vastgelegd; de melding kan wel worden verstuurd.'; state.className = 'dn-video-evidence-state is-warning'; }
                    return;
                }
                uploading = true;
                try {
                    const maxWidth = 720;
                    const scale = Math.min(1, maxWidth / remoteVideo.videoWidth);
                    const width = Math.max(1, Math.round(remoteVideo.videoWidth * scale));
                    const height = Math.max(1, Math.round(remoteVideo.videoHeight * scale));
                    const canvas = document.createElement('canvas');
                    canvas.width = width; canvas.height = height;
                    const ctx = canvas.getContext('2d', {alpha:false});
                    ctx.drawImage(remoteVideo, 0, 0, width, height);
                    const image = canvas.toDataURL('image/jpeg', 0.72);
                    const evidenceId = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('ev-' + Date.now() + '-' + Math.random().toString(36).slice(2));
                    const response = await fetch(endpoint, {
                        method:'POST', credentials:'same-origin',
                        headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
                        body:JSON.stringify({match:matchId,evidence_id:evidenceId,image})
                    });
                    if (!response.ok) throw new Error('upload failed');
                    uploaded = true;
                    if (state) { state.textContent = '✓ Bewijsscreenshot veilig vastgelegd voor de beheerder.'; state.className = 'dn-video-evidence-state is-ok'; }
                } catch (e) {
                    if (state) { state.textContent = 'Bewijsscreenshot kon niet worden opgeslagen; de melding kan wel worden verstuurd.'; state.className = 'dn-video-evidence-state is-warning'; }
                } finally {
                    uploading = false;
                }
            };

            reportButton?.addEventListener('click', () => { capture(); }, {capture:true});
        })();
        </script>
        <?php
        return $output . (string)ob_get_clean();
    }

    public static function rest_routes(): void
    {
        register_rest_route('dating-network/v1', '/video-evidence/capture', [
            'methods' => 'POST',
            'permission_callback' => static fn() => is_user_logged_in(),
            'callback' => [self::class, 'capture'],
        ]);
    }

    public static function capture(WP_REST_Request $request)
    {
        self::cleanup();
        $reporter = get_current_user_id();
        $match_id = absint($request->get_param('match'));
        $row = self::match_for_user($match_id, $reporter);
        if (!$row) { return new WP_Error('dn_evidence_forbidden', 'Geen toegang tot deze actieve videomatch.', ['status'=>403]); }
        $reported = self::other_user_id($row, $reporter);

        $evidence_id = substr(sanitize_text_field((string)$request->get_param('evidence_id')), 0, 64);
        if ($evidence_id === '') { return new WP_Error('dn_evidence_id', 'Evidence-ID ontbreekt.', ['status'=>400]); }
        $image = (string)$request->get_param('image');
        if (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $image, $m)) {
            return new WP_Error('dn_evidence_format', 'Alleen een JPEG-bewijsscreenshot is toegestaan.', ['status'=>415]);
        }
        $binary = base64_decode($m[1], true);
        if ($binary === false || strlen($binary) < 100 || strlen($binary) > self::MAX_BYTES) {
            return new WP_Error('dn_evidence_size', 'Bewijsscreenshot heeft een ongeldige grootte.', ['status'=>413]);
        }
        $info = @getimagesizefromstring($binary);
        if (!$info || ($info['mime'] ?? '') !== 'image/jpeg' || (int)$info[0] > 1280 || (int)$info[1] > 1280) {
            return new WP_Error('dn_evidence_image', 'Ongeldige bewijsscreenshot.', ['status'=>415]);
        }

        global $wpdb;
        $recent = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table() . " WHERE reporter_id=%d AND created_at >= %s",
            $reporter,
            wp_date('Y-m-d H:i:s', time() - HOUR_IN_SECONDS)
        ));
        if ($recent >= 20) { return new WP_Error('dn_evidence_rate', 'Te veel bewijsscreenshots in korte tijd.', ['status'=>429]); }

        $created = current_time('mysql');
        $delete_after = wp_date('Y-m-d H:i:s', current_time('timestamp') + self::PENDING_MINUTES * MINUTE_IN_SECONDS);
        $ok = $wpdb->insert(self::table(), [
            'report_id'=>0,
            'evidence_id'=>$evidence_id,
            'match_id'=>$match_id,
            'reporter_id'=>$reporter,
            'reported_id'=>$reported,
            'mime_type'=>'image/jpeg',
            'image_base64'=>base64_encode($binary),
            'sha256'=>hash('sha256', $binary),
            'created_at'=>$created,
            'delete_after'=>$delete_after,
        ], ['%d','%s','%d','%d','%d','%s','%s','%s','%s','%s']);
        if ($ok === false) { return new WP_Error('dn_evidence_store', 'Bewijsscreenshot kon niet worden opgeslagen.', ['status'=>500]); }
        return rest_ensure_response(['ok'=>true]);
    }

    public static function link_after_report($response, $handler, WP_REST_Request $request)
    {
        if ($request->get_route() !== '/dating-network/v1/video-safety/report' || is_wp_error($response)) { return $response; }
        if (method_exists($response, 'get_status') && $response->get_status() >= 400) { return $response; }
        $reporter = get_current_user_id();
        $match_id = absint($request->get_param('match'));
        if (!$reporter || !$match_id) { return $response; }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dn_matches WHERE id=%d AND (user_low=%d OR user_high=%d)",
            $match_id, $reporter, $reporter
        ));
        if (!$row) { return $response; }
        $reported = self::other_user_id($row, $reporter);
        $report_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dn_reports WHERE reporter_id=%d AND reported_id=%d ORDER BY id DESC LIMIT 1",
            $reporter, $reported
        ));
        if (!$report_id) { return $response; }

        $pending_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE report_id=0 AND match_id=%d AND reporter_id=%d AND reported_id=%d AND created_at >= %s ORDER BY id DESC LIMIT 1",
            $match_id,
            $reporter,
            $reported,
            wp_date('Y-m-d H:i:s', time() - self::PENDING_MINUTES * MINUTE_IN_SECONDS)
        ));
        if ($pending_id) {
            $delete_after = wp_date('Y-m-d H:i:s', current_time('timestamp') + self::RETENTION_DAYS * DAY_IN_SECONDS);
            $wpdb->update(self::table(), ['report_id'=>$report_id,'delete_after'=>$delete_after], ['id'=>$pending_id], ['%d','%s'], ['%d']);
        }
        return $response;
    }

    public static function admin_menu(): void
    {
        add_submenu_page('dating-network', 'Videobewijs', 'Videobewijs', 'manage_options', 'dating-network-video-evidence', [self::class, 'admin_page']);
    }

    public static function admin_notice(): void
    {
        if (!current_user_can('manage_options')) { return; }
        $page = sanitize_key((string)($_GET['page'] ?? ''));
        if ($page !== 'dating-network-video-safety') { return; }
        echo '<div class="notice notice-info"><p><strong>V0.6.0:</strong> nieuwe videomeldingen kunnen nu één rapport-triggered bewijsscreenshot bevatten. Er is nog steeds geen continue opname of live meekijken. Bewijs staat onder <a href="' . esc_url(admin_url('admin.php?page=dating-network-video-evidence')) . '">Dating Network → Videobewijs</a>.</p></div>';
    }

    public static function admin_page(): void
    {
        if (!current_user_can('manage_options')) { return; }
        self::cleanup();
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM " . self::table() . " WHERE report_id>0 ORDER BY created_at DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1>Dating Network · Videobewijs</h1>
            <p>Alleen bij een veiligheidsmelding kan één stilstaand beeld worden vastgelegd. Er is geen continue opname, geen audio-opname en geen live meekijkfunctie. Bewijsscreenshots worden standaard na <?php echo self::RETENTION_DAYS; ?> dagen verwijderd.</p>
            <p><strong>Moderatieregel:</strong> één melding leidt niet automatisch tot een platformban. Beoordeel screenshot, melding, eerdere signalen en context voordat je een beheeractie uitvoert.</p>
            <?php if (!$rows): ?><p>Nog geen gekoppelde bewijsscreenshots.</p><?php else: ?>
            <table class="widefat striped"><thead><tr><th>Datum</th><th>Rapport</th><th>Melder</th><th>Gemeld</th><th>Match</th><th>Bewijs</th><th>Verwijderd na</th></tr></thead><tbody>
            <?php foreach ($rows as $row):
                $reporter=get_userdata((int)$row->reporter_id); $reported=get_userdata((int)$row->reported_id);
                $url=wp_nonce_url(admin_url('admin-post.php?action=dn_video_evidence_image&id='.(int)$row->id),'dn_video_evidence_'.(int)$row->id);
            ?>
                <tr><td><?php echo esc_html((string)$row->created_at); ?></td><td>#<?php echo (int)$row->report_id; ?></td><td><?php echo esc_html($reporter?$reporter->display_name:'ID '.(int)$row->reporter_id); ?></td><td><strong><?php echo esc_html($reported?$reported->display_name:'ID '.(int)$row->reported_id); ?></strong></td><td>#<?php echo (int)$row->match_id; ?></td><td><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url($url); ?>">Screenshot bekijken</a><br><small>SHA-256 <?php echo esc_html(substr((string)$row->sha256,0,16)); ?>…</small></td><td><?php echo esc_html((string)$row->delete_after); ?></td></tr>
            <?php endforeach; ?></tbody></table><?php endif; ?>
        </div>
        <?php
    }

    public static function serve_image(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Geen toegang.', 403); }
        $id = max(0, (int)($_GET['id'] ?? 0));
        check_admin_referer('dn_video_evidence_' . $id);
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id=%d AND report_id>0", $id));
        if (!$row) { wp_die('Bewijsscreenshot niet gevonden.', 404); }
        $binary = base64_decode((string)$row->image_base64, true);
        if ($binary === false || !hash_equals((string)$row->sha256, hash('sha256', $binary))) { wp_die('Bewijsscreenshot is beschadigd.', 500); }
        nocache_headers();
        header('Content-Type: image/jpeg');
        header('Content-Disposition: inline; filename="dating-network-video-evidence-' . $id . '.jpg"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($binary));
        echo $binary;
        exit;
    }
}
