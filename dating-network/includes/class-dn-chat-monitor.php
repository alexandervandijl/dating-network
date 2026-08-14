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

            <div style="max-width:980px;margin:16px 0;padding:14px 16px;background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1">
                <strong>Interne aandachtssignalen</strong>
                <p style="margin-bottom:8px">De kleuren helpen alleen bij prioriteren. Ze zijn niet openbaar, veranderen de matchscore niet en nemen geen automatische sanctiebeslissingen.</p>
                <div style="display:flex;gap:16px;flex-wrap:wrap">
                    <?php self::legend_badge('green', 'Groen: geen actuele aandachtssignalen'); ?>
                    <?php self::legend_badge('orange', 'Oranje: bekijken wanneer mogelijk'); ?>
                    <?php self::legend_badge('red', 'Rood: prioriteit voor beheer'); ?>
                </div>
                <p class="description" style="margin-top:8px">Groen betekent niet dat een gebruiker “geverifieerd veilig” is; alleen dat er op dit moment geen relevante interne signalen zijn.</p>
            </div>

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

        $filter = sanitize_key(wp_unslash($_GET['risk'] ?? 'all'));
        if (!in_array($filter, ['all','red','orange','green'], true)) { $filter = 'all'; }

        foreach ($rows as $row) {
            $row->dn_left_risk = DN_Risk::summary((int)$row->user_low);
            $row->dn_right_risk = DN_Risk::summary((int)$row->user_high);
            $left_rank = DN_Risk::rank((string)$row->dn_left_risk['level']);
            $right_rank = DN_Risk::rank((string)$row->dn_right_risk['level']);
            $row->dn_risk = $left_rank >= $right_rank ? $row->dn_left_risk : $row->dn_right_risk;
            $row->dn_risk_rank = max($left_rank, $right_rank);
            $row->dn_risk_score = max((int)$row->dn_left_risk['score'], (int)$row->dn_right_risk['score']);
            $row->dn_activity = strtotime((string)($row->last_message ?: $row->created_at)) ?: 0;
        }

        usort($rows, static function($a, $b): int {
            if ((int)$a->dn_risk_rank !== (int)$b->dn_risk_rank) {
                return (int)$b->dn_risk_rank <=> (int)$a->dn_risk_rank;
            }
            if ((int)$a->dn_risk_score !== (int)$b->dn_risk_score) {
                return (int)$b->dn_risk_score <=> (int)$a->dn_risk_score;
            }
            return (int)$b->dn_activity <=> (int)$a->dn_activity;
        });

        $counts = ['red'=>0,'orange'=>0,'green'=>0];
        foreach ($rows as $row) { $counts[(string)$row->dn_risk['level']]++; }

        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 18px">';
        self::filter_button('all', 'Alles (' . count($rows) . ')', $filter);
        self::filter_button('red', '🔴 Prioriteit (' . $counts['red'] . ')', $filter);
        self::filter_button('orange', '🟠 Aandacht (' . $counts['orange'] . ')', $filter);
        self::filter_button('green', '🟢 Rustig (' . $counts['green'] . ')', $filter);
        echo '</div>';

        echo '<table class="widefat striped"><thead><tr><th>Prioriteit</th><th>Deelnemers</th><th>Status</th><th>Berichten</th><th>Laatste activiteit</th><th>Laatste bericht</th><th></th></tr></thead><tbody>';
        $visible = 0;
        foreach ($rows as $row) {
            $level = (string)$row->dn_risk['level'];
            if ($filter !== 'all' && $filter !== $level) { continue; }
            $visible++;

            $left = get_userdata((int)$row->user_low);
            $right = get_userdata((int)$row->user_high);
            $left_name = $left ? $left->display_name : 'Gebruiker #' . (int)$row->user_low;
            $right_name = $right ? $right->display_name : 'Gebruiker #' . (int)$row->user_high;
            $url = admin_url('admin.php?page=dating-network-chats&match=' . (int)$row->id);
            $left_trust = admin_url('admin.php?page=dating-network-trust&user=' . (int)$row->user_low);
            $right_trust = admin_url('admin.php?page=dating-network-trust&user=' . (int)$row->user_high);
            $preview = trim(wp_strip_all_tags((string)$row->last_body));
            if (function_exists('mb_strlen') && mb_strlen($preview) > 90) {
                $preview = mb_substr($preview, 0, 90) . '…';
            } elseif (strlen($preview) > 90) {
                $preview = substr($preview, 0, 90) . '…';
            }
            $colors = DN_Risk::colors($level);
            echo '<tr style="box-shadow:inset 4px 0 0 ' . esc_attr($colors[0]) . '">';
            echo '<td>' . self::risk_badge((array)$row->dn_risk, true) . '</td>';
            echo '<td><a href="' . esc_url($left_trust) . '"><strong>' . esc_html($left_name) . '</strong></a> ' . self::risk_badge((array)$row->dn_left_risk) . '<br>↔<br><a href="' . esc_url($right_trust) . '"><strong>' . esc_html($right_name) . '</strong></a> ' . self::risk_badge((array)$row->dn_right_risk) . '<br><small>Match #' . (int)$row->id . '</small></td>';
            echo '<td>' . esc_html((string)$row->status) . '</td>';
            echo '<td>' . (int)$row->message_count . '</td>';
            echo '<td>' . esc_html((string)($row->last_message ?: $row->created_at)) . '</td>';
            echo '<td>' . esc_html($preview !== '' ? $preview : '— nog geen berichten —') . '</td>';
            echo '<td><a class="button" href="' . esc_url($url) . '">Open chat</a></td>';
            echo '</tr>';
        }
        if ($visible === 0) {
            echo '<tr><td colspan="7">Geen gesprekken in deze categorie.</td></tr>';
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

        $left_id = (int)$row->user_low;
        $right_id = (int)$row->user_high;
        $left = get_userdata($left_id);
        $right = get_userdata($right_id);
        $left_name = $left ? $left->display_name : 'Gebruiker #' . $left_id;
        $right_name = $right ? $right->display_name : 'Gebruiker #' . $right_id;
        $left_risk = DN_Risk::summary($left_id);
        $right_risk = DN_Risk::summary($right_id);
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$messages} WHERE match_id=%d ORDER BY id ASC", $match_id));

        echo '<h2>' . esc_html($left_name) . ' ↔ ' . esc_html($right_name) . '</h2>';
        echo '<p><strong>Match #' . (int)$match_id . '</strong> · status: ' . esc_html((string)$row->status) . ' · gestart: ' . esc_html((string)$row->created_at) . '</p>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;max-width:920px;margin:18px 0">';
        self::risk_card($left_id, $left_name, $left_risk);
        self::risk_card($right_id, $right_name, $right_risk);
        echo '</div>';

        if (!$items) {
            echo '<p>Nog geen berichten in deze match.</p>';
            return;
        }

        echo '<div style="max-width:920px;display:grid;gap:10px;margin-top:18px">';
        foreach ($items as $item) {
            $sender = get_userdata((int)$item->sender_id);
            $sender_name = $sender ? $sender->display_name : 'Gebruiker #' . (int)$item->sender_id;
            $sender_risk = (int)$item->sender_id === $left_id ? $left_risk : $right_risk;
            $colors = DN_Risk::colors((string)$sender_risk['level']);
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid ' . esc_attr($colors[0]) . ';border-radius:10px;padding:12px 14px">';
            echo '<div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:7px"><div><strong>' . esc_html($sender_name) . '</strong> ' . self::risk_badge($sender_risk) . '</div><small>' . esc_html((string)$item->created_at) . '</small></div>';
            echo '<div style="white-space:pre-wrap;line-height:1.5">' . nl2br(esc_html((string)$item->body)) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    private static function risk_card(int $user_id, string $name, array $risk): void
    {
        $colors = DN_Risk::colors((string)$risk['level']);
        $trust_url = admin_url('admin.php?page=dating-network-trust&user=' . $user_id);
        echo '<div style="background:' . esc_attr($colors[1]) . ';border:1px solid ' . esc_attr($colors[2]) . ';border-radius:10px;padding:14px 16px">';
        echo '<div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><strong>' . esc_html($name) . '</strong>' . self::risk_badge($risk, true) . '</div>';
        echo '<ul style="margin:10px 0 10px 18px">';
        foreach ((array)$risk['reasons'] as $reason) { echo '<li>' . esc_html((string)$reason) . '</li>'; }
        echo '</ul><a class="button button-small" href="' . esc_url($trust_url) . '">Open vertrouwensdossier</a>';
        echo '</div>';
    }

    private static function risk_badge(array $risk, bool $show_score=false): string
    {
        $level = (string)($risk['level'] ?? 'orange');
        $colors = DN_Risk::colors($level);
        $label = (string)($risk['label'] ?? DN_Risk::label($level));
        if ($show_score) { $label .= ' · ' . (int)($risk['score'] ?? 0); }
        return '<span title="Interne prioriteitsindicatie; geen publieke score" style="display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;color:' . esc_attr($colors[0]) . ';background:' . esc_attr($colors[1]) . ';border:1px solid ' . esc_attr($colors[2]) . '">' . esc_html($label) . '</span>';
    }

    private static function legend_badge(string $level, string $text): void
    {
        $colors = DN_Risk::colors($level);
        echo '<span style="padding:5px 9px;border-radius:999px;font-weight:600;color:' . esc_attr($colors[0]) . ';background:' . esc_attr($colors[1]) . ';border:1px solid ' . esc_attr($colors[2]) . '">' . esc_html($text) . '</span>';
    }

    private static function filter_button(string $value, string $label, string $current): void
    {
        $url = admin_url('admin.php?page=dating-network-chats&risk=' . rawurlencode($value));
        $class = $current === $value ? 'button button-primary' : 'button';
        echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
}
