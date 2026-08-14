<?php
if (!defined('ABSPATH')) { exit; }

class DN_Photos
{
    private const MAX_BYTES = 8388608; // 8 MB

    public static function init(): void
    {
        add_action('init', [self::class, 'handle_frontend'], 8);
        add_filter('do_shortcode_tag', [self::class, 'extend_shortcodes'], 20, 4);
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_post_dn_photo_review', [self::class, 'handle_review']);
    }

    public static function extend_shortcodes(string $output, string $tag, array $attr, array $match): string
    {
        if ($tag === 'dating_network_profile' && is_user_logged_in()) {
            return $output . self::profile_panel(get_current_user_id());
        }
        if ($tag === 'dating_network_home') {
            $gallery = self::homepage_gallery();
            if ($gallery !== '') {
                $needle = '<section class="dn-coaching">';
                if (str_contains($output, $needle)) {
                    return str_replace($needle, $gallery . $needle, $output);
                }
                return $output . $gallery;
            }
        }
        return $output;
    }

    public static function handle_frontend(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['dn_action']) || !is_user_logged_in()) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['dn_action']));
        if ($action === 'photo_upload') {
            self::handle_upload(get_current_user_id());
        }
        if ($action === 'photo_home_consent') {
            self::handle_home_consent(get_current_user_id());
        }
    }

    private static function handle_upload(int $user_id): void
    {
        if (!DN_Core::verify_nonce('photo')) {
            DN_Core::go('profile', ['dn_msg' => 'security']);
        }

        $authentic = !empty($_POST['photo_authentic']);
        $rights = !empty($_POST['photo_rights']);
        $no_promo = !empty($_POST['photo_no_promo']);
        if (!$authentic || !$rights || !$no_promo) {
            DN_Core::go('profile', ['dn_photo_msg' => 'terms']);
        }

        if (empty($_FILES['dn_profile_photo']) || !isset($_FILES['dn_profile_photo']['error']) || (int)$_FILES['dn_profile_photo']['error'] !== UPLOAD_ERR_OK) {
            DN_Core::go('profile', ['dn_photo_msg' => 'upload']);
        }

        $file = $_FILES['dn_profile_photo'];
        if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > self::MAX_BYTES) {
            DN_Core::go('profile', ['dn_photo_msg' => 'size']);
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $image_info = $tmp !== '' ? @getimagesize($tmp) : false;
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!$image_info || !in_array((string)($image_info['mime'] ?? ''), $allowed, true)) {
            DN_Core::go('profile', ['dn_photo_msg' => 'type']);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('dn_profile_photo', 0, [], ['test_form' => false]);
        if (is_wp_error($attachment_id)) {
            DN_Core::go('profile', ['dn_photo_msg' => 'upload']);
        }
        $attachment_id = (int)$attachment_id;

        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => 'Dating Network profielfoto',
            'post_excerpt' => '',
            'post_content' => '',
        ]);

        // Re-encode waar WordPress dit ondersteunt. Dit verwijdert doorgaans EXIF/GPS-metadata uit de opgeslagen afbeelding.
        $path = get_attached_file($attachment_id);
        if ($path && is_string($path)) {
            $editor = wp_get_image_editor($path);
            if (!is_wp_error($editor)) {
                $editor->set_quality(90);
                $editor->save($path);
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $path));
            }
        }

        update_post_meta($attachment_id, '_dn_photo_owner', $user_id);
        update_post_meta($attachment_id, '_dn_photo_status', 'pending');
        update_post_meta($attachment_id, '_dn_photo_authentic_confirmed', '1');
        update_post_meta($attachment_id, '_dn_photo_rights_confirmed', '1');
        update_post_meta($attachment_id, '_dn_photo_no_promo_confirmed', '1');
        update_post_meta($attachment_id, '_dn_photo_home_consent', !empty($_POST['photo_home_consent']) ? '1' : '0');
        update_post_meta($attachment_id, '_dn_photo_uploaded_at', current_time('mysql'));
        update_user_meta($user_id, 'dn_pending_photo_id', $attachment_id);

        DN_Core::go('profile', ['dn_photo_msg' => 'pending']);
    }

    private static function handle_home_consent(int $user_id): void
    {
        if (!DN_Core::verify_nonce('photo_consent')) {
            DN_Core::go('profile', ['dn_msg' => 'security']);
        }
        $photo_id = (int)get_user_meta($user_id, 'dn_profile_photo_id', true);
        if (!$photo_id || (int)get_post_meta($photo_id, '_dn_photo_owner', true) !== $user_id || get_post_meta($photo_id, '_dn_photo_status', true) !== 'approved') {
            DN_Core::go('profile', ['dn_photo_msg' => 'invalid']);
        }
        update_post_meta($photo_id, '_dn_photo_home_consent', !empty($_POST['photo_home_consent']) ? '1' : '0');
        DN_Core::go('profile', ['dn_photo_msg' => 'consent_saved']);
    }

    private static function profile_panel(int $user_id): string
    {
        $approved_id = (int)get_user_meta($user_id, 'dn_profile_photo_id', true);
        $pending_id = (int)get_user_meta($user_id, 'dn_pending_photo_id', true);
        $pending = $pending_id && get_post_meta($pending_id, '_dn_photo_status', true) === 'pending';
        $approved = $approved_id && get_post_meta($approved_id, '_dn_photo_status', true) === 'approved';
        $home_consent = $approved ? get_post_meta($approved_id, '_dn_photo_home_consent', true) === '1' : false;
        $msg = sanitize_key(wp_unslash($_GET['dn_photo_msg'] ?? ''));

        $messages = [
            'pending' => ['Foto ontvangen. Hij wordt pas zichtbaar nadat hij handmatig is goedgekeurd.', 'success'],
            'terms' => ['Bevestig alle drie de verplichte verklaringen bij de foto.', 'error'],
            'upload' => ['Uploaden is niet gelukt. Probeer een andere foto.', 'error'],
            'size' => ['De foto mag maximaal 8 MB zijn.', 'error'],
            'type' => ['Gebruik een JPG, PNG of WebP-afbeelding.', 'error'],
            'invalid' => ['Deze foto kan niet worden aangepast.', 'error'],
            'consent_saved' => ['Je toestemming voor homepage-publicatie is opgeslagen.', 'success'],
        ];

        ob_start(); ?>
        <div class="dn-wrap dn-photo-wrap">
            <section class="dn-card dn-photo-card">
                <div class="dn-photo-copy">
                    <span class="dn-page-kicker">PROFIELFOTO</span>
                    <h2>Een echte foto, handmatig gecontroleerd.</h2>
                    <p>Iedere foto gaat eerst langs moderatie. Geen logo's, links, QR-codes, watermerken, gebruikersnamen of andere promotie.</p>
                    <?php if (isset($messages[$msg])): ?>
                        <div class="dn-notice dn-notice-<?php echo esc_attr($messages[$msg][1]); ?>"><?php echo esc_html($messages[$msg][0]); ?></div>
                    <?php endif; ?>
                    <?php if ($pending): ?><div class="dn-photo-status dn-photo-status-pending">⏳ Wacht op goedkeuring</div><?php endif; ?>
                    <?php if ($approved): ?><div class="dn-photo-status dn-photo-status-approved">✓ Goedgekeurd</div><?php endif; ?>
                </div>

                <?php if ($approved): ?>
                    <div class="dn-photo-current"><?php echo wp_get_attachment_image($approved_id, 'medium_large', false, ['class' => 'dn-approved-photo', 'alt' => 'Goedgekeurde profielfoto']); ?></div>
                    <form method="post" class="dn-photo-consent-form">
                        <?php echo DN_Core::nonce('photo_consent'); // phpcs:ignore ?>
                        <input type="hidden" name="dn_action" value="photo_home_consent">
                        <label class="dn-check"><input type="checkbox" name="photo_home_consent" value="1" <?php checked($home_consent); ?>><span><strong>Mijn goedgekeurde foto mag gratis op de openbare homepage worden getoond.</strong><br>Deze toestemming is vrijwillig, heeft geen invloed op mijn matches en kan ik hier altijd weer intrekken.</span></label>
                        <button class="dn-button dn-button-ghost" type="submit">Publicatietoestemming opslaan</button>
                    </form>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" class="dn-form dn-photo-upload-form">
                    <?php echo DN_Core::nonce('photo'); // phpcs:ignore ?>
                    <input type="hidden" name="dn_action" value="photo_upload">
                    <label>Nieuwe profielfoto
                        <input required type="file" name="dn_profile_photo" accept="image/jpeg,image/png,image/webp">
                        <small>JPG, PNG of WebP · maximaal 8 MB.</small>
                    </label>
                    <label class="dn-check"><input required type="checkbox" name="photo_authentic" value="1"><span><strong>Dit is een echte foto van mijzelf.</strong> Geen AI-gegenereerde afbeelding, stockfoto, foto van een bekend persoon of iemand anders. Er staan geen andere herkenbare personen op.</span></label>
                    <label class="dn-check"><input required type="checkbox" name="photo_rights" value="1"><span><strong>Ik heb het recht deze foto te gebruiken.</strong> Ik ben zelf rechthebbende of heb de benodigde toestemming/licentie voor gebruik en publicatie door Dating Network. De foto schendt geen auteursrecht, portretrecht of andere rechten van derden.</span></label>
                    <label class="dn-check"><input required type="checkbox" name="photo_no_promo" value="1"><span><strong>De foto bevat geen promotie.</strong> Geen logo, watermerk, URL, QR-code, telefoonnummer, e-mailadres, socialmedia-handle, advertentie of andere manier om mensen buiten Dating Network te benaderen.</span></label>
                    <label class="dn-check dn-check-optional"><input type="checkbox" name="photo_home_consent" value="1"><span><strong>Optioneel:</strong> na goedkeuring mag Dating Network deze foto gratis op de openbare homepage tonen. Dit geeft geen extra zichtbaarheid in matching en ik kan de toestemming later intrekken.</span></label>
                    <button class="dn-button" type="submit">Foto indienen voor controle →</button>
                </form>
            </section>
        </div>
        <?php return (string)ob_get_clean();
    }

    private static function homepage_gallery(): string
    {
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => 18,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_dn_photo_status', 'value' => 'approved'],
                ['key' => '_dn_photo_home_consent', 'value' => '1'],
            ],
        ]);
        if (!$ids) { return ''; }

        $visible = [];
        foreach ($ids as $photo_id) {
            $owner = (int)get_post_meta((int)$photo_id, '_dn_photo_owner', true);
            if ($owner && get_user_meta($owner, 'dn_profile_status', true) === 'active') {
                $visible[] = (int)$photo_id;
            }
            if (count($visible) >= 12) { break; }
        }
        if (!$visible) { return ''; }

        ob_start(); ?>
        <section class="dn-home-people">
            <div class="dn-section-head"><span>ECHTE MENSEN · HANDMATIG GOEDGEKEURD</span><h2>Singles die echt bestaan.</h2><p>Deze foto's zijn vrijwillig voor homepage-publicatie vrijgegeven én eerst handmatig gecontroleerd. Betalen speelt hierbij geen rol.</p></div>
            <div class="dn-home-photo-grid">
                <?php foreach ($visible as $photo_id): ?><figure><?php echo wp_get_attachment_image($photo_id, 'medium_large', false, ['loading' => 'lazy', 'alt' => 'Goedgekeurd Dating Network-profiel']); ?></figure><?php endforeach; ?>
            </div>
        </section>
        <?php return (string)ob_get_clean();
    }

    public static function admin_menu(): void
    {
        add_submenu_page('dating-network', 'Foto moderatie', 'Foto moderatie', 'manage_options', 'dating-network-photos', [self::class, 'admin_page']);
    }

    public static function admin_page(): void
    {
        if (!current_user_can('manage_options')) { return; }
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'ASC',
            'fields' => 'ids',
            'meta_key' => '_dn_photo_status',
            'meta_value' => 'pending',
        ]);
        ?>
        <div class="wrap"><h1>Dating Network · Foto moderatie</h1>
        <p><strong><?php echo count($ids); ?></strong> foto('s) wachten op controle. Keur alleen goed als het om een plausibele echte profielfoto gaat en er geen zichtbare promotie/contactgegevens aanwezig zijn. De gebruiker heeft daarnaast zelf de rechtenverklaring bevestigd.</p>
        <?php if (!$ids): ?><p>Geen foto's in de wachtrij.</p><?php endif; ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;max-width:1200px">
        <?php foreach ($ids as $photo_id): $owner=(int)get_post_meta($photo_id,'_dn_photo_owner',true);$user=get_userdata($owner); ?>
            <div class="card" style="max-width:none">
                <div style="aspect-ratio:4/3;overflow:hidden;background:#eee;border-radius:8px;margin-bottom:12px"><?php echo wp_get_attachment_image($photo_id,'medium_large',false,['style'=>'width:100%;height:100%;object-fit:cover']); ?></div>
                <h2><?php echo esc_html($user ? $user->display_name : 'Onbekende gebruiker'); ?></h2>
                <ul><li>✓ Verklaart: echte eigen persoonsfoto</li><li>✓ Verklaart: gebruiks-/publicatierechten</li><li>✓ Verklaart: geen promotie/contactgegevens</li><li><?php echo get_post_meta($photo_id,'_dn_photo_home_consent',true)==='1'?'✓':'–'; ?> Homepage-toestemming</li></ul>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('dn_photo_review_'.$photo_id); ?>
                    <input type="hidden" name="action" value="dn_photo_review"><input type="hidden" name="photo_id" value="<?php echo (int)$photo_id; ?>">
                    <label style="display:block;margin:10px 0"><input required type="checkbox" name="review_real" value="1"> Ik heb gecontroleerd dat dit plausibel een echte persoonsfoto is.</label>
                    <label style="display:block;margin:10px 0"><input required type="checkbox" name="review_clean" value="1"> Ik zie geen logo, link, QR, contactgegeven, gebruikersnaam, watermerk of promotie.</label>
                    <p><button class="button button-primary" name="decision" value="approve">Goedkeuren</button> <button class="button" name="decision" value="reject" formnovalidate>Afwijzen</button></p>
                </form>
            </div>
        <?php endforeach; ?>
        </div></div>
        <?php
    }

    public static function handle_review(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Geen toegang.'); }
        $photo_id = (int)($_POST['photo_id'] ?? 0);
        check_admin_referer('dn_photo_review_'.$photo_id);
        if (!$photo_id || get_post_meta($photo_id, '_dn_photo_status', true) !== 'pending') {
            wp_safe_redirect(admin_url('admin.php?page=dating-network-photos'));
            exit;
        }

        $decision = sanitize_key(wp_unslash($_POST['decision'] ?? ''));
        $owner = (int)get_post_meta($photo_id, '_dn_photo_owner', true);
        if ($decision === 'approve') {
            if (empty($_POST['review_real']) || empty($_POST['review_clean'])) {
                wp_die('Bevestig eerst beide moderatiecontroles.');
            }
            $previous = (int)get_user_meta($owner, 'dn_profile_photo_id', true);
            if ($previous && $previous !== $photo_id) {
                update_post_meta($previous, '_dn_photo_status', 'archived');
                update_post_meta($previous, '_dn_photo_home_consent', '0');
            }
            update_post_meta($photo_id, '_dn_photo_status', 'approved');
            update_post_meta($photo_id, '_dn_photo_moderated_by', get_current_user_id());
            update_post_meta($photo_id, '_dn_photo_moderated_at', current_time('mysql'));
            update_user_meta($owner, 'dn_profile_photo_id', $photo_id);
            delete_user_meta($owner, 'dn_pending_photo_id');
        } elseif ($decision === 'reject') {
            update_post_meta($photo_id, '_dn_photo_status', 'rejected');
            update_post_meta($photo_id, '_dn_photo_home_consent', '0');
            update_post_meta($photo_id, '_dn_photo_moderated_by', get_current_user_id());
            update_post_meta($photo_id, '_dn_photo_moderated_at', current_time('mysql'));
            delete_user_meta($owner, 'dn_pending_photo_id');
        }

        wp_safe_redirect(admin_url('admin.php?page=dating-network-photos'));
        exit;
    }
}
