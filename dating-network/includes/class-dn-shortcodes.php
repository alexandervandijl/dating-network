<?php
if (!defined('ABSPATH')) { exit; }

class DN_Shortcodes
{
    public static function init(): void
    {
        $map=['dating_network_home'=>'home','dating_network_register'=>'register','dating_network_login'=>'login','dating_network_verify'=>'verify','dating_network_dashboard'=>'dashboard','dating_network_profile'=>'profile','dating_network_discover'=>'discover','dating_network_matches'=>'matches','dating_network_chat'=>'chat'];
        foreach($map as $tag=>$method){add_shortcode($tag,[self::class,$method]);}
    }

    public static function home(): string
    {
        $register=DN_Core::page_url('register');$login=DN_Core::page_url('login');
        ob_start(); ?>
        <div class="dn-home">
            <header class="dn-home-nav"><div class="dn-home-nav-inner"><a class="dn-brand" href="<?php echo esc_url(home_url('/')); ?>"><span>♥</span> Dating Network</a><nav><a href="#hoe-het-werkt">Hoe het werkt</a><a href="#veiligheid">Veiligheid</a><a href="<?php echo esc_url($login); ?>">Inloggen</a><a class="dn-nav-button" href="<?php echo esc_url($register); ?>">Gratis aanmelden</a></nav></div></header>
            <section class="dn-hero"><div class="dn-hero-inner"><div><div class="dn-kicker">VOOR SINGLES · MAN ↔ VROUW · 18+</div><h1>Vind iemand met wie je <em>écht</em> verder wilt.</h1><p>Geen eindeloos swipen. Geen betaalmuur tussen jou en een match. We kijken naar wat voor jullie allebei belangrijk is — en helpen je richting een echte ontmoeting.</p><div class="dn-hero-actions"><a class="dn-home-button" href="<?php echo esc_url($register); ?>">Maak gratis je profiel →</a><a href="#hoe-het-werkt">Bekijk hoe matching werkt</a></div><div class="dn-home-trust"><span>✓ Alleen singles</span><span>✓ Matching gratis</span><span>✓ Contact blijft intern</span></div></div><div class="dn-match-demo"><div class="dn-demo-top"><span class="dn-demo-avatar">♥</span><div><strong>Een passende match</strong><small>Niet gekocht. Niet geboost.</small></div><b>89%</b></div><ul><li>✓ Relatiedoel sluit aan</li><li>✓ Kinderwens past</li><li>✓ Religie past bij voorkeuren</li><li>✓ Gedeelde interesses</li></ul><p>Je ziet waarom iemand bij je past. Geen geheim algoritme dat vooral wil dat je blijft swipen.</p></div></div></section>
            <section class="dn-values"><div><strong>0</strong><span>betaalde boosts</span></div><div><strong>0</strong><span>matches achter paywall</span></div><div><strong>0</strong><span>promotieprofielen</span></div><div><strong>1</strong><span>doel: iemand vinden</span></div></section>
            <section id="hoe-het-werkt" class="dn-home-section"><div class="dn-section-head"><span>HOE HET WERKT</span><h2>Van profiel naar een echte match.</h2></div><div class="dn-steps"><article><b>01</b><h3>Vertel wie je bent</h3><p>Vul je profiel, voorkeuren, religie, kinderwens, leefstijl en interesses in.</p></article><article><b>02</b><h3>Ontdek passende singles</h3><p>We tonen kandidaten die wederzijds binnen de belangrijkste voorkeuren passen.</p></article><article><b>03</b><h3>Alleen chat bij een match</h3><p>Pas bij wederzijdse interesse opent de interne chat.</p></article></div></section>
            <section id="veiligheid" class="dn-safety-home"><div><span>VEILIGHEID IS GEEN EXTRA</span><h2>Jouw datingleven is geen advertentieruimte.</h2><p>Geen telefoonnummers, e-mails, socials, externe links of OnlyFans-achtige promotie. Contact blijft binnen het platform zodat blokkeren en rapporteren ook echt iets betekenen.</p></div><div class="dn-safety-list"><span>✓ Alleen 18+ singles</span><span>✓ Blokkeren en rapporteren</span><span>✓ Geen externe contactgegevens</span><span>✓ “Niet single” als rapportreden</span></div></section>
            <section class="dn-coaching"><div><span>ALS HET EVEN NIET LUKT</span><h2>Geen succes? Dan helpen we je — we verstoppen je niet.</h2><p>Datingcoaching wordt de betaalde hulp voor wie wil verbeteren. Betalen geeft geen betere zichtbaarheid of voorrang in matching.</p></div><a class="dn-home-button" href="<?php echo esc_url($register); ?>">Begin gratis</a></section>
            <section class="dn-final"><h2>Maak je profiel. Ontmoet iemand.<br><em>Verwijder ons met plezier.</em></h2><a class="dn-home-button" href="<?php echo esc_url($register); ?>">Gratis aanmelden →</a></section>
            <footer class="dn-home-footer"><strong>Dating Network</strong><span>Gemaakt om je te helpen stoppen met daten.</span></footer>
        </div>
        <?php return (string)ob_get_clean();
    }

