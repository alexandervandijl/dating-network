<?php
if (!defined('ABSPATH')) { exit; }

class DN_Video_Safety
{
    private const SCHEMA = '1';

    public static function init(): void
    {
        self::maybe_install();
        add_filter('do_shortcode_tag', [self::class, 'extend_video_room'], 85, 4);
        add_action('rest_api_init', [self::class, 'rest_routes']);
        add_action('wp_enqueue_scripts', [self::class, 'assets'], 30);
        add_action('admin_menu', [self::class, 'admin_menu'], 45);
        add_action('admin_post_dn_video_report_action', [self::class, 'handle_report_action']);
    }

    private static function maybe_install(): void
    {
        if ((string)get_option('dn_video_safety_schema', '') === self::SCHEMA) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'dn_video_events';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            call_id varchar(64) NOT NULL,
            match_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            event_type varchar(32) NOT NULL,
            detail varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY call_id (call_id),
            KEY match_id (match_id),
            KEY user_id (user_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset};");
        update_option('dn_video_safety_schema', self::SCHEMA);
    }

    public static function assets(): void
    {
        if (class_exists('DN_Core') && DN_Core::current_page_key() === 'chat') {
            wp_enqueue_style('dating-network-video-safety', DN_URL . 'assets/video-safety.css', ['dating-network-branding'], DN_VERSION);
        }
    }

