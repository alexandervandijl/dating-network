<?php
if (!defined('ABSPATH')) { exit; }

class DN_Safety
{
    public static function inspect(string $text): array
    {
        $text=trim(wp_strip_all_tags($text));
        if($text===''){return ['blocked'=>false,'reason'=>''];}
        $lower=strtolower(remove_accents($text));
        $patterns=[
            'link'=>'~(?:https?://|www\.|\b(?:[a-z0-9][a-z0-9-]{0,62}\.)+[a-z]{2,24}\b)~iu',
            'email'=>'~\b[A-Z0-9._%+-]+\s*(?:@|\(at\)|\[at\])\s*[A-Z0-9.-]+\s*(?:\.|dot|punt)\s*[A-Z]{2,}\b~iu',
            'phone'=>'~(?<!\d)(?:\+?\d[\s().-]*){8,}(?!\d)~u',
            'handle'=>'~(^|\s)@[a-z0-9._-]{3,}\b~iu',
            'social'=>'~\b(?:onlyfans|fansly|instagram|insta|snapchat|telegram|whatsapp|tiktok|tik\s*tok|discord|kik|signal|wechat|viber|facebook\s*messenger|twitter|x\.com)\b~iu',
            'promotion'=>'~\b(?:follow\s+me|volg\s+me|dm\s+me|stuur\s+me\s+een\s+dm|add\s+me|voeg\s+me\s+toe|subscribe|abonneer|link\s+in\s+(?:my|mijn)\s+bio|zoek\s+me\s+op|vind\s+me\s+op|app\s+me)\b~iu',
            'obfuscated_email'=>'~\b[a-z0-9._%+-]{2,}\s+(?:at|apenstaartje)\s+(?:gmail|hotmail|outlook|protonmail|icloud|yahoo|live)\s+(?:dot|punt)\s+[a-z]{2,}\b~iu',
        ];
        foreach($patterns as $reason=>$pattern){if(preg_match($pattern,$text)){return ['blocked'=>true,'reason'=>$reason];}}
        $words='(?:nul|zero|een|one|twee|two|drie|three|vier|four|vijf|five|zes|six|zeven|seven|acht|eight|negen|nine)';
        if(preg_match('~(?:'.$words.'[\s,.-]*){6,}~iu',$lower)){return ['blocked'=>true,'reason'=>'phone'];}
        return ['blocked'=>false,'reason'=>''];
    }

    public static function message_for_reason(string $reason): string
    {
        $m=['link'=>'Externe links zijn niet toegestaan. Houd het contact binnen het platform.','email'=>'E-mailadressen mogen niet worden uitgewisseld. Gebruik de interne chat.','phone'=>'Telefoonnummers mogen niet worden uitgewisseld. Gebruik de interne chat.','handle'=>'Socialmedia-handles mogen niet worden uitgewisseld.','social'=>'Verwijzingen naar externe chat- of socialmediaplatforms zijn niet toegestaan.','promotion'=>'Promotionele of externe contactverzoeken zijn niet toegestaan.'];
        return $m[$reason]??'Dit bevat informatie die niet via het platform mag worden gedeeld.';
    }

    public static function add_strike(int $user_id,string $reason): void
    {
        $count=(int)get_user_meta($user_id,'dn_safety_strikes',true)+1;
        update_user_meta($user_id,'dn_safety_strikes',$count);
        update_user_meta($user_id,'dn_last_safety_reason',sanitize_key($reason));
        update_user_meta($user_id,'dn_last_safety_at',current_time('mysql'));
        if($count>=5){update_user_meta($user_id,'dn_profile_status','review');}
    }
}