    public static function register(): string
    {
        if(is_user_logged_in()){return self::notice('Je bent al ingelogd.','success').self::button('Naar mijn account',DN_Core::page_url('dashboard'));}
        $message=self::query_message();
        ob_start(); ?>
        <div class="dn-wrap dn-auth-wrap"><div class="dn-page-hero dn-page-hero-compact"><span class="dn-page-kicker">START HIER</span><h1>Maak je profiel.</h1><p>Gratis matching. Geen boosts. Alleen singles die vrij zijn voor een nieuwe relatie.</p></div><?php echo $message; // phpcs:ignore ?>
        <form class="dn-card dn-form dn-funnel" method="post" data-dn-funnel>
            <?php echo DN_Core::nonce('register'); // phpcs:ignore ?> <input type="hidden" name="dn_action" value="register">
            <div class="dn-progress"><span class="is-active">1</span><i></i><span>2</span><i></i><span>3</span></div>
            <section class="dn-funnel-step is-active"><h2>Over jou</h2><div class="dn-grid-2"><label>Voornaam<input required name="display_name" autocomplete="given-name"></label><label>Ik ben<select required name="gender"><option value="">Kies…</option><option value="male">Man</option><option value="female">Vrouw</option></select></label><label>Geboortedatum<input required type="date" name="dob"></label></div><button type="button" class="dn-button dn-funnel-next">Verder →</button></section>
            <section class="dn-funnel-step"><h2>Je veilige account</h2><label>E-mailadres<input required type="email" name="email" autocomplete="email"></label><label>Wachtwoord<input required minlength="10" type="password" name="password" autocomplete="new-password"></label><div class="dn-form-actions"><button type="button" class="dn-button dn-button-ghost dn-funnel-back">← Terug</button><button type="button" class="dn-button dn-funnel-next">Verder →</button></div></section>
            <section class="dn-funnel-step"><h2>Onze afspraken</h2><label class="dn-check"><input required type="checkbox" name="single" value="1"><span>Ik bevestig dat ik 18 jaar of ouder ben, single ben en vrij ben om een nieuwe relatie aan te gaan.</span></label><label class="dn-check"><input required type="checkbox" name="consent" value="1"><span>Ik geef toestemming om mijn profiel- en matchgegevens te verwerken voor Dating Network.</span></label><p class="dn-muted">Geen affaires, open relaties, promotieprofielen of externe contactuitwisseling.</p><div class="dn-form-actions"><button type="button" class="dn-button dn-button-ghost dn-funnel-back">← Terug</button><button class="dn-button" type="submit">Account aanmaken →</button></div></section>
        </form></div>
        <?php return (string)ob_get_clean();
    }

    public static function login(): string
    {
        if(is_user_logged_in()){return self::notice('Je bent ingelogd.','success').self::button('Ontdek singles',DN_Core::page_url('discover'));}
        ob_start(); ?><div class="dn-wrap dn-auth-wrap"><div class="dn-page-hero dn-page-hero-compact"><span class="dn-page-kicker">WELKOM TERUG</span><h1>Inloggen</h1><p>Ga verder met je profiel, matches en gesprekken.</p></div><?php echo self::query_message(); // phpcs:ignore ?><form class="dn-card dn-form" method="post"><?php echo DN_Core::nonce('login'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="login"><label>E-mailadres<input required type="email" name="email" autocomplete="email"></label><label>Wachtwoord<input required type="password" name="password" autocomplete="current-password"></label><button class="dn-button">Inloggen</button></form></div><?php return (string)ob_get_clean();
    }

    public static function verify(): string
    {
        $token=sanitize_text_field(wp_unslash($_GET['token']??''));$uid=(int)($_GET['uid']??0);
        if($token&&$uid&&hash_equals((string)get_user_meta($uid,'dn_verify_token',true),$token)){
            update_user_meta($uid,'dn_email_verified','1');delete_user_meta($uid,'dn_verify_token');self::refresh_status($uid);
            return '<div class="dn-wrap dn-auth-wrap">'.self::notice('Je e-mailadres is bevestigd. Je kunt nu je profiel afmaken.','success').self::button('Profiel afmaken',DN_Core::page_url('profile')).'</div>';
        }
        return '<div class="dn-wrap dn-auth-wrap"><div class="dn-page-hero dn-page-hero-compact"><span class="dn-page-kicker">E-MAILCONTROLE</span><h1>Bevestig je account</h1><p>Gebruik de link uit je verificatiemail. Is die verlopen? Log in en stuur hem opnieuw.</p></div>'.self::button('Inloggen',DN_Core::page_url('login')).'</div>';
    }