    private static function match_for_user(int $match_id, int $user_id, bool $active_only = false)
    {
        global $wpdb;
        $status = $active_only ? " AND status='active'" : '';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dn_matches WHERE id=%d{$status} AND (user_low=%d OR user_high=%d)",
            $match_id,
            $user_id,
            $user_id
        ));
    }

    private static function other_user_id($row, int $user_id): int
    {
        return (int)$row->user_low === $user_id ? (int)$row->user_high : (int)$row->user_low;
    }

    public static function extend_video_room(string $output, string $tag, $attr, $match): string
    {
        if ($tag !== 'dating_network_chat' || !is_user_logged_in() || empty($_GET['dn_video'])) { return $output; }
        if (!str_contains($output, 'id="dn-video-stage"')) { return $output; }

        $match_id = max(0, (int)($_GET['match'] ?? 0));
        $user_id = get_current_user_id();
        $row = self::match_for_user($match_id, $user_id, true);
        if (!$row) { return $output; }
        $other_id = self::other_user_id($row, $user_id);
        $other = get_userdata($other_id);
        $other_name = $other ? $other->display_name : 'je match';

        $rules = '<div class="dn-video-safety-rule"><strong>🛡️ Houd het veilig en respectvol</strong><span>Geen naakt of seksueel expliciet gedrag, geen seksuele handelingen voor de camera en stop direct als je match een grens aangeeft. Bij ongepast gedrag kun je het gesprek onmiddellijk beëindigen en melden.</span></div>';
        $output = str_replace('<strong>Voor je camera aangaat</strong>', '<strong>Voor je camera aangaat</strong>' . $rules, $output);

        $report_button = '<button id="dn-video-report" type="button" class="dn-video-control dn-video-report">🚨 Melden &amp; ophangen</button>';
        $output = str_replace('<button id="dn-video-end"', $report_button . '<button id="dn-video-end"', $output);

        $rest = esc_url_raw(rest_url('dating-network/v1/video-safety'));
        $nonce = wp_create_nonce('wp_rest');
        $matches_url = DN_Core::page_url('matches');

        ob_start(); ?>
        <div id="dn-video-report-modal" class="dn-video-report-modal" hidden>
            <div class="dn-video-report-dialog" role="dialog" aria-modal="true" aria-labelledby="dn-video-report-title">
                <button type="button" class="dn-video-report-close" id="dn-video-report-close" aria-label="Sluiten">×</button>
                <span class="dn-page-kicker">VEILIGHEIDSMELDING</span>
                <h2 id="dn-video-report-title">Meld gedrag van <?php echo esc_html($other_name); ?></h2>
                <p>Het gesprek wordt direct beëindigd. Dating Network neemt het videogesprek niet op; beschrijf daarom kort wat je zag of meemaakte.</p>
                <label>Wat gebeurde er?
                    <select id="dn-video-report-reason">
                        <option value="nudity">Naakt of seksueel expliciet beeld</option>
                        <option value="sexual">Ongewenst seksueel gedrag</option>
                        <option value="harassment">Intimidatie / lastigvallen</option>
                        <option value="pressure">Grenzen niet respecteren / druk zetten</option>
                        <option value="threat">Bedreiging / agressie</option>
                        <option value="fake">Persoon lijkt niet bij het profiel te horen</option>
                        <option value="underage">Twijfel over leeftijd / mogelijk minderjarig</option>
                        <option value="other">Ander veiligheidsprobleem</option>
                    </select>
                </label>
                <label>Toelichting
                    <textarea id="dn-video-report-details" rows="4" maxlength="1000" placeholder="Beschrijf kort wat er gebeurde."></textarea>
                </label>
                <label class="dn-video-report-block"><input id="dn-video-report-block" type="checkbox" checked> Deze persoon ook direct voor mij blokkeren</label>
                <div class="dn-form-actions">
                    <button type="button" class="dn-button dn-video-report-submit" id="dn-video-report-submit">Melding versturen</button>
                    <button type="button" class="dn-button dn-button-ghost" id="dn-video-report-cancel">Annuleren</button>
                </div>
                <p class="dn-muted">Er wordt geen screenshot, audio of video meegestuurd. De melding en gespreksmetadata komen wel in het interne veiligheidsdossier.</p>
            </div>
        </div>
        <script>
        (() => {
            const endpoint = <?php echo wp_json_encode($rest); ?>;
            const nonce = <?php echo wp_json_encode($nonce); ?>;
            const matchId = <?php echo (int)$match_id; ?>;
            const matchesUrl = <?php echo wp_json_encode($matches_url); ?>;
            const callId = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('call-' + Date.now() + '-' + Math.random().toString(36).slice(2));
            const startButton = document.getElementById('dn-video-start');
            const endButton = document.getElementById('dn-video-end');
            const reportButton = document.getElementById('dn-video-report');
            const status = document.getElementById('dn-video-status');
            const modal = document.getElementById('dn-video-report-modal');
            const closeButton = document.getElementById('dn-video-report-close');
            const cancelButton = document.getElementById('dn-video-report-cancel');
            const submitButton = document.getElementById('dn-video-report-submit');
            const remoteVideo = document.getElementById('dn-video-remote');
            let connectedLogged = false, failedLogged = false, endedLogged = false, reportOpen = false;

            const api = async (path, body) => {
                const response = await fetch(endpoint + path, {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type':'application/json','X-WP-Nonce':nonce},
                    body: JSON.stringify(body || {})
                });
                const json = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(json.message || 'Actie mislukt');
                return json;
            };
            const event = (type, detail='') => api('/event', {match:matchId, call_id:callId, event:type, detail}).catch(()=>{});
            event('room_open');
            startButton?.addEventListener('click', () => event('camera_start'));
            endButton?.addEventListener('click', () => { if (!endedLogged) { endedLogged = true; event('ended','user'); } });

            if (status) {
                const inspectStatus = () => {
                    const text = (status.textContent || '').toLowerCase();
                    if (!connectedLogged && text.includes('verbonden') && !text.includes('onderbroken')) { connectedLogged = true; event('connected'); }
                    if (!failedLogged && text.includes('mislukt')) { failedLogged = true; event('failed', text.slice(0,240)); }
                    if (!endedLogged && (text.includes('heeft opgehangen') || text.includes('gesprek beëindigd'))) { endedLogged = true; event('ended','remote_or_system'); }
                };
                new MutationObserver(inspectStatus).observe(status, {childList:true,subtree:true,characterData:true});
            }

            const openModal = () => { reportOpen = true; modal.hidden = false; document.body.classList.add('dn-video-modal-open'); };
            const closeModal = () => { reportOpen = false; modal.hidden = true; document.body.classList.remove('dn-video-modal-open'); };
            reportButton?.addEventListener('click', openModal);
            closeButton?.addEventListener('click', closeModal);
            cancelButton?.addEventListener('click', closeModal);
            modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', e => { if (reportOpen && e.key === 'Escape') closeModal(); });

            submitButton?.addEventListener('click', async () => {
                const reason = document.getElementById('dn-video-report-reason').value;
                const details = document.getElementById('dn-video-report-details').value.trim();
                const block = document.getElementById('dn-video-report-block').checked;
                submitButton.disabled = true;
                submitButton.textContent = 'Melding versturen…';
                if (remoteVideo) { try { remoteVideo.pause(); remoteVideo.srcObject = null; } catch(e) {} }
                if (endButton && !endButton.disabled) endButton.click();
                try {
                    await api('/report', {match:matchId, call_id:callId, reason, details, block});
                    alert('Dank je. De melding is opgeslagen' + (block ? ' en deze persoon is voor jou geblokkeerd.' : '.'));
                    window.location.href = matchesUrl;
                } catch (error) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Melding versturen';
                    alert(error.message || 'De melding kon niet worden opgeslagen.');
                }
            });
        })();
        </script>
        <?php
        return $output . (string)ob_get_clean();
    }

    public static function rest_routes(): void
    {
        register_rest_route('dating-network/v1', '/video-safety/event', [
            'methods' => 'POST',
            'permission_callback' => static fn() => is_user_logged_in(),
            'callback' => [self::class, 'rest_event'],
        ]);
        register_rest_route('dating-network/v1', '/video-safety/report', [
            'methods' => 'POST',
            'permission_callback' => static fn() => is_user_logged_in(),
            'callback' => [self::class, 'rest_report'],
        ]);
    }

    public static function rest_event(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $match_id = absint($request->get_param('match'));
        if (!self::match_for_user($match_id, $user_id, false)) {
            return new WP_Error('dn_video_event_forbidden', 'Geen toegang tot deze videosessie.', ['status'=>403]);
        }
        $event = sanitize_key((string)$request->get_param('event'));
        $allowed = ['room_open','camera_start','connected','failed','ended','reported'];
        if (!in_array($event, $allowed, true)) {
            return new WP_Error('dn_video_event_type', 'Ongeldig video-event.', ['status'=>400]);
        }
        $call_id = substr(sanitize_text_field((string)$request->get_param('call_id')), 0, 64);
        if ($call_id === '') { return new WP_Error('dn_video_call', 'Call-ID ontbreekt.', ['status'=>400]); }
        $detail = substr(sanitize_text_field((string)$request->get_param('detail')), 0, 255);

        global $wpdb;
        $table = $wpdb->prefix . 'dn_video_events';
        $recent = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id=%d AND created_at>=%s",
            $user_id,
            wp_date('Y-m-d H:i:s', time() - MINUTE_IN_SECONDS)
        ));
        if ($recent > 40) { return new WP_Error('dn_video_rate', 'Te veel video-events.', ['status'=>429]); }
        $wpdb->insert($table, [
            'call_id'=>$call_id,
            'match_id'=>$match_id,
            'user_id'=>$user_id,
            'event_type'=>$event,
            'detail'=>$detail,
            'created_at'=>current_time('mysql'),
        ], ['%s','%d','%d','%s','%s','%s']);
        return rest_ensure_response(['ok'=>true]);
    }

    public static function rest_report(WP_REST_Request $request)
    {
        $reporter = get_current_user_id();
        $match_id = absint($request->get_param('match'));
        $row = self::match_for_user($match_id, $reporter, true);
        if (!$row) { return new WP_Error('dn_video_report_forbidden', 'Deze actieve match bestaat niet meer.', ['status'=>403]); }
        $reported = self::other_user_id($row, $reporter);

        $map = [
            'nudity' => ['sexual', 'Naakt of seksueel expliciet beeld'],
            'sexual' => ['sexual', 'Ongewenst seksueel gedrag'],
            'harassment' => ['harassment', 'Intimidatie / lastigvallen'],
            'pressure' => ['pressure', 'Grenzen niet respecteren / druk zetten'],
            'threat' => ['abuse', 'Bedreiging / agressie'],
            'fake' => ['fake', 'Persoon lijkt niet bij het profiel te horen'],
            'underage' => ['safety', 'Twijfel over leeftijd / mogelijk minderjarig'],
            'other' => ['safety', 'Ander veiligheidsprobleem'],
        ];
        $reason_key = sanitize_key((string)$request->get_param('reason'));
        if (!isset($map[$reason_key])) { $reason_key = 'other'; }
        [$reason, $label] = $map[$reason_key];
        $details = sanitize_textarea_field((string)$request->get_param('details'));
        $details = mb_substr($details, 0, 1000);
        $report_text = 'Video #' . $match_id . ' — ' . $label . ($details !== '' ? ' — ' . $details : '');
        $block = rest_sanitize_boolean($request->get_param('block'));

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'dn_reports', [
            'reporter_id'=>$reporter,
            'reported_id'=>$reported,
            'reason'=>$reason,
            'details'=>$report_text,
            'status'=>'open',
            'created_at'=>current_time('mysql'),
        ], ['%d','%d','%s','%s','%s','%s']);
        $report_id = (int)$wpdb->insert_id;

        if (class_exists('DN_Reputation')) {
            DN_Reputation::add_event(
                $reported,
                'negative',
                'user_report_video_' . $reason_key,
                'Videomelding door gebruiker ID ' . $reporter . ': ' . $label . ($details !== '' ? ' — ' . $details : ''),
                $reporter
            );
        }

        if ($block) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}dn_blocks (blocker_id,blocked_id,created_at) VALUES (%d,%d,%s)",
                $reporter,
                $reported,
                current_time('mysql')
            ));
            $wpdb->update($wpdb->prefix . 'dn_matches', [
                'status'=>'blocked',
                'ended_at'=>current_time('mysql'),
            ], ['id'=>$match_id], ['%s','%s'], ['%d']);
        }

        $call_id = substr(sanitize_text_field((string)$request->get_param('call_id')), 0, 64);
        if ($call_id !== '') {
            $wpdb->insert($wpdb->prefix . 'dn_video_events', [
                'call_id'=>$call_id,
                'match_id'=>$match_id,
                'user_id'=>$reporter,
                'event_type'=>'reported',
                'detail'=>$reason_key,
                'created_at'=>current_time('mysql'),
            ], ['%s','%d','%d','%s','%s','%s']);
        }
        return rest_ensure_response(['ok'=>true,'report_id'=>$report_id,'blocked'=>$block]);
    }

    public static function admin_menu(): void
    {
        add_submenu_page(
            'dating-network',
            'Videoveiligheid',
            'Videoveiligheid',
            'manage_options',
            'dating-network-video-safety',
            [self::class, 'admin_page']
        );
    }

    public static function handle_report_action(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Geen toegang.'); }
        $report_id = max(0, (int)($_POST['report_id'] ?? 0));
        check_admin_referer('dn_video_report_' . $report_id);
        $action = sanitize_key(wp_unslash($_POST['report_action'] ?? ''));
        if (!in_array($action, ['resolved','dismissed','open'], true)) { wp_die('Ongeldige actie.'); }
        global $wpdb;
        $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dn_reports WHERE id=%d", $report_id));
        if (!$report || !str_starts_with((string)$report->details, 'Video #')) { wp_die('Videomelding niet gevonden.'); }
        $wpdb->update($wpdb->prefix . 'dn_reports', ['status'=>$action], ['id'=>$report_id], ['%s'], ['%d']);
        wp_safe_redirect(admin_url('admin.php?page=dating-network-video-safety&updated=1'));
        exit;
    }

    public static function admin_page(): void
    {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $events = $wpdb->prefix . 'dn_video_events';
        $reports = $wpdb->prefix . 'dn_reports';
        $since = wp_date('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $like = $wpdb->esc_like('Video #') . '%';

        $stats = [
            'Videoruimtes geopend' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events} WHERE event_type='room_open' AND created_at>=%s", $since)),
            'Camera gestart' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events} WHERE event_type='camera_start' AND created_at>=%s", $since)),
            'Verbonden' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events} WHERE event_type='connected' AND created_at>=%s", $since)),
            'P2P mislukt' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events} WHERE event_type='failed' AND created_at>=%s", $since)),
            'Meldingen 24 uur' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$reports} WHERE details LIKE %s AND created_at>=%s", $like, $since)),
            'Open videomeldingen' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$reports} WHERE details LIKE %s AND status='open'", $like)),
        ];
        $recent_reports = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$reports} WHERE details LIKE %s ORDER BY created_at DESC,id DESC LIMIT 100", $like));
        $recent_events = $wpdb->get_results("SELECT * FROM {$events} ORDER BY created_at DESC,id DESC LIMIT 150");
        ?>
        <div class="wrap dn-video-safety-admin">
            <h1>Dating Network · Videoveiligheid</h1>
            <p>Dit dashboard bewaakt <strong>gespreksmetadata en gebruikersmeldingen</strong>. Dating Network neemt geen video of audio op en bewaart geen screenshots van videogesprekken.</p>
            <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success"><p>Meldingsstatus bijgewerkt.</p></div><?php endif; ?>
            <style>.dn-video-safety-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;max-width:1200px;margin:20px 0}.dn-video-safety-stat{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:16px}.dn-video-safety-stat strong{display:block;font-size:30px;color:#BE1D4E}.dn-video-safety-stat span{color:#646970}.dn-video-safety-admin table{margin-top:12px}.dn-video-safety-admin .dn-danger{color:#b42318;font-weight:700}</style>
            <h2>Laatste 24 uur</h2>
            <div class="dn-video-safety-stats"><?php foreach ($stats as $label=>$value): ?><div class="dn-video-safety-stat"><strong><?php echo (int)$value; ?></strong><span><?php echo esc_html($label); ?></span></div><?php endforeach; ?></div>

            <h2>Videomeldingen</h2>
            <?php if (!$recent_reports): ?><p>Nog geen videomeldingen.</p><?php else: ?>
            <table class="widefat striped"><thead><tr><th>Datum</th><th>Gemeld door</th><th>Over</th><th>Reden</th><th>Details</th><th>Status</th><th>Actie</th></tr></thead><tbody>
            <?php foreach ($recent_reports as $report): $reporter=get_userdata((int)$report->reporter_id); $reported=get_userdata((int)$report->reported_id); ?>
            <tr><td><?php echo esc_html((string)$report->created_at); ?></td><td><?php echo esc_html($reporter ? $reporter->display_name : 'ID '.(int)$report->reporter_id); ?></td><td><a href="<?php echo esc_url(admin_url('admin.php?page=dating-network-trust&user='.(int)$report->reported_id)); ?>"><?php echo esc_html($reported ? $reported->display_name : 'ID '.(int)$report->reported_id); ?></a></td><td class="<?php echo in_array($report->reason,['sexual','abuse','safety'],true)?'dn-danger':''; ?>"><?php echo esc_html((string)$report->reason); ?></td><td><?php echo esc_html((string)$report->details); ?></td><td><?php echo esc_html((string)$report->status); ?></td><td>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:6px;flex-wrap:wrap"><?php wp_nonce_field('dn_video_report_'.(int)$report->id); ?><input type="hidden" name="action" value="dn_video_report_action"><input type="hidden" name="report_id" value="<?php echo (int)$report->id; ?>"><button class="button" name="report_action" value="resolved">Afgehandeld</button><button class="button" name="report_action" value="dismissed">Onterecht</button><?php if($report->status!=='open'): ?><button class="button" name="report_action" value="open">Heropen</button><?php endif; ?></form>
            </td></tr><?php endforeach; ?></tbody></table><?php endif; ?>

            <h2>Recente video-events</h2>
            <p class="description">Technische metadata om te zien of videobellen werkt. Er staat geen beeld, geluid of inhoud van het gesprek in deze tabel.</p>
            <?php if (!$recent_events): ?><p>Nog geen video-events.</p><?php else: ?><table class="widefat striped"><thead><tr><th>Datum</th><th>Gebruiker</th><th>Match</th><th>Event</th><th>Call-ID</th><th>Detail</th></tr></thead><tbody><?php foreach($recent_events as $event): $user=get_userdata((int)$event->user_id); ?><tr><td><?php echo esc_html((string)$event->created_at); ?></td><td><?php echo esc_html($user?$user->display_name:'ID '.(int)$event->user_id); ?></td><td>#<?php echo (int)$event->match_id; ?></td><td><?php echo esc_html((string)$event->event_type); ?></td><td><code><?php echo esc_html(substr((string)$event->call_id,0,18)); ?>…</code></td><td><?php echo esc_html((string)$event->detail); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        </div>
        <?php
    }
}
