<?php
/* ════════════════════════════════════════════════════════════════════════
   SENTINELLE REMISE v2 (04/11/2026 — demande JC : « je ne suis pas alerte et
   les agents se plaignent que les clients ne recoivent pas les emails »).
   Remplace le bloc du 19/08 (entre ce bandeau et celui de la MOISSONNEUSE D'IP).
   Surveille la remise de l'INVITATION (email 1, lien vers le Barometre) envoyee
   par le #18 a la creation du dossier dans /lp-saisie (meta _lafg_sent_at).

   CE QUI CHANGE PAR RAPPORT A LA v1 :
   - fenetre d'ENVOI d'une heure (au lieu de 20 min) et seuil de volume abaisse a
     8 invitations (au lieu de 15) : le plateau en envoie rarement 15 en 20 min,
     la v1 ne jugeait donc presque jamais ;
   - alerte aussi sur les ECHECS AU DEPART (_lafg_send_ok = 0 : le relais SMTP a
     refuse le message, l'agent a vu « email NON envoye ») des 3 echecs dans
     l'heure, quel que soit le volume ;
   - seuil de remise 60 % (au lieu de 50 %), critique sous 30 % ;
   - repli sans FORCE INDEX si l'index lafg_tel9 manque (deja disparu le 11/08) :
     plus jamais un zero silencieux, l'echec de mesure est signale ;
   - chaque passage ecrit son verdict dans l'option lafg_remise_etat : la ronde
     Cowork lit cet etat par HTTPS et previent JC par notification push, HORS du
     canal email (une panne d'email emportait l'alerte email avec elle) ;
   - point de lecture HTTPS (lecture seule, secret de la sentinelle admins) :
     admin-ajax.php?action=lafg_sentinelle_remise&s=<option lafg_sentinelle_secret>
   INCHANGE : cadence 5 min (hook lafg_lag_sentinelle), silence 45 min, transient
   lafg_lag_silence lu par le tableau de bord #9, destinataires DP + JC.
   ════════════════════════════════════════════════════════════════════════ */
add_filter('cron_schedules', function($s){
  if(!isset($s['lafg_5min'])) $s['lafg_5min'] = array('interval'=>300,'display'=>'Toutes les 5 minutes (LaFourgale)');
  return $s;
});
add_action('init', function(){
  if(!wp_next_scheduled('lafg_lag_sentinelle')) wp_schedule_event(time()+180,'lafg_5min','lafg_lag_sentinelle');
});

if(!function_exists('lafg_remise_fournisseur')){
/* Famille du fournisseur de messagerie d'une adresse — pour reperer un blocage
   localise (reputation chez Orange, Gmail…) sans jamais exposer l'adresse. */
function lafg_remise_fournisseur($email){
  $arobase = strrchr(strtolower((string)$email), '@');
  $d = ($arobase === false) ? '' : substr($arobase, 1);
  if($d === '') return 'autres';
  $familles = array(
    'gmail'     => array('gmail.com','googlemail.com'),
    'orange'    => array('orange.fr','wanadoo.fr'),
    'free'      => array('free.fr','aliceadsl.fr'),
    'sfr'       => array('sfr.fr','neuf.fr','club-internet.fr','cegetel.net'),
    'bouygues'  => array('bbox.fr','numericable.fr'),
    'laposte'   => array('laposte.net'),
    'microsoft' => array('hotmail.fr','hotmail.com','outlook.fr','outlook.com','live.fr','live.com','msn.com'),
    'yahoo'     => array('yahoo.fr','yahoo.com','ymail.com'),
    'apple'     => array('icloud.com','me.com','mac.com'),
  );
  foreach($familles as $nom => $domaines){ if(in_array($d, $domaines, true)) return $nom; }
  return 'autres';
}}