    public static function dashboard(): string
    {
        if(!is_user_logged_in()){return self::login_required();}$id=get_current_user_id();$active=DN_Match::is_active($id);$verified=get_user_meta($id,'dn_email_verified',true)==='1';
        ob_start(); ?><div class="dn-wrap"><div class="dn-page-hero dn-page-hero-compact"><span class="dn-page-kicker">MIJN ACCOUNT</span><h1>Jouw Dating Network</h1><p><?php echo $active?'Je profiel doet mee aan matching.':'Maak je profiel compleet om passende singles te zien.'; ?></p></div><?php echo self::query_message(); // phpcs:ignore ?><div class="dn-dashboard-grid"><a class="dn-card dn-dashboard-card" href="<?php echo esc_url(DN_Core::page_url('discover')); ?>"><span>♥</span><h3>Ontdek singles</h3><p>Bekijk wederzijds passende kandidaten.</p></a><a class="dn-card dn-dashboard-card" href="<?php echo esc_url(DN_Core::page_url('matches')); ?>"><span>↔</span><h3>Mijn matches</h3><p>Ga naar je wederzijdse matches en chats.</p></a><a class="dn-card dn-dashboard-card" href="<?php echo esc_url(DN_Core::page_url('profile')); ?>"><span>☺</span><h3>Mijn profiel</h3><p>Werk je profiel, voorkeuren en interesses bij.</p></a></div><?php if(!$verified): ?><form method="post" class="dn-inline-form"><?php echo DN_Core::nonce('resend'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="resend"><button class="dn-button dn-button-ghost">Verificatiemail opnieuw sturen</button></form><?php endif; ?><form method="post" class="dn-card dn-success-card"><?php echo DN_Core::nonce('found'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="found"><h2>❤️ Iemand gevonden?</h2><p>Fantastisch. Hiermee halen we je profiel direct uit de matching.</p><button class="dn-button" onclick="return confirm('Je profiel uit matching halen omdat je iemand hebt gevonden?')">Ik heb iemand gevonden</button></form></div><?php return (string)ob_get_clean();
    }

