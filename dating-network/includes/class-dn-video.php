<?php
if (!defined('ABSPATH')) { exit; }

class DN_Video
{
    private const SCHEMA = '2';
    private const SESSION_TTL = 1800;
    private const SIGNAL_TTL = 7200;

    public static function init(): void
    {
        self::maybe_install();
        add_filter('do_shortcode_tag', [self::class, 'filter_shortcode'], 70, 4);
        add_action('rest_api_init', [self::class, 'rest_routes']);
        add_action('admin_menu', [self::class, 'admin_menu'], 40);
        add_action('admin_post_dn_video_save', [self::class, 'save_settings']);
    }

    private static function maybe_install(): void
    {
        if ((string)get_option('dn_video_schema', '') === self::SCHEMA) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'dn_video_signals';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            match_id bigint(20) unsigned NOT NULL,
            session_id varchar(64) NOT NULL,
            sender_id bigint(20) unsigned NOT NULL,
            signal_type varchar(24) NOT NULL,
            payload longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY match_session (match_id,session_id),
            KEY created_at (created_at)
        ) {$charset};");
        update_option('dn_video_schema', self::SCHEMA);
        if (get_option('dn_video_enabled', null) === null) { update_option('dn_video_enabled', '1'); }
        if (get_option('dn_video_stun', null) === null) { update_option('dn_video_stun', 'stun:stun.l.google.com:19302'); }
    }

    private static function enabled(): bool
    {
        return (string)get_option('dn_video_enabled', '1') === '1';
    }

    private static function active_match(int $match_id, int $user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dn_matches';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id=%d AND status='active' AND (user_low=%d OR user_high=%d)",
            $match_id, $user_id, $user_id
        ));
    }

    public static function filter_shortcode(string $output, string $tag, $attr, $match): string
    {
        if ($tag !== 'dating_network_chat' || !is_user_logged_in() || !self::enabled()) { return $output; }
        $match_id = (int)($_GET['match'] ?? 0);
        $row = self::active_match($match_id, get_current_user_id());
        if (!$row) { return $output; }

        if (!empty($_GET['dn_video'])) { return self::render_room($match_id, $row); }

        $url = add_query_arg(['match' => $match_id, 'dn_video' => 1], DN_Core::page_url('chat'));
        $bar = '<div class="dn-card dn-video-cta"><div><strong>📹 Videobellen binnen Dating Network</strong><br><span class="dn-muted">Rechtstreeks browser ↔ browser. Geen telefoonnummer of externe app nodig.</span></div><a class="dn-button" href="' . esc_url($url) . '">Videobellen</a></div>';
        return str_replace('<div class="dn-card dn-chat-log">', $bar . '<div class="dn-card dn-chat-log">', $output);
    }

    private static function render_room(int $match_id, $row): string
    {
        $user_id = get_current_user_id();
        $other_id = (int)$row->user_low === $user_id ? (int)$row->user_high : (int)$row->user_low;
        $other = get_userdata($other_id);
        $back = DN_Core::page_url('chat', ['match' => $match_id]);
        $rest = esc_url_raw(rest_url('dating-network/v1/video'));
        $nonce = wp_create_nonce('wp_rest');

        ob_start(); ?>
        <div class="dn-wrap dn-video-wrap">
            <div class="dn-page-hero dn-page-hero-compact">
                <span class="dn-page-kicker">RECHTSTREEKS 1-OP-1</span>
                <h1>Videobellen met <?php echo esc_html($other ? $other->display_name : 'je match'); ?></h1>
                <p>Audio en video gaan waar mogelijk rechtstreeks tussen jullie browsers. Dating Network slaat het gesprek niet op en neemt het niet op.</p>
            </div>
            <div id="dn-video-consent" class="dn-card dn-video-consent">
                <strong>Voor je camera aangaat</strong>
                <p class="dn-muted">Camera en microfoon worden pas benaderd nadat je hieronder start. De tijdelijke verbindingssignalen lopen via Dating Network en worden automatisch opgeschoond. Maak geen opname zonder toestemming van je match.</p>
                <div class="dn-form-actions"><button id="dn-video-start" class="dn-button" type="button">Camera en microfoon starten →</button><a class="dn-button dn-button-ghost" href="<?php echo esc_url($back); ?>">Terug naar chat</a></div>
                <p class="dn-video-testnote">Testversie: directe P2P-verbinding. Als twee netwerken een directe verbinding blokkeren, kan een eigen TURN-relay nodig zijn.</p>
            </div>
            <div id="dn-video-stage" class="dn-video-stage" hidden>
                <div class="dn-video-status" id="dn-video-status">Verbinding voorbereiden…</div>
                <div class="dn-video-grid">
                    <div class="dn-video-tile dn-video-remote"><video id="dn-video-remote" autoplay playsinline></video><span><?php echo esc_html($other ? $other->display_name : 'Je match'); ?></span></div>
                    <div class="dn-video-tile dn-video-local"><video id="dn-video-local" autoplay muted playsinline></video><span>Jij</span></div>
                </div>
                <div class="dn-video-controls"><button id="dn-video-mic" type="button" class="dn-video-control">🎙 Microfoon</button><button id="dn-video-camera" type="button" class="dn-video-control">📷 Camera</button><button id="dn-video-end" type="button" class="dn-video-control is-danger">☎ Ophangen</button></div>
            </div>
        </div>
        <script>
        (() => {
            const endpoint = <?php echo wp_json_encode($rest); ?>;
            const nonce = <?php echo wp_json_encode($nonce); ?>;
            const matchId = <?php echo (int)$match_id; ?>;
            const startButton = document.getElementById('dn-video-start');
            const stage = document.getElementById('dn-video-stage');
            const consent = document.getElementById('dn-video-consent');
            const localVideo = document.getElementById('dn-video-local');
            const remoteVideo = document.getElementById('dn-video-remote');
            const status = document.getElementById('dn-video-status');
            const micButton = document.getElementById('dn-video-mic');
            const cameraButton = document.getElementById('dn-video-camera');
            const endButton = document.getElementById('dn-video-end');
            let pc = null, localStream = null, sessionId = '', role = '', lastSignal = 0, pollTimer = null, ended = false;
            let pendingCandidates = [];

            const api = async (path, options = {}) => {
                const response = await fetch(endpoint + path, {credentials:'same-origin', ...options, headers:{'Content-Type':'application/json','X-WP-Nonce':nonce,...(options.headers||{})}});
                const json = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(json.message || 'Verbinding mislukt');
                return json;
            };
            const setStatus = (text, state='') => { status.textContent = text; status.dataset.state = state; };
            const sendSignal = async (type, payload={}) => {
                if (!sessionId || ended) return;
                await api('/signal', {method:'POST', body:JSON.stringify({match:matchId, session:sessionId, type, payload})});
            };
            const flushCandidates = async () => {
                if (!pc || !pc.remoteDescription) return;
                const queued = pendingCandidates; pendingCandidates = [];
                for (const candidate of queued) { try { await pc.addIceCandidate(candidate); } catch (e) {} }
            };
            const handleSignal = async (signal) => {
                if (!pc || ended) return;
                const type = signal.type, payload = signal.payload || {};
                if (type === 'offer' && role === 'callee' && !pc.currentRemoteDescription) {
                    await pc.setRemoteDescription(new RTCSessionDescription(payload));
                    await flushCandidates();
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);
                    await sendSignal('answer', pc.localDescription);
                    setStatus('Verbinden met je match…');
                } else if (type === 'answer' && role === 'caller' && !pc.currentRemoteDescription) {
                    await pc.setRemoteDescription(new RTCSessionDescription(payload));
                    await flushCandidates();
                    setStatus('Verbinden met je match…');
                } else if (type === 'candidate' && payload && payload.candidate) {
                    const candidate = new RTCIceCandidate(payload);
                    if (pc.remoteDescription) { try { await pc.addIceCandidate(candidate); } catch (e) {} }
                    else { pendingCandidates.push(candidate); }
                } else if (type === 'hangup') { endCall(false, 'Je match heeft opgehangen.'); }
            };
            const pollSignals = async () => {
                if (ended || !sessionId) return;
                try {
                    const data = await api('/signals?match='+encodeURIComponent(matchId)+'&session='+encodeURIComponent(sessionId)+'&after='+encodeURIComponent(lastSignal));
                    for (const signal of (data.signals || [])) { lastSignal = Math.max(lastSignal, Number(signal.id || 0)); await handleSignal(signal); }
                } catch (e) { setStatus('Signaling tijdelijk onderbroken…', 'warning'); }
                if (!ended) pollTimer = window.setTimeout(pollSignals, 800);
            };
            const buildPeer = async (iceServers) => {
                pc = new RTCPeerConnection({iceServers:Array.isArray(iceServers)?iceServers:[], bundlePolicy:'max-bundle'});
                pc.onicecandidate = event => { if (event.candidate) sendSignal('candidate', event.candidate.toJSON()).catch(()=>{}); };
                pc.ontrack = event => {
                    if (event.streams && event.streams[0]) remoteVideo.srcObject = event.streams[0];
                    else { const stream = remoteVideo.srcObject instanceof MediaStream ? remoteVideo.srcObject : new MediaStream(); stream.addTrack(event.track); remoteVideo.srcObject = stream; }
                };
                pc.onconnectionstatechange = () => {
                    const s=pc.connectionState;
                    if(s==='connected') setStatus('✓ Verbonden','connected');
                    else if(s==='connecting') setStatus('Verbinden…');
                    else if(s==='disconnected') setStatus('Verbinding onderbroken…','warning');
                    else if(s==='failed') setStatus('Directe verbinding mislukt. Voor dit netwerk is mogelijk een eigen TURN-relay nodig.','error');
                    else if(s==='closed') setStatus('Gesprek beëindigd.');
                };
                localStream.getTracks().forEach(track => pc.addTrack(track, localStream));
            };
            const startCall = async () => {
                if (!navigator.mediaDevices?.getUserMedia || !window.RTCPeerConnection) { alert('Deze browser ondersteunt videobellen niet volledig.'); return; }
                startButton.disabled = true; startButton.textContent = 'Camera openen…';
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true},video:{width:{ideal:1280},height:{ideal:720},facingMode:'user'}});
                    localVideo.srcObject = localStream;
                    const session = await api('/session',{method:'POST',body:JSON.stringify({match:matchId})});
                    sessionId=session.session; role=session.role;
                    await buildPeer(session.iceServers || []);
                    consent.hidden=true; stage.hidden=false;
                    setStatus(role==='caller'?'Wachten tot je match ook Videobellen opent…':'Wachten op verbinding…');
                    pollSignals();
                    if(role==='caller') { const offer=await pc.createOffer({offerToReceiveAudio:true,offerToReceiveVideo:true}); await pc.setLocalDescription(offer); await sendSignal('offer',pc.localDescription); }
                } catch(error) {
                    startButton.disabled=false; startButton.textContent='Opnieuw proberen';
                    if(localStream) localStream.getTracks().forEach(track=>track.stop());
                    alert(error?.name==='NotAllowedError'?'Camera of microfoon is niet toegestaan. Geef Dating Network toestemming in je browser.':'Videobellen kon niet starten. Controleer je camera, microfoon en internetverbinding.');
                }
            };
            const endCall = (notify=true, message='Gesprek beëindigd.') => {
                if(ended) return;
                if(notify && sessionId) sendSignal('hangup',{}).catch(()=>{});
                ended=true; if(pollTimer) clearTimeout(pollTimer); if(pc) pc.close(); if(localStream) localStream.getTracks().forEach(track=>track.stop()); setStatus(message); endButton.disabled=true;
            };
            startButton?.addEventListener('click',startCall);
            micButton?.addEventListener('click',()=>{ if(!localStream)return; const tracks=localStream.getAudioTracks(); const newState=!tracks.some(t=>!t.enabled); tracks.forEach(t=>t.enabled=!newState); const enabled=tracks.some(t=>t.enabled); micButton.textContent=enabled?'🎙 Microfoon':'🔇 Microfoon uit'; micButton.classList.toggle('is-off',!enabled); });
            cameraButton?.addEventListener('click',()=>{ if(!localStream)return; const tracks=localStream.getVideoTracks(); const newState=!tracks.some(t=>!t.enabled); tracks.forEach(t=>t.enabled=!newState); const enabled=tracks.some(t=>t.enabled); cameraButton.textContent=enabled?'📷 Camera':'🚫 Camera uit'; cameraButton.classList.toggle('is-off',!enabled); });
            endButton?.addEventListener('click',()=>endCall(true));
            window.addEventListener('pagehide',()=>endCall(true));
        })();
        </script>
        <?php return (string)ob_get_clean();
    }

    public static function rest_routes(): void
    {
        register_rest_route('dating-network/v1', '/video/session', ['methods'=>'POST','permission_callback'=>static fn()=>is_user_logged_in(),'callback'=>[self::class,'rest_session']]);
        register_rest_route('dating-network/v1', '/video/signal', ['methods'=>'POST','permission_callback'=>static fn()=>is_user_logged_in(),'callback'=>[self::class,'rest_signal']]);
        register_rest_route('dating-network/v1', '/video/signals', ['methods'=>'GET','permission_callback'=>static fn()=>is_user_logged_in(),'callback'=>[self::class,'rest_signals']]);
    }

    public static function rest_session(WP_REST_Request $request)
    {
        $user_id=get_current_user_id(); $match_id=absint($request->get_param('match')); $row=self::active_match($match_id,$user_id);
        if(!$row || !self::enabled()) return new WP_Error('dn_video_forbidden','Deze videoruimte is niet beschikbaar.',['status'=>403]);
        self::cleanup();
        $key='dn_video_session_'.$match_id; $session=get_transient($key);
        if(!is_array($session)||empty($session['id'])) { $session=['id'=>wp_generate_uuid4(),'created_at'=>time()]; set_transient($key,$session,self::SESSION_TTL); }
        return rest_ensure_response(['session'=>(string)$session['id'],'role'=>(int)$row->user_low===$user_id?'caller':'callee','iceServers'=>self::ice_servers($user_id)]);
    }

    public static function rest_signal(WP_REST_Request $request)
    {
        $user_id=get_current_user_id(); $match_id=absint($request->get_param('match')); $session_id=sanitize_text_field((string)$request->get_param('session')); $type=sanitize_key((string)$request->get_param('type')); $payload=$request->get_param('payload');
        if(!in_array($type,['offer','answer','candidate','hangup'],true)) return new WP_Error('dn_video_type','Ongeldig videosignaal.',['status'=>400]);
        if(!self::session_allowed($match_id,$session_id,$user_id)) return new WP_Error('dn_video_forbidden','Deze videoruimte is niet beschikbaar.',['status'=>403]);
        $json=wp_json_encode(is_array($payload)?$payload:[]);
        if($json===false||strlen($json)>20000) return new WP_Error('dn_video_payload','Videosignaal is te groot.',['status'=>413]);
        global $wpdb; $table=$wpdb->prefix.'dn_video_signals';
        $ok=$wpdb->insert($table,['match_id'=>$match_id,'session_id'=>$session_id,'sender_id'=>$user_id,'signal_type'=>$type,'payload'=>$json,'created_at'=>current_time('mysql')],['%d','%s','%d','%s','%s','%s']);
        if($ok===false) return new WP_Error('dn_video_store','Videosignaal kon niet worden opgeslagen.',['status'=>500]);
        return rest_ensure_response(['ok'=>true,'id'=>(int)$wpdb->insert_id]);
    }

    public static function rest_signals(WP_REST_Request $request)
    {
        $user_id=get_current_user_id(); $match_id=absint($request->get_param('match')); $session_id=sanitize_text_field((string)$request->get_param('session')); $after=max(0,absint($request->get_param('after')));
        if(!self::session_allowed($match_id,$session_id,$user_id)) return new WP_Error('dn_video_forbidden','Deze videoruimte is niet beschikbaar.',['status'=>403]);
        global $wpdb; $table=$wpdb->prefix.'dn_video_signals';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,signal_type,payload FROM {$table} WHERE match_id=%d AND session_id=%s AND sender_id<>%d AND id>%d ORDER BY id ASC LIMIT 100",$match_id,$session_id,$user_id,$after));
        $signals=[]; foreach($rows as $row){$payload=json_decode((string)$row->payload,true);$signals[]=['id'=>(int)$row->id,'type'=>sanitize_key((string)$row->signal_type),'payload'=>is_array($payload)?$payload:[]];}
        return rest_ensure_response(['signals'=>$signals]);
    }

    private static function session_allowed(int $match_id,string $session_id,int $user_id): bool
    {
        if($session_id===''||!self::active_match($match_id,$user_id)) return false;
        $session=get_transient('dn_video_session_'.$match_id);
        return is_array($session)&&!empty($session['id'])&&hash_equals((string)$session['id'],$session_id);
    }

    private static function ice_servers(int $user_id): array
    {
        $servers=[]; $stun=self::url_list((string)get_option('dn_video_stun','stun:stun.l.google.com:19302'));
        if($stun) $servers[]=['urls'=>count($stun)===1?$stun[0]:$stun];
        $turn=self::url_list((string)get_option('dn_video_turn_urls','')); $secret=(string)get_option('dn_video_turn_secret','');
        if($turn&&$secret!==''){$username=(time()+3600).':dn-'.$user_id;$credential=base64_encode(hash_hmac('sha1',$username,$secret,true));$servers[]=['urls'=>count($turn)===1?$turn[0]:$turn,'username'=>$username,'credential'=>$credential];}
        return $servers;
    }

    private static function url_list(string $raw): array
    {
        $parts=preg_split('/[\r\n,]+/',$raw)?:[];$out=[];
        foreach($parts as $part){$url=trim($part);if($url===''||!preg_match('/^(stun|stuns|turn|turns):/i',$url))continue;$out[]=$url;}
        return array_values(array_unique($out));
    }

    private static function cleanup(): void
    {
        global $wpdb; $table=$wpdb->prefix.'dn_video_signals'; $cutoff=wp_date('Y-m-d H:i:s',time()-self::SIGNAL_TTL); $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at<%s",$cutoff));
    }

    public static function admin_menu(): void
    {
        add_submenu_page('dating-network','Videobellen','Videobellen','manage_options','dating-network-video',[self::class,'admin_page']);
    }

    public static function save_settings(): void
    {
        if(!current_user_can('manage_options')||!check_admin_referer('dn_video_save')) wp_die('Geen toegang');
        update_option('dn_video_enabled',empty($_POST['enabled'])?'0':'1');
        update_option('dn_video_stun',sanitize_textarea_field(wp_unslash($_POST['stun']??'')));
        update_option('dn_video_turn_urls',sanitize_textarea_field(wp_unslash($_POST['turn_urls']??'')));
        update_option('dn_video_turn_secret',sanitize_text_field(wp_unslash($_POST['turn_secret']??'')));
        wp_safe_redirect(admin_url('admin.php?page=dating-network-video&saved=1'));exit;
    }

    public static function admin_page(): void
    {
        if(!current_user_can('manage_options'))return;
        $enabled=self::enabled();$stun=(string)get_option('dn_video_stun','stun:stun.l.google.com:19302');$turn_urls=(string)get_option('dn_video_turn_urls','');$has_turn=trim($turn_urls)!==''&&(string)get_option('dn_video_turn_secret','')!== '';
        ?>
        <div class="wrap"><h1>Dating Network · Eigen videobellen</h1><?php if(!empty($_GET['saved'])):?><div class="notice notice-success"><p>Videobelinstellingen opgeslagen.</p></div><?php endif; ?>
        <p>Dating Network gebruikt WebRTC voor rechtstreekse 1-op-1 audio/video. WordPress wordt alleen gebruikt voor tijdelijke signaling; er wordt geen audio of video opgeslagen.</p><p><strong>Status:</strong> <?php echo $enabled?'Videobellen actief':'Videobellen uit'; ?> · <?php echo $has_turn?'eigen TURN-fallback ingesteld':'nog geen TURN-fallback'; ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('dn_video_save'); ?><input type="hidden" name="action" value="dn_video_save"><table class="form-table">
        <tr><th>Videobellen</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>> Inschakelen voor actieve matches</label></td></tr>
        <tr><th>STUN URL(s)</th><td><textarea class="large-text code" rows="3" name="stun"><?php echo esc_textarea($stun); ?></textarea><p class="description">Voor de test staat een publieke STUN-server ingevuld. Voor volledig eigen infrastructuur vervang je dit later door bijvoorbeeld <code>stun:video.jouwdomein.nl:3478</code> op je eigen coturn-server. Eén URL per regel.</p></td></tr>
        <tr><th>Eigen TURN URL(s)</th><td><textarea class="large-text code" rows="3" name="turn_urls"><?php echo esc_textarea($turn_urls); ?></textarea><p class="description">Optioneel voor netwerken waar directe P2P niet lukt. Bijvoorbeeld <code>turns:video.jouwdomein.nl:5349?transport=tcp</code>.</p></td></tr>
        <tr><th>TURN shared secret</th><td><input class="regular-text" type="password" name="turn_secret" value="<?php echo esc_attr((string)get_option('dn_video_turn_secret','')); ?>" autocomplete="new-password"><p class="description">Moet gelijk zijn aan <code>static-auth-secret</code> van coturn. Dating Network genereert automatisch tijdelijke TURN-credentials; het shared secret wordt nooit naar de browser gestuurd.</p></td></tr>
        </table><?php submit_button('Opslaan'); ?></form><hr><h2>Testen vanavond</h2><ol><li>Update Dating Network naar V0.5.8.</li><li>Gebruik twee verschillende Dating Network-accounts die met elkaar gematcht zijn.</li><li>Open dezelfde match op twee apparaten of browsers.</li><li>Klik bij beide op <strong>Videobellen</strong> en daarna op <strong>Camera en microfoon starten</strong>.</li></ol><p>Als P2P op beide netwerken wordt toegestaan, hoort de verbinding direct te werken. Bij striktere bedrijfs-, mobiele of hotelnetwerken kan de eigen TURN-fallback nodig zijn.</p></div>
        <?php
    }
}
