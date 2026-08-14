<?php
if (!defined('ABSPATH')) { exit; }

class DN_Video
{
    public static function init(): void
    {
        add_filter('do_shortcode_tag', [self::class, 'filter_shortcode'], 70, 4);
        add_action('admin_menu', [self::class, 'admin_menu'], 40);
        add_action('admin_post_dn_video_save', [self::class, 'save_settings']);
    }

    private static function configured(): bool
    {
        return (string)get_option('dn_cf_account') !== ''
            && (string)get_option('dn_cf_app') !== ''
            && (string)get_option('dn_cf_token') !== ''
            && (string)get_option('dn_cf_preset') !== '';
    }

    private static function active_match(int $match_id, int $user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dn_matches';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id=%d AND status='active' AND (user_low=%d OR user_high=%d)",
            $match_id,
            $user_id,
            $user_id
        ));
    }

    public static function filter_shortcode(string $output, string $tag, $attr, $match): string
    {
        if ($tag !== 'dating_network_chat' || !is_user_logged_in()) { return $output; }
        $match_id = (int)($_GET['match'] ?? 0);
        $row = self::active_match($match_id, get_current_user_id());
        if (!$row || !self::configured()) { return $output; }

        if (!empty($_GET['dn_video'])) {
            return self::render_room($match_id, $row);
        }

        $url = add_query_arg(['match' => $match_id, 'dn_video' => 1], DN_Core::page_url('chat'));
        $bar = '<div class="dn-card dn-video-cta"><div><strong>📹 Liever elkaar even zien?</strong><br><span class="dn-muted">Videobellen kan binnen jullie bestaande match.</span></div><a class="dn-button" href="' . esc_url($url) . '">Videobellen</a></div>';
        return str_replace('<div class="dn-card dn-chat-log">', $bar . '<div class="dn-card dn-chat-log">', $output);
    }

    private static function render_room(int $match_id, $row): string
    {
        $user_id = get_current_user_id();
        $other_id = (int)$row->user_low === $user_id ? (int)$row->user_high : (int)$row->user_low;
        $other = get_userdata($other_id);
        $token = self::participant_token($match_id, $user_id);
        $back = DN_Core::page_url('chat', ['match' => $match_id]);

        if (is_wp_error($token)) {
            return '<div class="dn-wrap"><div class="dn-notice dn-notice-error">Videobellen kon niet worden gestart. Probeer later opnieuw.</div><a class="dn-button dn-button-ghost" href="' . esc_url($back) . '">← Terug naar chat</a></div>';
        }

        ob_start(); ?>
        <div class="dn-wrap dn-video-wrap">
            <div class="dn-page-hero dn-page-hero-compact">
                <span class="dn-page-kicker">1-OP-1 VIDEOBELLEN</span>
                <h1>Videobellen met <?php echo esc_html($other ? $other->display_name : 'je match'); ?></h1>
                <p>Alleen jullie actieve match kan deze videoruimte openen. Dating Network neemt het gesprek niet op. Maak zelf ook geen opname zonder toestemming.</p>
            </div>
            <div class="dn-card dn-video-consent">
                <strong>Voor je camera aangaat</strong>
                <p class="dn-muted">De videoverbinding wordt technisch geleverd via Cloudflare RealtimeKit. Camera en microfoon worden pas benaderd nadat je hieronder start. Rapporteren en blokkeren blijft via jullie Dating Network-chat beschikbaar.</p>
                <button id="dn-video-start" class="dn-button" data-token="<?php echo esc_attr($token); ?>">Camera en microfoon testen →</button>
                <a class="dn-button dn-button-ghost" href="<?php echo esc_url($back); ?>">Terug naar chat</a>
            </div>
            <div id="dn-video-stage" class="dn-video-stage" hidden>
                <rtk-meeting id="dn-rtk" show-setup-screen="true"></rtk-meeting>
            </div>
        </div>
        <script>
        document.getElementById('dn-video-start')?.addEventListener('click', async function () {
            const button = this;
            button.disabled = true;
            button.textContent = 'Videogesprek laden…';
            try {
                const modules = await Promise.all([
                    import('https://cdn.jsdelivr.net/npm/@cloudflare/realtimekit@latest/dist/index.es.js'),
                    import('https://cdn.jsdelivr.net/npm/@cloudflare/realtimekit-ui@latest/loader/index.es2017.js')
                ]);
                const RealtimeKitClient = modules[0].default;
                modules[1].defineCustomElements();
                await customElements.whenDefined('rtk-meeting');
                const meeting = await RealtimeKitClient.init({
                    authToken: button.dataset.token,
                    defaults: { audio: true, video: true }
                });
                const element = document.getElementById('dn-rtk');
                element.meeting = meeting;
                element.overrides = { disablePrivateChat: true, disableEmojiPicker: true };
                const stage = document.getElementById('dn-video-stage');
                stage.hidden = false;
                button.closest('.dn-video-consent').hidden = true;
            } catch (error) {
                button.disabled = false;
                button.textContent = 'Opnieuw proberen';
                alert('Videobellen kon niet laden. Controleer je camera, microfoon en internetverbinding.');
            }
        });
        </script>
        <?php
        return (string)ob_get_clean();
    }

    private static function participant_token(int $match_id, int $user_id)
    {
        $meeting_id = (string)get_option('dn_video_meeting_' . $match_id, '');
        if ($meeting_id === '') {
            $response = self::api('POST', 'meetings', [
                'title' => 'Dating Network 1-op-1',
                'record_on_start' => false,
                'persist_chat' => false,
                'live_stream_on_start' => false,
                'transcribe_on_end' => false,
            ]);
            if (is_wp_error($response) || empty($response['data']['id'])) { return new WP_Error('meeting'); }
            $meeting_id = (string)$response['data']['id'];
            update_option('dn_video_meeting_' . $match_id, $meeting_id, false);
        }

        $meta_key = 'dn_video_participant_' . $match_id;
        $participant_id = (string)get_user_meta($user_id, $meta_key, true);
        if ($participant_id !== '') {
            $response = self::api('POST', 'meetings/' . rawurlencode($meeting_id) . '/participants/' . rawurlencode($participant_id) . '/token', []);
            if (!is_wp_error($response) && !empty($response['data']['token'])) {
                return (string)$response['data']['token'];
            }
        }

        $preset = (string)get_option('dn_cf_preset', '');
        $user = get_userdata($user_id);
        $response = self::api('POST', 'meetings/' . rawurlencode($meeting_id) . '/participants', [
            'name' => $user ? $user->display_name : 'Single',
            'preset_name' => $preset,
            'custom_participant_id' => 'dn-' . $user_id . '-' . $match_id,
        ]);
        if (is_wp_error($response) || empty($response['data']['token'])) { return new WP_Error('participant'); }
        if (!empty($response['data']['id'])) { update_user_meta($user_id, $meta_key, (string)$response['data']['id']); }
        return (string)$response['data']['token'];
    }

    private static function api(string $method, string $path, array $body = [])
    {
        $account = rawurlencode((string)get_option('dn_cf_account'));
        $app = rawurlencode((string)get_option('dn_cf_app'));
        $url = 'https://api.cloudflare.com/client/v4/accounts/' . $account . '/realtime/kit/' . $app . '/' . $path;
        $args = [
            'method' => $method,
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . (string)get_option('dn_cf_token'),
                'Content-Type' => 'application/json',
            ],
        ];
        if ($method !== 'GET') { $args['body'] = wp_json_encode($body); }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) { return $response; }
        $json = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($json) && !empty($json['success']) ? $json : new WP_Error('cloudflare');
    }

    public static function admin_menu(): void
    {
        add_submenu_page('dating-network', 'Videobellen', 'Videobellen', 'manage_options', 'dating-network-video', [self::class, 'admin_page']);
    }

    public static function save_settings(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('dn_video_save')) { wp_die('Geen toegang'); }
        update_option('dn_cf_account', sanitize_text_field(wp_unslash($_POST['account'] ?? '')));
        update_option('dn_cf_app', sanitize_text_field(wp_unslash($_POST['app'] ?? '')));
        update_option('dn_cf_token', sanitize_text_field(wp_unslash($_POST['token'] ?? '')));
        update_option('dn_cf_preset', sanitize_text_field(wp_unslash($_POST['preset'] ?? '')));
        wp_safe_redirect(admin_url('admin.php?page=dating-network-video&saved=1'));
        exit;
    }

    public static function admin_page(): void
    {
        if (!current_user_can('manage_options')) { return; }
        ?>
        <div class="wrap">
            <h1>Dating Network · Videobellen</h1>
            <p>Cloudflare RealtimeKit-koppeling. Het API-token blijft op de server; leden krijgen alleen een tijdelijk deelnemertoken voor hun eigen match.</p>
            <p><strong>De videobelknop verschijnt pas nadat alle vier velden zijn ingevuld.</strong> Gebruik een aparte GROUP_CALL-preset waarin ingebouwde chat, recording, livestreaming en screensharing zijn uitgeschakeld. Zo blijven contact en moderatie bij Dating Network.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dn_video_save'); ?>
                <input type="hidden" name="action" value="dn_video_save">
                <table class="form-table">
                    <tr><th>Cloudflare Account ID</th><td><input class="regular-text" name="account" value="<?php echo esc_attr((string)get_option('dn_cf_account')); ?>"></td></tr>
                    <tr><th>RealtimeKit App ID</th><td><input class="regular-text" name="app" value="<?php echo esc_attr((string)get_option('dn_cf_app')); ?>"></td></tr>
                    <tr><th>API-token</th><td><input class="regular-text" type="password" name="token" value="<?php echo esc_attr((string)get_option('dn_cf_token')); ?>" autocomplete="off"></td></tr>
                    <tr><th>Veilige GROUP_CALL-preset</th><td><input class="regular-text" name="preset" value="<?php echo esc_attr((string)get_option('dn_cf_preset', '')); ?>"><p class="description">Vul hier pas de presetnaam in nadat chat, recording, livestreaming en screensharing voor die preset zijn uitgezet.</p></td></tr>
                </table>
                <?php submit_button('Opslaan'); ?>
            </form>
        </div>
        <?php
    }
}
