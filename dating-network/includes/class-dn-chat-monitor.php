<?php
if (!defined('ABSPATH')) { exit; }

class DN_Chat_Monitor
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'settings']);
        add_filter('do_shortcode_tag', [self::class, 'chat_disclosure'], 30, 4);

        if (get_option('dn_chat_monitoring_enabled', null) === null) {
            add_option('dn_chat_monitoring_enabled', '1', '', false);
        }
    }

    public static function settings(): void
    {
        register_setting('dn_chat_monitoring', 'dn_chat_monitoring_enabled', [
            'type' => 'string',
            'sanitize_callback' => static fn($value): string => (string)$value === '1' ? '1' : '0',
            'default' => '1',
        ]);
    }

    public static function menu(): void
    {
        add_submenu_page(
            'dating-network',
            'Chatmonitor',
            'Chatmonitor',
            'manage_options',
            'dating-network-chats',
            [self::class, 'page']
        );
    }

    public static function chat_disclosure(string $output, string $tag, array $attr, array $match): string
    {
        if ($tag !== 'dating_network_chat' || get_option('dn_chat_monitoring_enabled', '1') !== '1') {
            return $output;
        }

        $notice = '<div class="dn-wrap"><div class="dn-notice dn-notice-info"><strong>Veiligheidsmelding:</strong> gesprekken zijn niet openbaar en alleen zichtbaar voor jou en je match. In de opstartfase kunnen bevoegde Dating Network-beheerders chats controleren voor veiligheid, misbruikpreventie en kwaliteitscontrole.</div></div>';
        return $notice . $output;
    }

    public static function page(): void
    {
        if (!current_user_can('manage_options')) { return; }

        $match_id = isset($_GET['match']) ? absint($_GET['match']) : 0;
        ?>
        <div class="wrap">
            <h1>Dating Network · Chatmonitor</h1>
            <p>Alleen beheerders met <code>manage_options</code> hebben toegang. Gebruik deze inhoud uitsluitend voor veiligheid, misbruikpreventie en kwaliteitscontrole.</p>

            <form method="post" action="options.php" style="margin:18px 0 26px;padding:16px;background:#fff;border:1px solid #dcdcde;max-width:760px">
                <?php settings_fields('dn_chat_monitoring'); ?>
                <input type="hidden" name="dn_chat_monitoring_enabled" value="0">
                <label>
                    <input type="checkbox" name="dn_chat_monitoring_enabled" value="1" <?php checked(get_option('dn_chat_monitoring_enabled', '1'), '1'); ?>>
                    <strong>Chatmonitoring in de opstartfase inschakelen</strong>
                </label>
                <p class="description">Als dit uitstaat, verdwijnt de gebruikersmelding. De bestaande chatberichten blijven volgens de normale platformopslag bestaan.</p>
                <?php submit_button('Instelling opslaan', 'secondary', 'submit', false); ?>
            </form>

            <?php
            if ($match_id) {
                self::conversation($match_id);
            } else {
                self::conversation_list();
            }
            ?>
        </div>
        <?php
    }

    private static function conversation_list(): void
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'dn_matches';
        $messages = $wpdb->prefix . 'dn_messages';

        $rows = $wpdb->get_results(
            "SELECT m.id, m.user_low, m.user_high, m.status, m.created_at,
                    COUNT(msg.id) AS message_count,
                    MAX(msg.created_at) AS last_message,
                    (SELECT x.body FROM {$messages} x WHERE x.match_id=m.id ORDER BY x.id DESC LIMIT 1) AS last_body
             FROM {$matches} m
             LEFT JOIN {$messages} msg ON msg.match_id=m.id
             GROUP BY m.id, m.user_low, m.user_high, m.status, m.created_at
             ORDER BY COALESCE(MAX(msg.created_at), m.created_at) DESC
             LIMIT 200"
        );

        echo '<h2>Gesprekken</h2>';
        if (!$rows) {
            echo '<p>Nog geen matches of chats.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>Deelnemers</th><th>Status</th><th>Berichten</th><th>Laatste activiteit</th><th>Laatste bericht</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $left = get_userdata((int)$row->user_low);
            $right = get_userdata((int)$row->user_high);
            $left_name = $left ? $left->display_name : 'Gebruiker #' . (int)$row->user_low;
            $right_name = $right ? $right->display_name : 'Gebruiker #' . (int)$row->user_high;
            $url = admin_url('admin.php?page=dating-network-chats&match=' . (int)$row->id);
            $preview = trim(wp_strip_all_tags((string)$row->last_body));
            if (function_exists('mb_strlen') && mb_strlen($preview) > 90) {
                $preview = mb_substr($preview, 0, 90) . '…';
            } elseif (strlen($preview) > 90) {
                $preview = substr($preview, 0, 90) . '…';
            }
            echo '<tr>';
            echo '<td><strong>' . esc_html($left_name) . '</strong> ↔ <strong>' . esc_html($right_name) . '</strong><br><small>Match #' . (int)$row->id . '</small></td>';
            echo '<td>' . esc_html((string)$row->status) . '</td>';
            echo '<td>' . (int)$row->message_count . '</td>';
            echo '<td>' . esc_html((string)($row->last_message ?: $row->created_at)) . '</td>';
            echo '<td>' . esc_html($preview !== '' ? $preview : '— nog geen berichten —') . '</td>';
            echo '<td><a class="button" href="' . esc_url($url) . '">Open chat</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function conversation(int $match_id): void
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'dn_matches';
        $messages = $wpdb->prefix . 'dn_messages';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$matches} WHERE id=%d LIMIT 1", $match_id));

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=dating-network-chats')) . '">← Terug naar alle gesprekken</a></p>';
        if (!$row) {
            echo '<div class="notice notice-error"><p>Deze match bestaat niet.</p></div>';
            return;
        }

        $left = get_userdata((int)$row->user_low);
        $right = get_userdata((int)$row->user_high);
        $left_name = $left ? $left->display_name : 'Gebruiker #' . (int)$row->user_low;
        $right_name = $right ? $right->display_name : 'Gebruiker #' . (int)$row->user_high;
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$messages} WHERE match_id=%d ORDER BY id ASC", $match_id));

        echo '<h2>' . esc_html($left_name) . ' ↔ ' . esc_html($right_name) . '</h2>';
        echo '<p><strong>Match #' . (int)$match_id . '</strong> · status: ' . esc_html((string)$row->status) . ' · gestart: ' . esc_html((string)$row->created_at) . '</p>';

        if (!$items) {
            echo '<p>Nog geen berichten in deze match.</p>';
            return;
        }

        echo '<div style="max-width:920px;display:grid;gap:10px;margin-top:18px">';
        foreach ($items as $item) {
            $sender = get_userdata((int)$item->sender_id);
            $sender_name = $sender ? $sender->display_name : 'Gebruiker #' . (int)$item->sender_id;
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:12px 14px">';
            echo '<div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:7px"><strong>' . esc_html($sender_name) . '</strong><small>' . esc_html((string)$item->created_at) . '</small></div>';
            echo '<div style="white-space:pre-wrap;line-height:1.5">' . nl2br(esc_html((string)$item->body)) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
}
