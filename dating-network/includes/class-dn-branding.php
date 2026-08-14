<?php
if (!defined('ABSPATH')) { exit; }

class DN_Branding
{
    public static function init(): void
    {
        add_action('wp_head', [self::class, 'head_meta'], 2);
        add_filter('do_shortcode_tag', [self::class, 'brand_shortcode'], 45, 4);
        add_filter('document_title_parts', [self::class, 'title_parts']);
    }

    public static function head_meta(): void
    {
        $key = DN_Core::current_page_key();
        if ($key === '') { return; }

        $icon = DN_URL . 'assets/dating-network-icon.svg';
        echo '<link rel="icon" href="' . esc_url($icon) . '" type="image/svg+xml">' . "\n";
        echo '<meta name="theme-color" content="#BE1D4E">' . "\n";

        if ($key === 'home') {
            $title = 'Dating Network — gratis daten, en dat blijft zo';
            $description = 'Dating Network is gratis en blijft gratis. Geen betaalde boosts, geen premium matches en geen betaalmuur tussen jou en een echte connectie.';
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            echo '<meta property="og:type" content="website">' . "\n";
            echo '<meta property="og:site_name" content="Dating Network">' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
            echo '<meta property="og:url" content="' . esc_url(home_url('/')) . '">' . "\n";
        }
    }

    public static function title_parts(array $parts): array
    {
        if (DN_Core::current_page_key() === 'home') {
            $parts['title'] = 'Dating Network — gratis daten, en dat blijft zo';
            unset($parts['tagline']);
        }
        return $parts;
    }

    public static function brand_shortcode(string $output, string $tag, array $attr, array $match): string
    {
        if ($tag === 'dating_network_home') {
            return self::home($output);
        }

        if ($tag === 'dating_network_register') {
            $output = str_replace(
                'Gratis matching. Geen boosts. Alleen singles die vrij zijn voor een nieuwe relatie.',
                'Dating Network is gratis en blijft gratis. Geen boosts, geen premium matches en geen betaalmuur tussen jou en een echte connectie.',
                $output
            );
        }

        if ($tag === 'dating_network_dashboard') {
            $promise = '<div class="dn-free-inline"><strong>♥ Gratis. En dat blijft zo.</strong><span>Profiel, matching, matches en interne chat blijven kosteloos.</span></div>';
            $needle = '<div class="dn-wrap">';
            if (str_contains($output, $needle)) {
                $output = str_replace($needle, $needle . $promise, $output, $count);
            }
        }

        return $output;
    }

    private static function home(string $output): string
    {
        $replacements = [
            '♥</span> Dating Network' => '♥</span> Dating <b>Network</b>',
            'VOOR SINGLES · MAN ↔ VROUW · 18+' => 'GRATIS EN BLIJFT GRATIS · VOOR SINGLES · 18+',
            'Vind iemand met wie je <em>écht</em> verder wilt.' => 'Echte mensen.<br><em>Echte connecties.</em>',
            'Geen eindeloos swipen. Geen betaalmuur tussen jou en een match. We kijken naar wat voor jullie allebei belangrijk is — en helpen je richting een echte ontmoeting.' => 'Dating Network is gratis en blijft gratis. Geen premium matches, geen betaalde zichtbaarheid en geen trucjes om je langer te laten swipen. Gewoon een eerlijke kans om iemand te vinden met wie je écht verder wilt.',
            '✓ Matching gratis' => '✓ Gratis en blijft gratis',
            '<strong>0</strong><span>betaalde boosts</span>' => '<strong>€0</strong><span>voor matching en chat</span>',
            '<strong>0</strong><span>matches achter paywall</span>' => '<strong>Altijd</strong><span>gratis toegang tot matches</span>',
            '<strong>0</strong><span>promotieprofielen</span>' => '<strong>0</strong><span>betaalde voorrang</span>',
            'ALS HET EVEN NIET LUKT' => 'GRATIS BETEKENT OOK ECHT GRATIS',
            'Geen succes? Dan helpen we je — we verstoppen je niet.' => 'Je hoeft nooit te betalen om gezien te worden.',
            'Datingcoaching wordt de betaalde hulp voor wie wil verbeteren. Betalen geeft geen betere zichtbaarheid of voorrang in matching.' => 'Het datingplatform zelf blijft gratis: profiel, matching, matches en interne chat. Eventuele losse coaching kan later optioneel worden aangeboden, maar betalen geeft nooit extra zichtbaarheid, betere matches of voorrang.',
            '>Begin gratis<' => '>Maak gratis je profiel<',
            'Maak je profiel. Ontmoet iemand.<br><em>Verwijder ons met plezier.</em>' => 'Gratis aanmelden. Gratis matchen.<br><em>En hopelijk snel weer weg.</em>',
            'Gemaakt om je te helpen stoppen met daten.' => 'Echte mensen. Echte connecties. Gratis en blijvend gratis.',
        ];

        $output = str_replace(array_keys($replacements), array_values($replacements), $output);

        $promise = '<section class="dn-free-promise"><div class="dn-free-promise-mark">♥</div><div><span>ONZE BELOFTE</span><h2>Gratis. En dat blijft zo.</h2><p>Een profiel maken, passende singles ontdekken, matchen en intern chatten kost niets — nu niet en later niet. Er komt geen premiumlaag die betere mensen, extra zichtbaarheid of meer kans op een match vrijspeelt.</p></div><div class="dn-free-price"><strong>€0</strong><small>datingkosten</small></div></section>';
        $needle = '<section class="dn-values">';
        if (str_contains($output, $needle)) {
            $output = str_replace($needle, $promise . $needle, $output);
        }

        return $output;
    }
}
