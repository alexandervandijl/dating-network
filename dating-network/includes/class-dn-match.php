<?php
if (!defined('ABSPATH')) { exit; }

class DN_Match
{
    public static function age(int $user_id): ?int
    {
        $dob=(string)get_user_meta($user_id,'dn_dob',true);
        if(!$dob){return null;}
        try{return (new DateTimeImmutable($dob))->diff(new DateTimeImmutable('today'))->y;}catch(Throwable $e){return null;}
    }

    public static function is_active(int $user_id): bool
    {
        $moderation=(string)get_user_meta($user_id,'dn_admin_moderation_status',true);
        if(in_array($moderation,['paused','blocked'],true)){return false;}
        $single=get_user_meta($user_id,'dn_single_confirmed',true)==='1'||get_user_meta($user_id,'dn_is_single',true)==='1';
        return get_user_meta($user_id,'dn_email_verified',true)==='1'&&$single&&(string)get_user_meta($user_id,'dn_profile_status',true)==='active';
    }

    public static function eligible(int $viewer,int $candidate): bool
    {
        if($viewer===$candidate||!self::is_active($viewer)||!self::is_active($candidate)||self::blocked_between($viewer,$candidate)){return false;}
        $vg=(string)get_user_meta($viewer,'dn_gender',true);$cg=(string)get_user_meta($candidate,'dn_gender',true);
        if(!in_array($vg,['male','female'],true)||!in_array($cg,['male','female'],true)||$vg===$cg){return false;}
        $va=self::age($viewer);$ca=self::age($candidate);if(!$va||!$ca||$va<18||$ca<18){return false;}
        $vmin=max(18,(int)get_user_meta($viewer,'dn_age_min',true));$vmax=max($vmin,(int)get_user_meta($viewer,'dn_age_max',true));
        $cmin=max(18,(int)get_user_meta($candidate,'dn_age_min',true));$cmax=max($cmin,(int)get_user_meta($candidate,'dn_age_max',true));
        if($ca<$vmin||$ca>$vmax||$va<$cmin||$va>$cmax){return false;}
        $vhas=(string)get_user_meta($viewer,'dn_children_status',true)==='has';$chas=(string)get_user_meta($candidate,'dn_children_status',true)==='has';
        if(($chas&&get_user_meta($viewer,'dn_open_partner_children',true)==='no')||($vhas&&get_user_meta($candidate,'dn_open_partner_children',true)==='no')){return false;}
        $vw=(string)get_user_meta($viewer,'dn_children_wish',true);$cw=(string)get_user_meta($candidate,'dn_children_wish',true);
        if(($vw==='yes'&&$cw==='no')||($vw==='no'&&$cw==='yes')){return false;}
        $vr=(string)get_user_meta($viewer,'dn_religion',true);$cr=(string)get_user_meta($candidate,'dn_religion',true);
        $vi=(string)get_user_meta($viewer,'dn_religion_importance',true);$ci=(string)get_user_meta($candidate,'dn_religion_importance',true);
        $vo=(string)get_user_meta($viewer,'dn_open_other_religion',true);$co=(string)get_user_meta($candidate,'dn_open_other_religion',true);
        if($vr&&$cr&&$vr!=='prefer_not'&&$cr!=='prefer_not'&&$vr!==$cr&&($vi==='must'||$ci==='must'||$vo==='no'||$co==='no')){return false;}
        return true;
    }

