<?php
if (!defined('ABSPATH')) { exit; }

class DN_Risk
{
    private static array $cache = [];

    public static function summary(int $user_id): array
    {
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return self::result('orange', 20, ['Gebruiker bestaat niet meer of kan niet worden geladen.'], 0, 0, 0, 0, 0, 'onbekend');
        }
        if (isset(self::$cache[$user_id])) { return self::$cache[$user_id]; }

        global $wpdb;
        $events = $wpdb->prefix . 'dn_reputation_events';
        $reports = $wpdb->prefix . 'dn_reports';
        $blocks = $wpdb->prefix . 'dn_blocks';

        $moderation = (string)get_user_meta($user_id, 'dn_admin_moderation_status', true) ?: 'normaal';
        $positive = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$events} WHERE user_id=%d AND polarity='positive'",
            $user_id
        ));
        $admin_negative = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$events} WHERE user_id=%d AND polarity='negative' AND category NOT LIKE 'user_report_%%'",
            $user_id
        ));
        $warnings = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$events} WHERE user_id=%d AND category='warning'",
            $user_id
        ));
        $open_reports = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$reports} WHERE reported_id=%d AND status='open'",
            $user_id
        ));
        $severe_open = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$reports} WHERE reported_id=%d AND status='open' AND reason IN ('harassment','pressure','sexual','abuse','scam','discrimination','safety','not_single')",
            $user_id
        ));
        $blocks_received = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$blocks} WHERE blocked_id=%d",
            $user_id
        ));

        $score = 0;
        $reasons = [];
        $hard_red = false;

        if ($moderation === 'blocked') {
            $score = 100;
            $hard_red = true;
            $reasons[] = 'Volledig geblokkeerd door beheer.';
        } elseif ($moderation === 'paused') {
            $score = 80;
            $hard_red = true;
            $reasons[] = 'Profiel gepauzeerd door beheer.';
        }

        if ($open_reports > 0) {
            $score += min(40, $open_reports * 15);
            $reasons[] = $open_reports . ' open gebruikersmelding' . ($open_reports === 1 ? '' : 'en') . '.';
        }
        if ($severe_open > 0) {
            $score += min(40, $severe_open * 20);
            $reasons[] = $severe_open . ' open melding' . ($severe_open === 1 ? '' : 'en') . ' met verhoogde veiligheidsprioriteit.';
        }
        if ($admin_negative > 0) {
            $score += min(30, $admin_negative * 12);
            $reasons[] = $admin_negative . ' negatief beheersignaal' . ($admin_negative === 1 ? '' : 'en') . '.';
        }
        if ($warnings > 0) {
            $score += min(20, $warnings * 10);
            $reasons[] = $warnings . ' geregistreerde waarschuwing' . ($warnings === 1 ? '' : 'en') . '.';
        }
        if ($blocks_received > 0) {
            $score += min(15, $blocks_received * 4);
            $reasons[] = $blocks_received . ' keer door een andere gebruiker geblokkeerd.';
        }
        if ($positive > 0) {
            $score -= min(20, $positive * 4);
            $reasons[] = $positive . ' positieve waardering' . ($positive === 1 ? '' : 'en') . '.';
        }

        $score = max(0, min(100, $score));

        if ($hard_red || $score >= 50 || $severe_open >= 2) {
            $level = 'red';
        } elseif ($score >= 15 || $open_reports > 0 || $admin_negative > 0 || $warnings > 0) {
            $level = 'orange';
        } else {
            $level = 'green';
        }

        if (!$reasons) {
            $reasons[] = 'Geen actuele interne aandachtssignalen.';
        }

        return self::$cache[$user_id] = self::result(
            $level,
            $score,
            array_slice($reasons, 0, 5),
            $positive,
            $admin_negative,
            $open_reports,
            $severe_open,
            $blocks_received,
            $moderation
        );
    }

    public static function rank(string $level): int
    {
        return $level === 'red' ? 3 : ($level === 'orange' ? 2 : 1);
    }

    public static function label(string $level): string
    {
        return $level === 'red' ? 'Prioriteit' : ($level === 'orange' ? 'Aandacht' : 'Rustig');
    }

    public static function colors(string $level): array
    {
        if ($level === 'red') { return ['#b42318', '#fef3f2', '#fecdca']; }
        if ($level === 'orange') { return ['#b54708', '#fffaeb', '#fedf89']; }
        return ['#137333', '#ecfdf3', '#abefc6'];
    }

    private static function result(string $level, int $score, array $reasons, int $positive, int $admin_negative, int $open_reports, int $severe_open, int $blocks_received, string $moderation): array
    {
        return [
            'level' => $level,
            'label' => self::label($level),
            'score' => $score,
            'reasons' => $reasons,
            'positive' => $positive,
            'admin_negative' => $admin_negative,
            'open_reports' => $open_reports,
            'severe_open' => $severe_open,
            'blocks_received' => $blocks_received,
            'moderation' => $moderation,
        ];
    }
}