    public static function profile(): string
    {
        if(!is_user_logged_in()){return self::login_required();}$id=get_current_user_id();$u=get_userdata($id);$interests=DN_Match::list_meta($id,'dn_interests');$favorites=DN_Match::list_meta($id,'dn_favorite_interests');
        $g=(string)get_user_meta($id,'dn_gender',true);$goal=(string)get_user_meta($id,'dn_relationship_goal',true);$children=(string)get_user_meta($id,'dn_children_status',true);$wish=(string)get_user_meta($id,'dn_children_wish',true);$religion=(string)get_user_meta($id,'dn_religion',true);
        ob_start(); ?><div class="dn-wrap"><div class="dn-page-hero dn-page-hero-compact"><span class="dn-page-kicker">JOUW BASIS</span><h1>Mijn profiel</h1><p>Hoe completer en eerlijker dit is, hoe beter we kunnen uitleggen waarom iemand bij je past.</p></div><?php echo self::query_message(); // phpcs:ignore ?>
        <form method="post" class="dn-card dn-form"><?php echo DN_Core::nonce('profile'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="profile_save"><div class="dn-grid-2"><label>Voornaam<input required name="display_name" value="<?php echo esc_attr($u?$u->display_name:''); ?>"></label><label>Ik ben<select required name="gender"><option value="male" <?php selected($g,'male'); ?>>Man</option><option value="female" <?php selected($g,'female'); ?>>Vrouw</option></select></label><label>Geboortedatum<input required type="date" name="dob" value="<?php echo esc_attr((string)get_user_meta($id,'dn_dob',true)); ?>"></label><label>Woonplaats<input required name="city" value="<?php echo esc_attr((string)get_user_meta($id,'dn_city',true)); ?>"></label><label>Land<input required name="country" value="<?php echo esc_attr((string)get_user_meta($id,'dn_country',true)?:'Nederland'); ?>"></label><label>Hoe lang single?<select name="single_since"><option value="lt3">Korter dan 3 maanden</option><option value="3_6">3–6 maanden</option><option value="6_12">6–12 maanden</option><option value="1_2">1–2 jaar</option><option value="2_5">2–5 jaar</option><option value="5plus">Meer dan 5 jaar</option><option value="prefer_not">Zeg ik liever niet</option></select></label></div>
        <label>Waar ben je naar op zoek?<select required name="relationship_goal"><option value="serious" <?php selected($goal,'serious'); ?>>Een serieuze relatie</option><option value="slow_serious" <?php selected($goal,'slow_serious'); ?>>Rustig daten met als doel een relatie</option><option value="see_where" <?php selected($goal,'see_where'); ?>>Iemand leren kennen en kijken waar het heen gaat</option></select></label>
        <div class="dn-grid-2"><label>Ik heb kinderen<select name="children_status"><option value="none" <?php selected($children,'none'); ?>>Nee</option><option value="has" <?php selected($children,'has'); ?>>Ja</option></select></label><label>Kinderwens<select name="children_wish"><option value="yes" <?php selected($wish,'yes'); ?>>Ja</option><option value="maybe" <?php selected($wish,'maybe'); ?>>Misschien</option><option value="no" <?php selected($wish,'no'); ?>>Nee</option></select></label><label>Partner met kinderen?<select name="open_partner_children"><option value="yes">Ja</option><option value="maybe">Misschien</option><option value="no">Nee</option></select></label><label>Roken<select name="smoking"><option value="no">Niet</option><option value="sometimes">Soms</option><option value="yes">Ja</option></select></label><label>Alcohol<select name="alcohol"><option value="no">Niet</option><option value="sometimes">Soms</option><option value="regular">Regelmatig</option></select></label></div>
        <h2>Religie / levensovertuiging</h2><div class="dn-grid-2"><label>Religie<input name="religion" value="<?php echo esc_attr($religion); ?>" placeholder="Bijv. christelijk, islam, Scientology, geen"></label><label>Hoe belangrijk?<select name="religion_importance"><option value="not">Niet belangrijk</option><option value="nice">Mooi meegenomen</option><option value="important">Belangrijk</option><option value="must">Moet passen</option></select></label><label>Open voor andere religie?<select name="open_other_religion"><option value="yes">Ja</option><option value="maybe">Hangt ervan af</option><option value="no">Nee</option></select></label></div><label class="dn-check"><input type="checkbox" name="religion_consent" value="1" <?php checked(get_user_meta($id,'dn_religion_consent',true),'1'); ?>><span>Ik geef expliciet toestemming om mijn religie/levensovertuiging voor mijn profiel en matching te verwerken.</span></label>
        <h2>Interesses</h2><label>0–100 interesses <small>(komma’s of nieuwe regels)</small><textarea name="interests" rows="4"><?php echo esc_textarea(implode(', ',$interests)); ?></textarea></label><label>Maximaal 5 favorieten<textarea name="favorite_interests" rows="2"><?php echo esc_textarea(implode(', ',$favorites)); ?></textarea></label><label>Over mij<textarea name="bio" rows="6" maxlength="1200"><?php echo esc_textarea((string)get_user_meta($id,'dn_bio',true)); ?></textarea></label><h2>Wie past bij jou?</h2><div class="dn-grid-2"><label>Minimumleeftijd<input type="number" min="18" max="99" name="age_min" value="<?php echo esc_attr((string)get_user_meta($id,'dn_age_min',true)?:'25'); ?>"></label><label>Maximumleeftijd<input type="number" min="18" max="99" name="age_max" value="<?php echo esc_attr((string)get_user_meta($id,'dn_age_max',true)?:'55'); ?>"></label></div><button class="dn-button">Profiel opslaan</button></form>
        <div class="dn-card dn-danger-zone"><h2>Privacy en account</h2><form method="post"><?php echo DN_Core::nonce('consent'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="revoke_consent"><button class="dn-button dn-button-ghost" onclick="return confirm('Je profiel pauzeren en toestemming intrekken?')">Toestemming intrekken en profiel pauzeren</button></form><form method="post"><?php echo DN_Core::nonce('delete'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="delete_account"><button class="dn-link-danger" onclick="return confirm('Account en persoonsgegevens definitief verwijderen? Dit kan niet ongedaan worden gemaakt.')">Account definitief verwijderen</button></form></div></div><?php return (string)ob_get_clean();
    }

    public static function discover(): string
    {
        if(!is_user_logged_in()){return self::login_required();}$id=get_current_user_id();
        if(!DN_Match::is_active($id)){return self::discover_onboarding($id);}
        $users=get_users(['number'=>100,'fields'=>'ID']);$cards=[];
        foreach($users as $candidate){$candidate=(int)$candidate;if(!DN_Match::eligible($id,$candidate)){continue;}$score=DN_Match::score($id,$candidate);$cards[]=[$candidate,$score];}
        usort($cards,static fn($a,$b)=>$b[1]['score']<=>$a[1]['score']);
        ob_start(); ?><div class="dn-wrap"><div class="dn-page-hero"><span class="dn-page-kicker">JOUW MATCHRONDE</span><h1>Ontdek singles</h1><p>Geen swipe-loterij. Alleen mensen die wederzijds binnen jullie basisvoorkeuren passen.</p></div><?php echo self::query_message(); // phpcs:ignore ?><div class="dn-candidate-grid"><?php if(!$cards): ?><div class="dn-card dn-empty"><h2>Nog geen passende kandidaten</h2><p>We tonen liever niemand dan iemand die niet binnen jullie wederzijdse basisvoorkeuren past. Zodra er een passende single bijkomt, kan die hier verschijnen.</p></div><?php endif; foreach(array_slice($cards,0,30) as [$candidate,$score]): $u=get_userdata($candidate); ?><article class="dn-card dn-candidate"><div class="dn-score-ring"><strong><?php echo (int)$score['score']; ?>%</strong><span>match</span></div><div><h2><?php echo esc_html($u?$u->display_name:'Single'); ?>, <?php echo (int)(DN_Match::age($candidate)?:0); ?></h2><p class="dn-muted"><?php echo esc_html((string)get_user_meta($candidate,'dn_city',true)); ?></p><p><?php echo esc_html(wp_trim_words((string)get_user_meta($candidate,'dn_bio',true),30)); ?></p><ul class="dn-reasons"><?php foreach($score['reasons'] as $reason): ?><li>✓ <?php echo esc_html($reason); ?></li><?php endforeach; ?></ul><form method="post"><?php echo DN_Core::nonce('like'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="like"><input type="hidden" name="candidate" value="<?php echo (int)$candidate; ?>"><button class="dn-button">♥ Interesse tonen</button></form></div></article><?php endforeach; ?></div></div><?php return (string)ob_get_clean();
    }

    private static function discover_onboarding(int $id): string
    {
        $verified=get_user_meta($id,'dn_email_verified',true)==='1';$single=get_user_meta($id,'dn_single_confirmed',true)==='1'||get_user_meta($id,'dn_is_single',true)==='1';$complete=self::profile_complete($id);
        ob_start(); ?><div class="dn-wrap dn-discover-onboarding"><div class="dn-page-hero"><span class="dn-page-kicker">JOUW MATCHRONDE</span><h1>Bijna klaar om singles te ontdekken.</h1><p>Voor goede matches hebben we eerst genoeg informatie nodig om wederzijds te kunnen filteren. Dat voorkomt willekeurig swipen.</p></div><div class="dn-onboarding-card"><div class="dn-onboarding-icon">♥</div><div><span class="dn-eyebrow">EERST EVEN DE BASIS GOED</span><h2>Maak je profiel match-klaar.</h2><p>Zodra je profiel actief is, tonen we alleen singles die aan jullie wederzijdse basisvoorkeuren voldoen.</p><div class="dn-onboarding-checks"><span class="<?php echo $verified?'is-done':''; ?>"><?php echo $verified?'✓':'○'; ?> E-mail bevestigd</span><span class="<?php echo $single?'is-done':''; ?>"><?php echo $single?'✓':'○'; ?> Single bevestigd</span><span class="<?php echo $complete?'is-done':''; ?>"><?php echo $complete?'✓':'○'; ?> Profiel compleet</span></div><div class="dn-onboarding-actions"><?php echo self::button('Mijn profiel afmaken →',DN_Core::page_url('profile')); // phpcs:ignore ?><?php if(!$verified): ?><form method="post"><?php echo DN_Core::nonce('resend'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="resend"><button class="dn-button dn-button-ghost">Verificatiemail opnieuw sturen</button></form><?php endif; ?></div></div></div><div class="dn-mini-promise"><strong>Geen race naar likes.</strong><p>Je profiel hoeft niet “populair” te zijn. Het moet vooral duidelijk genoeg zijn om de juiste persoon te kunnen herkennen.</p></div></div><?php return (string)ob_get_clean();
    }

    public static function matches(): string
    {
        if(!is_user_logged_in()){return self::login_required();}$id=get_current_user_id();global $wpdb;$t=$wpdb->prefix.'dn_matches';$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE status='active' AND (user_low=%d OR user_high=%d) ORDER BY created_at DESC",$id,$id));
        ob_start(); ?><div class="dn-wrap"><div class="dn-page-hero"><span class="dn-page-kicker">WEDERZIJDSE INTERESSE</span><h1>Mijn matches</h1><p>Alleen als jullie allebei interesse tonen, ontstaat hier een match en opent de chat.</p></div><div class="dn-list"><?php if(!$rows): ?><div class="dn-card dn-empty"><h2>Nog geen matches</h2><p>Dat is niet erg. Een match is pas waardevol als de interesse wederzijds is.</p><?php echo self::button('Ontdek singles',DN_Core::page_url('discover')); // phpcs:ignore ?></div><?php endif; foreach($rows as $row):$other=(int)$row->user_low===$id?(int)$row->user_high:(int)$row->user_low;$u=get_userdata($other);$score=DN_Match::score($id,$other);?><article class="dn-card dn-match-row"><div><h2>♥ <?php echo esc_html($u?$u->display_name:'Match'); ?></h2><p><?php echo (int)$score['score']; ?>% match · <?php echo esc_html((string)get_user_meta($other,'dn_city',true)); ?></p></div><?php echo self::button('Open chat →',DN_Core::page_url('chat',['match'=>(int)$row->id])); // phpcs:ignore ?></article><?php endforeach; ?></div></div><?php return (string)ob_get_clean();
    }

    public static function chat(): string
    {
        if(!is_user_logged_in()){return self::login_required();}$id=get_current_user_id();$match=(int)($_GET['match']??0);$row=self::match_for_user($match,$id);if(!$row){return '<div class="dn-wrap">'.self::notice('Deze chat is niet beschikbaar.','error').'</div>';}
        $other=(int)$row->user_low===$id?(int)$row->user_high:(int)$row->user_low;$u=get_userdata($other);global $wpdb;$mt=$wpdb->prefix.'dn_messages';$messages=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$mt} WHERE match_id=%d ORDER BY created_at ASC LIMIT 300",$match));
        ob_start(); ?><div class="dn-wrap dn-chat-wrap"><div class="dn-page-hero dn-page-hero-compact"><span class="dn-page-kicker">INTERNE CHAT</span><h1><?php echo esc_html($u?$u->display_name:'Je match'); ?></h1><p>Contactgegevens, socials en externe links blijven geblokkeerd. Spreek gerust binnen het platform af.</p></div><?php echo self::query_message(); // phpcs:ignore ?><div class="dn-card dn-chat-log"><?php if(!$messages): ?><p class="dn-muted">Dit is het begin van jullie gesprek.</p><?php endif; foreach($messages as $m): ?><div class="dn-message <?php echo (int)$m->sender_id===$id?'is-mine':''; ?>"><p><?php echo esc_html((string)$m->body); ?></p><small><?php echo esc_html(wp_date('d-m H:i',strtotime((string)$m->created_at))); ?></small></div><?php endforeach; ?></div><form method="post" class="dn-card dn-chat-form"><?php echo DN_Core::nonce('message'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="message"><input type="hidden" name="match" value="<?php echo $match; ?>"><textarea required maxlength="1500" name="body" rows="4" placeholder="Schrijf een bericht…"></textarea><button class="dn-button">Versturen</button></form><details class="dn-card dn-chat-safety"><summary>Veiligheid en match beheren</summary><div class="dn-action-row"><form method="post"><?php echo DN_Core::nonce('report'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="report"><input type="hidden" name="user" value="<?php echo $other; ?>"><select name="reason"><option value="not_single">Deze persoon is niet single</option><option value="promotion">Promotie / externe links</option><option value="harassment">Ongewenst gedrag</option><option value="fake">Nep of misleidend profiel</option><option value="other">Anders</option></select><button class="dn-button dn-button-ghost">Rapporteren</button></form><form method="post"><?php echo DN_Core::nonce('block'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="block"><input type="hidden" name="user" value="<?php echo $other; ?>"><button class="dn-link-danger" onclick="return confirm('Deze gebruiker blokkeren?')">Blokkeren</button></form><form method="post"><?php echo DN_Core::nonce('end_match'); // phpcs:ignore ?><input type="hidden" name="dn_action" value="end_match"><input type="hidden" name="match" value="<?php echo $match; ?>"><button class="dn-link-danger">Match beëindigen</button></form></div></details></div><?php return (string)ob_get_clean();
    }

    public static function handle_post(string $action): void
    {
        if($action==='register'){self::post_register();return;}if($action==='login'){self::post_login();return;}
        if(!is_user_logged_in()){DN_Core::go('login',['dn_msg'=>'login']);}$id=get_current_user_id();
        if($action==='resend'&&DN_Core::verify_nonce('resend')){self::send_verification($id);DN_Core::go('dashboard',['dn_msg'=>'resent']);}
        if($action==='profile_save'&&DN_Core::verify_nonce('profile')){self::post_profile($id);}
        if($action==='like'&&DN_Core::verify_nonce('like')){self::post_like($id);}
        if($action==='message'&&DN_Core::verify_nonce('message')){self::post_message($id);}
        if($action==='report'&&DN_Core::verify_nonce('report')){self::post_report($id);}
        if($action==='block'&&DN_Core::verify_nonce('block')){self::post_block($id);}
        if($action==='end_match'&&DN_Core::verify_nonce('end_match')){self::post_end_match($id);}
        if($action==='found'&&DN_Core::verify_nonce('found')){update_user_meta($id,'dn_profile_status','success');update_user_meta($id,'dn_found_at',current_time('mysql'));self::close_user_matches($id);DN_Core::go('dashboard',['dn_msg'=>'found']);}
        if($action==='revoke_consent'&&DN_Core::verify_nonce('consent')){update_user_meta($id,'dn_consent','0');update_user_meta($id,'dn_profile_status','paused');self::close_user_matches($id);DN_Core::go('profile',['dn_msg'=>'paused']);}
        if($action==='delete_account'&&DN_Core::verify_nonce('delete')){require_once ABSPATH.'wp-admin/includes/user.php';wp_logout();wp_delete_user($id);wp_safe_redirect(home_url('/'));exit;}
    }

    private static function post_register(): void
    {
        if(!DN_Core::verify_nonce('register')){DN_Core::go('register',['dn_msg'=>'security']);}
        $email=sanitize_email(wp_unslash($_POST['email']??''));$password=(string)($_POST['password']??'');$name=sanitize_text_field(wp_unslash($_POST['display_name']??''));$gender=sanitize_key(wp_unslash($_POST['gender']??''));$dob=sanitize_text_field(wp_unslash($_POST['dob']??''));
        if(!$email||email_exists($email)||strlen($password)<10||!in_array($gender,['male','female'],true)||empty($_POST['single'])||empty($_POST['consent'])){DN_Core::go('register',['dn_msg'=>'register_error']);}
        try{$age=(new DateTimeImmutable($dob))->diff(new DateTimeImmutable('today'))->y;}catch(Throwable $e){$age=0;}if($age<18){DN_Core::go('register',['dn_msg'=>'under18']);}
        $base=sanitize_user(strstr($email,'@',true)?:'single',true);$login=$base?:'single';$n=1;while(username_exists($login)){$login=$base.$n++;}
        $uid=wp_create_user($login,$password,$email);if(is_wp_error($uid)){DN_Core::go('register',['dn_msg'=>'register_error']);}
        wp_update_user(['ID'=>$uid,'display_name'=>$name,'first_name'=>$name]);update_user_meta($uid,'dn_gender',$gender);update_user_meta($uid,'dn_dob',$dob);update_user_meta($uid,'dn_single_confirmed','1');update_user_meta($uid,'dn_is_single','1');update_user_meta($uid,'dn_consent','1');update_user_meta($uid,'dn_email_verified','0');update_user_meta($uid,'dn_profile_status','incomplete');update_user_meta($uid,'dn_age_min','25');update_user_meta($uid,'dn_age_max','55');self::send_verification((int)$uid);DN_Core::go('login',['dn_msg'=>'registered']);
    }

    private static function post_login(): void
    {
        if(!DN_Core::verify_nonce('login')){DN_Core::go('login',['dn_msg'=>'security']);}
        $creds=['user_login'=>sanitize_email(wp_unslash($_POST['email']??'')),'user_password'=>(string)($_POST['password']??''),'remember'=>true];$u=wp_signon($creds,is_ssl());if(is_wp_error($u)){DN_Core::go('login',['dn_msg'=>'login_error']);}DN_Core::go('dashboard');
    }

    private static function post_profile(int $id): void
    {
        $bio=sanitize_textarea_field(wp_unslash($_POST['bio']??''));$inspection=DN_Safety::inspect($bio);if($inspection['blocked']){DN_Safety::add_strike($id,$inspection['reason']);DN_Core::go('profile',['dn_msg'=>'blocked']);}
        $fields=['gender'=>'dn_gender','dob'=>'dn_dob','city'=>'dn_city','country'=>'dn_country','single_since'=>'dn_single_since','relationship_goal'=>'dn_relationship_goal','children_status'=>'dn_children_status','children_wish'=>'dn_children_wish','open_partner_children'=>'dn_open_partner_children','smoking'=>'dn_smoking','alcohol'=>'dn_alcohol','religion'=>'dn_religion','religion_importance'=>'dn_religion_importance','open_other_religion'=>'dn_open_other_religion'];
        foreach($fields as $post=>$meta){update_user_meta($id,$meta,sanitize_text_field(wp_unslash($_POST[$post]??'')));}
        update_user_meta($id,'dn_bio',$bio);update_user_meta($id,'dn_religion_consent',empty($_POST['religion_consent'])?'0':'1');
        $min=max(18,min(99,(int)($_POST['age_min']??18)));$max=max($min,min(99,(int)($_POST['age_max']??99)));update_user_meta($id,'dn_age_min',(string)$min);update_user_meta($id,'dn_age_max',(string)$max);
        $interests=array_slice(self::parse_list((string)($_POST['interests']??'')),0,100);$favorites=array_slice(self::parse_list((string)($_POST['favorite_interests']??'')),0,5);update_user_meta($id,'dn_interests',$interests);update_user_meta($id,'dn_favorite_interests',$favorites);
        $name=sanitize_text_field(wp_unslash($_POST['display_name']??''));if($name){wp_update_user(['ID'=>$id,'display_name'=>$name,'first_name'=>$name]);}self::refresh_status($id);DN_Core::go('profile',['dn_msg'=>'saved']);
    }

    private static function post_like(int $id): void
    {
        $candidate=(int)($_POST['candidate']??0);if(!$candidate||!DN_Match::eligible($id,$candidate)){DN_Core::go('discover',['dn_msg'=>'invalid']);}global $wpdb;$likes=$wpdb->prefix.'dn_likes';$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$likes} (from_user,to_user,created_at) VALUES (%d,%d,%s)",$id,$candidate,current_time('mysql')));$mutual=(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$likes} WHERE from_user=%d AND to_user=%d LIMIT 1",$candidate,$id));if($mutual){$low=min($id,$candidate);$high=max($id,$candidate);$matches=$wpdb->prefix.'dn_matches';$wpdb->query($wpdb->prepare("INSERT INTO {$matches} (user_low,user_high,status,created_at) VALUES (%d,%d,'active',%s) ON DUPLICATE KEY UPDATE status='active', ended_at=NULL",$low,$high,current_time('mysql')));DN_Core::go('matches',['dn_msg'=>'match']);}DN_Core::go('discover',['dn_msg'=>'liked']);
    }

    private static function post_message(int $id): void
    {
        $match=(int)($_POST['match']??0);$row=self::match_for_user($match,$id);if(!$row){DN_Core::go('matches',['dn_msg'=>'invalid']);}$body=sanitize_textarea_field(wp_unslash($_POST['body']??''));$check=DN_Safety::inspect($body);if($check['blocked']){DN_Safety::add_strike($id,$check['reason']);DN_Core::go('chat',['match'=>$match,'dn_msg'=>'blocked']);}if($body!==''){global $wpdb;$wpdb->insert($wpdb->prefix.'dn_messages',['match_id'=>$match,'sender_id'=>$id,'body'=>$body,'created_at'=>current_time('mysql')],['%d','%d','%s','%s']);}DN_Core::go('chat',['match'=>$match]);
    }

    private static function post_report(int $id): void
    {
        $other=(int)($_POST['user']??0);$reason=sanitize_key(wp_unslash($_POST['reason']??'other'));if($other&&$other!==$id){global $wpdb;$wpdb->insert($wpdb->prefix.'dn_reports',['reporter_id'=>$id,'reported_id'=>$other,'reason'=>$reason,'details'=>'','status'=>'open','created_at'=>current_time('mysql')]);}DN_Core::go('matches',['dn_msg'=>'reported']);
    }

    private static function post_block(int $id): void
    {
        $other=(int)($_POST['user']??0);if($other&&$other!==$id){global $wpdb;$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}dn_blocks (blocker_id,blocked_id,created_at) VALUES (%d,%d,%s)",$id,$other,current_time('mysql')));$low=min($id,$other);$high=max($id,$other);$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}dn_matches SET status='blocked',ended_at=%s WHERE user_low=%d AND user_high=%d",current_time('mysql'),$low,$high));}DN_Core::go('matches',['dn_msg'=>'blocked_user']);
    }

    private static function post_end_match(int $id): void
    {
        $match=(int)($_POST['match']??0);$row=self::match_for_user($match,$id);if($row){global $wpdb;$wpdb->update($wpdb->prefix.'dn_matches',['status'=>'ended','ended_at'=>current_time('mysql')],['id'=>$match],['%s','%s'],['%d']);}DN_Core::go('matches');
    }

    private static function send_verification(int $id): void
    {
        $u=get_userdata($id);if(!$u){return;}$token=wp_generate_password(40,false,false);update_user_meta($id,'dn_verify_token',$token);$url=DN_Core::page_url('verify',['uid'=>$id,'token'=>$token]);$subject='Bevestig je Dating Network-account';$message="Hoi {$u->display_name},\n\nBevestig je e-mailadres via deze link:\n{$url}\n\nDating Network";wp_mail($u->user_email,$subject,$message);
    }

    private static function refresh_status(int $id): void
    {
        $verified=get_user_meta($id,'dn_email_verified',true)==='1';$single=get_user_meta($id,'dn_single_confirmed',true)==='1'||get_user_meta($id,'dn_is_single',true)==='1';$consent=get_user_meta($id,'dn_consent',true)!=='0';$status=$verified&&$single&&$consent&&self::profile_complete($id)?'active':'incomplete';update_user_meta($id,'dn_profile_status',$status);
    }

    private static function profile_complete(int $id): bool
    {
        foreach(['dn_gender','dn_dob','dn_city','dn_country','dn_relationship_goal','dn_age_min','dn_age_max'] as $k){if(trim((string)get_user_meta($id,$k,true))===''){return false;}}return (DN_Match::age($id)??0)>=18;
    }

    private static function parse_list(string $text): array{$parts=preg_split('/[,\n\r;]+/u',$text)?:[];$out=[];foreach($parts as $v){$v=trim(sanitize_text_field($v));if($v!==''&&!in_array($v,$out,true)){$out[]=$v;}}return $out;}
    private static function match_for_user(int $match,int $id): ?object{if(!$match){return null;}global $wpdb;$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dn_matches WHERE id=%d AND status='active' AND (user_low=%d OR user_high=%d) LIMIT 1",$match,$id,$id));return $row?:null;}
    private static function close_user_matches(int $id): void{global $wpdb;$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}dn_matches SET status='ended',ended_at=%s WHERE status='active' AND (user_low=%d OR user_high=%d)",current_time('mysql'),$id,$id));}
    private static function login_required(): string{return '<div class="dn-wrap">'.self::notice('Log eerst in om verder te gaan.','info').self::button('Inloggen',DN_Core::page_url('login')).'</div>';}
    private static function button(string $label,string $url): string{return '<a class="dn-button" href="'.esc_url($url).'">'.esc_html($label).'</a>';}
    private static function notice(string $text,string $type='info'): string{return '<div class="dn-notice dn-notice-'.esc_attr($type).'">'.esc_html($text).'</div>';}
    private static function query_message(): string{$key=sanitize_key(wp_unslash($_GET['dn_msg']??''));$m=['saved'=>['Profiel opgeslagen.','success'],'registered'=>['Account gemaakt. Controleer je e-mail om je account te bevestigen.','success'],'resent'=>['Verificatiemail opnieuw verstuurd.','success'],'liked'=>['Interesse opgeslagen. Bij wederzijdse interesse ontstaat een match.','success'],'match'=>['Het is wederzijds — jullie hebben een match!','success'],'reported'=>['Melding ontvangen. Dank je.','success'],'blocked'=>['Dit bevat externe contact- of promotiegegevens en is daarom geblokkeerd.','error'],'blocked_user'=>['Gebruiker geblokkeerd.','success'],'found'=>['Mooi! Je profiel is uit de matching gehaald.','success'],'paused'=>['Je toestemming is ingetrokken en je profiel is gepauzeerd.','success'],'login_error'=>['E-mailadres of wachtwoord klopt niet.','error'],'register_error'=>['Registreren lukte niet. Controleer de gegevens of gebruik een ander e-mailadres.','error'],'under18'=>['Dating Network is uitsluitend voor 18+.','error'],'security'=>['De beveiligingscontrole is verlopen. Probeer opnieuw.','error'],'invalid'=>['Deze actie is niet beschikbaar.','error']];return isset($m[$key])?self::notice($m[$key][0],$m[$key][1]):'';}
}