if(!function_exists('lafg_remise_mesure')){
/* Mesure sur une fenetre d'ENVOI qui se termine a $fin_epoch (UTC) et dure
   $duree secondes. Les metas _lafg_sent_at sont en heure LOCALE du site
   (current_time), le journal Brevo en UTC : les deux bornes sont derivees du
   meme instant, quel que soit le fuseau regle dans WordPress.
   Renvoie les compteurs bruts, ou array('erreur'=>...) si la mesure a echoue. */
function lafg_remise_mesure($fin_epoch, $duree){
  global $wpdb;
  $T = $wpdb->prefix . 'lafg_evts_brevo';
  $decal = (int)(current_time('timestamp') - time());
  $debL = gmdate('Y-m-d H:i:s', $fin_epoch - $duree + $decal);
  $finL = gmdate('Y-m-d H:i:s', $fin_epoch + $decal);
  $debU = gmdate('Y-m-d H:i:s', $fin_epoch - $duree);
  $finU = gmdate('Y-m-d H:i:s', $fin_epoch);

  $sql = "SELECT s.user_id, u.user_email, ok.meta_value AS send_ok
      FROM {$wpdb->usermeta} s %FORCE%
      INNER JOIN {$wpdb->users} u ON u.ID = s.user_id
      LEFT JOIN {$wpdb->usermeta} ok ON ok.user_id = s.user_id AND ok.meta_key = '_lafg_send_ok'
      WHERE s.meta_key = '_lafg_sent_at' AND s.meta_value >= %s AND s.meta_value < %s
        AND u.user_email NOT LIKE '%%@sans-email.%%'";
  $wpdb->last_error = '';
  $rows = $wpdb->get_results($wpdb->prepare(str_replace('%FORCE%', 'FORCE INDEX (lafg_tel9)', $sql), $debL, $finL));
  if($wpdb->last_error !== ''){
    /* Filet : l'index lafg_tel9 a deja disparu une fois (11/08). On retente sans
       le forcer ; si cela echoue encore, on le DIT au lieu de compter zero. */
    $wpdb->last_error = '';
    $rows = $wpdb->get_results($wpdb->prepare(str_replace('%FORCE%', '', $sql), $debL, $finL));
    if($wpdb->last_error !== '') return array('erreur' => 'mesure impossible : ' . mb_substr($wpdb->last_error, 0, 200));
  }
  if(!is_array($rows)) $rows = array();

  $ids = array(); $echecs = 0; $parF = array();
  foreach($rows as $r){
    $uid = (int)$r->user_id; $ids[$uid] = true;
    $f = lafg_remise_fournisseur($r->user_email);
    if(!isset($parF[$f])) $parF[$f] = array('envoyes'=>0,'remis'=>0,'rejetes'=>0);
    $parF[$f]['envoyes']++;
    if((string)$r->send_ok === '0') $echecs++;
  }
  $nEnv = count($ids);
  $nDel = 0; $rejets = array(); $motifs = array(); $nRej = 0;
  if($nEnv){
    $in = implode(',', array_map('intval', array_keys($ids)));
    $wpdb->last_error = '';
    $del = $wpdb->get_results("SELECT DISTINCT user_id, email FROM $T WHERE evt = 'delivered' AND ts >= '" . esc_sql($debU) . "' AND user_id IN ($in)");
    if($wpdb->last_error !== '') return array('erreur' => 'journal Brevo illisible : ' . mb_substr($wpdb->last_error, 0, 200));
    $vus = array();
    foreach((array)$del as $d){
      $uid = (int)$d->user_id; if(isset($vus[$uid])) continue; $vus[$uid] = true; $nDel++;
      $f = lafg_remise_fournisseur($d->email);
      if(!isset($parF[$f])) $parF[$f] = array('envoyes'=>0,'remis'=>0,'rejetes'=>0);
      $parF[$f]['remis']++;
    }
    $rej = $wpdb->get_results("SELECT user_id, email, evt, motif FROM $T
      WHERE evt IN ('hard_bounce','soft_bounce','blocked','invalid_email','error') AND ts >= '" . esc_sql($debU) . "' AND user_id IN ($in)
      ORDER BY id DESC LIMIT 500");
    $vusR = array();
    foreach((array)$rej as $x){
      $uid = (int)$x->user_id;
      $e = (string)$x->evt;
      $rejets[$e] = isset($rejets[$e]) ? $rejets[$e] + 1 : 1;
      $m = trim((string)$x->motif); if($m !== ''){ $m = mb_substr($m, 0, 90); $motifs[$m] = isset($motifs[$m]) ? $motifs[$m] + 1 : 1; }
      if(isset($vusR[$uid])) continue; $vusR[$uid] = true; $nRej++;
      $f = lafg_remise_fournisseur($x->email);
      if(!isset($parF[$f])) $parF[$f] = array('envoyes'=>0,'remis'=>0,'rejetes'=>0);
      $parF[$f]['rejetes']++;
    }
    arsort($motifs); $motifs = array_slice($motifs, 0, 8, true);
  }
  $defer = (int)$wpdb->get_var("SELECT COUNT(*) FROM $T WHERE evt IN ('deferred','soft_bounce') AND ts >= '" . esc_sql($debU) . "'");
  /* Retard de remontee : moyenne (arrivee - evenement) des evenements INGERES ces 10
     dernieres minutes. NULL tant qu'aucun evenement recent n'est remonte. */
  $lagArr = $wpdb->get_var("SELECT ROUND(AVG(TIMESTAMPDIFF(SECOND, ts, recu_at))) FROM $T
    WHERE recu_at IS NOT NULL AND recu_at >= (UTC_TIMESTAMP() - INTERVAL 10 MINUTE) AND ts <= recu_at");
  ksort($parF);
  return array(
    'fenetre_utc'        => array('debut' => $debU, 'fin' => $finU),
    'envoyes'            => $nEnv,
    'echecs_depart'      => $echecs,
    'remis_visibles'     => $nDel,
    'taux_remise'        => $nEnv ? (int)round(100 * $nDel / $nEnv) : null,
    'rejetes'            => $nRej,
    'rejets_par_type'    => $rejets,
    'rejets_motifs'      => $motifs,
    'differes'           => $defer,
    'retard_remontee_s'  => ($lagArr === null ? null : (int)$lagArr),
    'par_fournisseur'    => $parF,
  );
}}

if(!function_exists('lafg_remise_verdict')){
/* Verdict sur une mesure d'UNE HEURE. Memes seuils que la ronde Cowork.
   Renvoie array('niveau' => normal|trop_tot|calme|degrade|critique|erreur, 'motif' => ...). */
function lafg_remise_verdict($m){
  if(isset($m['erreur'])) return array('niveau'=>'erreur', 'motif'=>$m['erreur']);
  $n = (int)$m['envoyes'];
  if($n === 0) return array('niveau'=>'calme', 'motif'=>'aucune invitation envoyee dans la fenetre');
  if($m['echecs_depart'] >= 3 || ($n >= 5 && $m['echecs_depart'] / $n >= 0.3)){
    return array('niveau'=>'critique', 'motif'=>$m['echecs_depart'] . ' refus du relais SMTP au depart sur ' . $n . ' invitations (voir console /lp-saisie/?lafg_wa_setup=1)');
  }
  if($n < 8) return array('niveau'=>'trop_tot', 'motif'=>$n . ' invitations seulement : volume insuffisant pour juger');
  $ratio = $m['remis_visibles'] / $n;
  $remonteeLente = ($m['retard_remontee_s'] !== null && $m['retard_remontee_s'] > 600);
  if($ratio < 0.3) return array('niveau'=>'critique', 'motif'=>(int)round(100 * $ratio) . ' % remis (' . $m['remis_visibles'] . '/' . $n . ')');
  if($ratio < 0.6) return array('niveau'=>'degrade', 'motif'=>(int)round(100 * $ratio) . ' % remis (' . $m['remis_visibles'] . '/' . $n . ')');
  if($remonteeLente) return array('niveau'=>'degrade', 'motif'=>'remontee webhook lente : ' . $m['retard_remontee_s'] . ' s');
  return array('niveau'=>'normal', 'motif'=>(int)round(100 * $ratio) . ' % remis (' . $m['remis_visibles'] . '/' . $n . ')');
}}

if(!function_exists('lafg_lag_destinataires')){
/* Encadrement a prevenir en cas d'alerte : les seuls DIRECTEURS DE PRODUCTION
   (decision JC 03/09). JAMAIS les agents. Adresses fictives @sans-email et comptes
   de TEST (directeur1/2@mail.com) ecartes, + adjonctions manuelles de l'option
   lafg_lag_alerte_emails (virgules). Repli : JC seul. */
function lafg_lag_destinataires(){
  global $wpdb;
  $dest = array('jcmillancourt@gmail.com');
  $capkey = $wpdb->prefix . 'capabilities';
  $exclus = array('directeur1@mail.com', 'directeur2@mail.com');
  $ids = $wpdb->get_col($wpdb->prepare(
    "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
    $capkey, '%"directeur-production"%'));
  if($ids){
    $ph = implode(',', array_map('intval', $ids));
    foreach($wpdb->get_col("SELECT user_email FROM {$wpdb->users} WHERE ID IN ($ph)") as $em){
      $em = strtolower(trim((string)$em));
      if($em !== '' && strpos($em, '@sans-email.') === false && !in_array($em, $exclus, true)) $dest[] = $em;
    }
  }
  foreach(explode(',', (string)get_option('lafg_lag_alerte_emails')) as $em){
    $em = strtolower(trim($em)); if($em !== '' && !in_array($em, $exclus, true)) $dest[] = $em;
  }
  return array_values(array_unique($dest));
}}

/* Le passage toutes les 5 minutes : mesure de l'heure ecoulee (3 min de grace pour
   les remises en vol), verdict, etat ecrit pour la ronde Cowork, alerte email si
   degrade/critique (silence 45 min ensuite). */
add_action('lafg_lag_sentinelle', function(){
  $fin = time() - 180;
  $m = lafg_remise_mesure($fin, 3600);
  $v = lafg_remise_verdict($m);
  $silence = (bool)get_transient('lafg_lag_silence');
  $alerter = in_array($v['niveau'], array('degrade','critique','erreur'), true) && !$silence;
  update_option('lafg_remise_etat', array(
    'evalue_le'      => gmdate('Y-m-d H:i:s') . ' UTC',
    'niveau'         => $v['niveau'],
    'motif'          => $v['motif'],
    'mesure'         => $m,
    'alerte_envoyee' => $alerter,
    'silence_actif'  => $silence || $alerter,
  ), false);
  if(!$alerter) return;
  set_transient('lafg_lag_silence', 1, 45 * MINUTE_IN_SECONDS);
  $dest = lafg_lag_destinataires();
  $copie = array_values(array_diff($dest, array('jcmillancourt@gmail.com')));
  $entetes = $copie ? array('Bcc: ' . implode(', ', $copie)) : array();
  $libelle = array('degrade'=>'DEGRADE', 'critique'=>'CRITIQUE', 'erreur'=>'MESURE IMPOSSIBLE');
  $corps = "Niveau : " . $libelle[$v['niveau']] . " — " . $v['motif'] . "\n";
  if(!isset($m['erreur'])){
    $corps .= "Fenetre d'envoi surveillee (UTC) : {$m['fenetre_utc']['debut']} -> {$m['fenetre_utc']['fin']}\n"
      . "Invitations envoyees (hors adresses fictives) : {$m['envoyes']}\n"
      . "Refus du relais SMTP au depart              : {$m['echecs_depart']}\n"
      . "Remises VISIBLES au journal Brevo           : {$m['remis_visibles']} (" . (int)$m['taux_remise'] . " %)\n"
      . "Rejets (bounce, blocage, adresse invalide)  : {$m['rejetes']}\n"
      . "Differes / soft bounces depuis le debut     : {$m['differes']}\n"
      . "Retard de remontee webhook (10 dernieres min) : " . ($m['retard_remontee_s'] === null ? 'pas encore mesurable' : $m['retard_remontee_s'] . ' s') . "\n";
    if($m['par_fournisseur']){
      $corps .= "Par fournisseur (envoyes / remis / rejetes) :\n";
      foreach($m['par_fournisseur'] as $f => $c){ $corps .= "  - $f : {$c['envoyes']} / {$c['remis']} / {$c['rejetes']}\n"; }
    }
    if($m['rejets_motifs']){
      $corps .= "Motifs de rejet les plus frequents :\n";
      foreach($m['rejets_motifs'] as $mo => $c){ $corps .= "  - ($c) $mo\n"; }
    }
  }
  $corps .= "\nLecture :\n"
    . "- refus au DEPART -> le relais SMTP refuse nos messages (identifiants Brevo, quota, relais) :\n"
    . "  console /lp-saisie/?lafg_wa_setup=1, bloc « Canal d'envoi des emails ».\n"
    . "- retard de remontee GRAND -> la remise est sans doute normale, c'est le webhook qui traine.\n"
    . "- retard PETIT avec un taux bas -> remise reellement en panne : https://status.brevo.com, rejets et differes.\n"
    . "- un seul fournisseur a 0 remis -> blocage chez ce fournisseur (reputation), pas chez nous.\n\n"
    . "Prochaine alerte email au plus tot dans 45 min (silence anti-rafale). La ronde Cowork horaire\n"
    . "lit le meme etat par HTTPS et previent JC par notification push, meme si cet email ne part pas.\n"
    . "Destinataires : Directeurs de Production, " . count($dest) . " adresse(s).";
  wp_mail('jcmillancourt@gmail.com',
    '[LaFourgale] Alerte remise invitations : ' . $libelle[$v['niveau']] . ' — ' . $v['motif'],
    $corps, $entetes);
});

/* ---------- le point de lecture HTTPS pour la ronde Cowork (lecture seule) ----------
   Meme secret que la sentinelle admins (option lafg_sentinelle_secret, rotatable). */
if(!function_exists('lafg_sentinelle_remise_ajax')){
function lafg_sentinelle_remise_ajax(){
  $secret = (string)get_option('lafg_sentinelle_secret');
  $s = isset($_REQUEST['s']) ? (string)$_REQUEST['s'] : '';
  if($secret === '' || !hash_equals($secret, $s)) wp_send_json_error(array('erreur'=>'acces refuse'), 403);
  $now = time();
  $tzP = new DateTimeZone('Europe/Paris');
  $heure = lafg_remise_mesure($now - 180, 3600);
  $vh = lafg_remise_verdict($heure);
  $heure['niveau'] = $vh['niveau']; $heure['motif'] = $vh['motif'];
  $debJour = (new DateTime('today', $tzP))->getTimestamp();
  $dureeJour = max(60, ($now - 180) - $debJour);
  $journee = lafg_remise_mesure($now - 180, $dureeJour);
  $prochain = wp_next_scheduled('lafg_lag_sentinelle');
  $brevoPret = (get_option('lafg_envoi_brevo') === '1'
                && trim((string)get_option('lafg_brevo_smtp_login')) !== ''
                && trim((string)get_option('lafg_brevo_smtp_cle')) !== '');
  wp_send_json_success(array(
    'genere_le'             => gmdate('Y-m-d H:i:s') . ' UTC',
    'heure_paris'           => (new DateTime('now', $tzP))->format('Y-m-d H:i:s'),
    'canal'                 => $brevoPret ? 'brevo' : 'local',
    'dernier_echec_smtp'    => (string)get_option('lafg_envoi_dernier_echec'),
    'dernier_test_smtp'     => (string)get_option('lafg_envoi_dernier_test'),
    'silence_actif'         => (bool)get_transient('lafg_lag_silence'),
    'cron_prochain_passage' => $prochain ? gmdate('Y-m-d H:i:s', $prochain) . ' UTC' : null,
    'derniere_evaluation'   => get_option('lafg_remise_etat') ?: null,
    'derniere_heure'        => $heure,
    'journee'               => $journee,
    'seuils'                => array('volume_min'=>8, 'remise_degrade_pct'=>60, 'remise_critique_pct'=>30,
                                     'echecs_depart_critique'=>3, 'remontee_lente_s'=>600, 'silence_min'=>45),
  ));
}}
add_action('wp_ajax_lafg_sentinelle_remise', 'lafg_sentinelle_remise_ajax');
add_action('wp_ajax_nopriv_lafg_sentinelle_remise', 'lafg_sentinelle_remise_ajax');
