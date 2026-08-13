<?php
if (!defined('ABSPATH')) {
    exit;
}

final class DN_Homepage
{
    public static function init(): void
    {
        // Intentionally registered after DN_Shortcodes so this replaces the legacy homepage renderer.
        add_shortcode('dating_network_home', [self::class, 'render']);
    }

    public static function render(): string
    {
        wp_enqueue_style('dn-home-v3', DN_URL . 'assets/home.css', [], DN_VERSION);

        $register_url = DN_Core::page_url('register');
        $login_url = DN_Core::page_url('login');
        $discover_url = DN_Core::page_url('discover');
        $cta_url = is_user_logged_in() ? $discover_url : $register_url;
        $cta_label = is_user_logged_in() ? 'Ontdek singles' : 'Maak gratis je profiel';

        ob_start();
        ?>
        <main class="dn-home-v3">
            <section class="dn-hero-v3">
                <div class="dn-hero-v3-copy">
                    <div class="dn-kicker-pill"><span>♥</span> Voor singles van 18+</div>
                    <h1>Je bent hier niet om langer te daten.<br><em>Je bent hier om iemand te vinden.</em></h1>
                    <p class="dn-hero-v3-lead">Dating Network is gebouwd voor single mannen en vrouwen die serieus iemand willen leren kennen. Geen betaalde zichtbaarheid, geen promotieaccounts en geen contactgegevens buiten het platform.</p>
                    <div class="dn-hero-v3-actions">
                        <a class="dn-button dn-button-hero" href="<?php echo esc_url($cta_url); ?>"><?php echo esc_html($cta_label); ?> <span aria-hidden="true">→</span></a>
                        <?php if (!is_user_logged_in()) : ?>
                            <a class="dn-link-quiet" href="<?php echo esc_url($login_url); ?>">Ik heb al een account</a>
                        <?php endif; ?>
                    </div>
                    <div class="dn-hero-proof">
                        <span><b>✓</b> Alleen singles</span>
                        <span><b>✓</b> Wederzijdse interesse</span>
                        <span><b>✓</b> Matching blijft gratis</span>
                    </div>
                </div>

                <div class="dn-hero-v3-stage" aria-label="Voorbeeld van een uitlegbare match">
                    <div class="dn-stage-glow"></div>
                    <div class="dn-person-card dn-person-card-a">
                        <div class="dn-person-avatar">M</div>
                        <div><strong>Single profiel</strong><span>Gouda · 38</span></div>
                    </div>
                    <div class="dn-person-card dn-person-card-b">
                        <div class="dn-person-avatar">V</div>
                        <div><strong>Passend profiel</strong><span>12 km verder · 36</span></div>
                    </div>
                    <div class="dn-match-panel">
                        <div class="dn-match-panel-top"><span class="dn-match-heart">♥</span><div><small>PASSENDE VOORKEUREN</small><strong>89%</strong></div></div>
                        <ul>
                            <li><span>✓</span> Zelfde relatiedoel</li>
                            <li><span>✓</span> Leeftijd past wederzijds</li>
                            <li><span>✓</span> Religie past bij voorkeur</li>
                            <li><span>✓</span> 7 gedeelde interesses</li>
                        </ul>
                        <div class="dn-match-explain">Geen magie. Je ziet waarom iemand bij je past.</div>
                    </div>
                </div>
            </section>

            <section class="dn-principles-strip" aria-label="Waar Dating Network voor staat">
                <div><strong>0</strong><span>betaalde boosts</span></div>
                <div><strong>0</strong><span>matches achter een betaalmuur</span></div>
                <div><strong>1</strong><span>doel: een echte ontmoeting</span></div>
            </section>

            <section class="dn-home-block dn-home-center">
                <span class="dn-section-label">De andere route</span>
                <h2>Niet nóg een swipe-app.</h2>
                <p class="dn-section-lead">We optimaliseren niet voor schermtijd. We optimaliseren voor het moment waarop twee mensen elkaar willen ontmoeten — en de site daarna minder nodig hebben.</p>
                <div class="dn-feature-grid">
                    <article><span class="dn-icon-chip">01</span><h3>Maak een echt profiel</h3><p>Vertel wie je bent, wat je belangrijk vindt, je religie, levensstijl en maximaal 100 interesses.</p></article>
                    <article><span class="dn-icon-chip">02</span><h3>Krijg passende mensen</h3><p>Voorkeuren worden wederzijds bekeken. Jij moet bij hen passen én zij bij jou.</p></article>
                    <article><span class="dn-icon-chip">03</span><h3>Alleen samen verder</h3><p>Pas wanneer de interesse van beide kanten komt, wordt een privéchat geopend.</p></article>
                    <article><span class="dn-icon-chip">04</span><h3>Ontmoet in het echt</h3><p>Plan veilig via de interne chat en ontmoet elkaar op een plek waar jullie je prettig bij voelen.</p></article>
                </div>
            </section>

            <section class="dn-home-block dn-depth-section">
                <div class="dn-depth-copy">
                    <span class="dn-section-label">Meer dan een foto</span>
                    <h2>Een goede match heeft meer dan één indicator.</h2>
                    <p>Afstand en leeftijd zijn nuttig, maar zeggen weinig over hoe twee levens bij elkaar passen. Daarom kijken we ook naar doelen, kinderen, levensstijl, religie en wat jullie echt leuk vinden.</p>
                    <div class="dn-interest-cloud">
                        <span>Reizen</span><span>Films</span><span>Wandelen</span><span>Koken</span><span>AI</span><span>Muziek</span><span>Sport</span><span>Familie</span><span>Auto's</span><span>Natuur</span><span>Lezen</span><span>Cultuur</span>
                    </div>
                </div>
                <div class="dn-depth-card">
                    <div class="dn-depth-score"><strong>82%</strong><span>passende voorkeuren</span></div>
                    <div class="dn-depth-row"><span>Relatiedoel</span><b>Sterk</b></div>
                    <div class="dn-depth-row"><span>Kinderen & kinderwens</span><b>Sterk</b></div>
                    <div class="dn-depth-row"><span>Religie</span><b>Past</b></div>
                    <div class="dn-depth-row"><span>Interesses</span><b>6 gedeeld</b></div>
                    <div class="dn-depth-row"><span>Afstand</span><b>18 km</b></div>
                    <small>De matchscore is uitlegbaar en niet gebaseerd op populariteit of hoeveel je betaalt.</small>
                </div>
            </section>

            <section class="dn-home-block dn-safety-v3">
                <div class="dn-safety-v3-copy">
                    <span class="dn-section-label dn-section-label-light">Veiligheid is geen premiumfunctie</span>
                    <h2>De kennismaking blijft binnen Dating Network.</h2>
                    <p>Geen telefoonnummers, e-mailadressen, externe links, socialmedia-handles of commerciële promotie in profielen en chats. Zo houden we de kennismaking controleerbaar en kunnen blokkeren en rapporteren ook echt iets betekenen.</p>
                    <a href="<?php echo esc_url($cta_url); ?>" class="dn-button dn-button-light"><?php echo esc_html($cta_label); ?> →</a>
                </div>
                <div class="dn-safety-v3-list">
                    <div><span>18+</span><strong>Alleen volwassenen</strong><p>Iedere gebruiker bevestigt minimaal 18 jaar oud te zijn.</p></div>
                    <div><span>Single</span><strong>Geen Second Love</strong><p>Geen relatie, open relatie of affaire zoeken. Niet single? Dan hoor je hier niet thuis.</p></div>
                    <div><span>Privé</span><strong>Geen contactgegevens</strong><p>Digitale communicatie blijft op het platform tot jullie elkaar in het echt ontmoeten.</p></div>
                    <div><span>Actie</span><strong>Blokkeren & rapporteren</strong><p>Ongewenst gedrag kan direct worden gestopt en gemeld voor moderatie.</p></div>
                </div>
            </section>

            <section class="dn-home-block dn-mission-section">
                <div class="dn-mission-card">
                    <span class="dn-section-label">Ons verdienmodel</span>
                    <h2>We verdienen niet aan jouw eenzaamheid.</h2>
                    <p>Een match wordt nooit verstopt achter een abonnement en betalen geeft je geen betere plek in het algoritme. Als daten niet lukt, willen we je juist helpen begrijpen waar het stokt.</p>
                    <div class="dn-mission-points"><span>Geen betaalde boosts</span><span>Geen premium matches</span><span>Geen kunstmatige schaarste</span></div>
                </div>
                <div class="dn-coach-card">
                    <div class="dn-coach-badge">Dating coaching</div>
                    <h3>Geen geluk? Dan laten we je niet zwemmen.</h3>
                    <p>Profiel bekeken maar weinig interesse? Matches maar geen dates? Later kan persoonlijke coaching je helpen precies op het punt waar je vastloopt.</p>
                    <small>Coaching is optioneel. De datingfunctionaliteit blijft gewoon bruikbaar zonder coaching te kopen.</small>
                </div>
            </section>

            <section class="dn-home-block dn-rules-section">
                <div class="dn-rules-heading"><span class="dn-section-label">Voordat je begint</span><h2>Vier regels. Geen kleine lettertjes nodig.</h2></div>
                <div class="dn-rules-list">
                    <div><span>1</span><p><strong>Je bent echt single.</strong> Geen partner, open relatie of affaire.</p></div>
                    <div><span>2</span><p><strong>Je bent hier om iemand te ontmoeten.</strong> Niet om volgers, klanten of verkeer te verzamelen.</p></div>
                    <div><span>3</span><p><strong>Je houdt contact op het platform.</strong> Geen telefoonnummer, socials, mail of links uitwisselen.</p></div>
                    <div><span>4</span><p><strong>Je behandelt mensen als mensen.</strong> Geen intimidatie, manipulatie of ongewenst gedrag.</p></div>
                </div>
            </section>

            <section class="dn-final-cta-v3">
                <div class="dn-final-heart">♥</div>
                <span class="dn-section-label">Misschien zit jouw reden om ons te verlaten hier al tussen.</span>
                <h2>Maak je profiel. Ontmoet iemand. Verwijder ons met plezier.</h2>
                <p>Gratis aanmelden. Alleen voor singles van 18+. Geen betaalde voorrang in matching.</p>
                <div class="dn-final-actions">
                    <a class="dn-button dn-button-hero" href="<?php echo esc_url($cta_url); ?>"><?php echo esc_html($cta_label); ?> →</a>
                    <?php if (!is_user_logged_in()) : ?><a class="dn-final-login" href="<?php echo esc_url($login_url); ?>">Of log in</a><?php endif; ?>
                </div>
            </section>
        </main>
        <?php
        return (string) ob_get_clean();
    }
}