    public static function score(int $a,int $b): array
    {
        if(!self::eligible($a,$b)){return ['score'=>0,'reasons'=>[],'shared_interests'=>[]];}
        $earned=0.0;$possible=0.0;$reasons=[];$shared=[];
        $aa=self::age($a)?:18;$ba=self::age($b)?:18;
        $earned+=10*((self::range_fit($ba,(int)get_user_meta($a,'dn_age_min',true),(int)get_user_meta($a,'dn_age_max',true))+self::range_fit($aa,(int)get_user_meta($b,'dn_age_min',true),(int)get_user_meta($b,'dn_age_max',true)))/2);$possible+=10;$reasons[]='Leeftijd past wederzijds';
        $goal=self::goal_similarity((string)get_user_meta($a,'dn_relationship_goal',true),(string)get_user_meta($b,'dn_relationship_goal',true));$earned+=20*$goal;$possible+=20;if($goal>=.7){$reasons[]='Jullie zoeken ongeveer hetzelfde';}
        [$kids,$kids_reason]=self::children_score($a,$b);$earned+=20*$kids;$possible+=20;if($kids_reason){$reasons[]=$kids_reason;}
        [$rel,$rel_reason]=self::religion_score($a,$b);$earned+=15*$rel;$possible+=15;if($rel_reason){$reasons[]=$rel_reason;}
        [$interest,$shared]=self::interest_score($a,$b);if($interest!==null){$earned+=20*$interest;$possible+=20;if($shared){$reasons[]=count($shared).' gedeelde interesse'.(count($shared)===1?'':'s');}}
        [$life,$life_reason]=self::lifestyle_score($a,$b);$earned+=5*$life;$possible+=5;if($life_reason){$reasons[]=$life_reason;}
        $citya=self::norm((string)get_user_meta($a,'dn_city',true));$cityb=self::norm((string)get_user_meta($b,'dn_city',true));if($citya&&$cityb){$possible+=10;if($citya===$cityb){$earned+=10;$reasons[]='Jullie wonen in dezelfde plaats';}else{$earned+=4;}}
        $score=$possible>0?(int)round(($earned/$possible)*100):0;
        return ['score'=>max(1,min(100,$score)),'reasons'=>array_slice(array_values(array_unique($reasons)),0,5),'shared_interests'=>array_slice($shared,0,12)];
    }

    public static function list_meta(int $id,string $key): array
    {
        $raw=get_user_meta($id,$key,true);if(is_array($raw)){return array_values(array_filter(array_map('strval',$raw)));}
        if(is_string($raw)&&$raw!==''){$d=json_decode($raw,true);if(is_array($d)){return array_values(array_filter(array_map('strval',$d)));}}
        return [];
    }

    private static function range_fit(int $age,int $min,int $max): float{$min=max(18,$min);$max=max($min,$max);if($max===$min){return 1.0;}$mid=($min+$max)/2;$half=max(1,($max-$min)/2);return max(.65,1-(abs($age-$mid)/$half)*.35);}
    private static function goal_similarity(string $a,string $b): float{if($a&&$a===$b){return 1.0;}$map=['serious'=>3,'slow_serious'=>2,'see_where'=>1];if(!isset($map[$a],$map[$b])){return .6;}return abs($map[$a]-$map[$b])===1?.72:.38;}
    private static function children_score(int $a,int $b): array{$wa=(string)get_user_meta($a,'dn_children_wish',true);$wb=(string)get_user_meta($b,'dn_children_wish',true);$v=.6;if($wa&&$wb){$v=$wa===$wb?1:(in_array('maybe',[$wa,$wb],true)?.72:.25);}return [$v,$v>=.85?'Jullie kijk op kinderen sluit goed aan':''];}
    private static function religion_score(int $a,int $b): array{$ra=(string)get_user_meta($a,'dn_religion',true);$rb=(string)get_user_meta($b,'dn_religion',true);if(!$ra||!$rb||$ra==='prefer_not'||$rb==='prefer_not'){return [.65,''];}if($ra===$rb){return [1,'Jullie religie/levensovertuiging sluit aan'];}return [.55,''];}
    private static function interest_score(int $a,int $b): array{$ia=self::list_meta($a,'dn_interests');$ib=self::list_meta($b,'dn_interests');if(!$ia||!$ib){return [null,[]];}$na=array_map([self::class,'norm'],$ia);$nb=array_map([self::class,'norm'],$ib);$sharedn=array_values(array_intersect($na,$nb));$shared=[];foreach($sharedn as $n){$i=array_search($n,$na,true);if($i!==false){$shared[]=$ia[$i];}}$factor=count($sharedn)/max(1,min(count($na),count($nb)));return [min(1,.3+.7*$factor),$shared];}
    private static function lifestyle_score(int $a,int $b): array{$s=[];foreach(['dn_smoking','dn_alcohol'] as $k){$x=(string)get_user_meta($a,$k,true);$y=(string)get_user_meta($b,$k,true);if($x&&$y){$s[]=$x===$y?1:.6;}}if(!$s){return [.65,''];}$v=array_sum($s)/count($s);return [$v,$v>=.9?'Jullie leefstijl komt goed overeen':''];}
    private static function blocked_between(int $a,int $b): bool{global $wpdb;$t=$wpdb->prefix.'dn_blocks';return(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE (blocker_id=%d AND blocked_id=%d) OR (blocker_id=%d AND blocked_id=%d) LIMIT 1",$a,$b,$b,$a));}
    private static function norm(string $v): string{return strtolower(trim(remove_accents(wp_strip_all_tags($v))));}
}
