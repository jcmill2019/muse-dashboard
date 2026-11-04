<?php
/* Pose de la SENTINELLE REMISE v2 dans l'extrait #90 — 04/11/2026.
   Exécuté SUR LE SERVEUR par pose_sentinelle_remise_v2.sh.

   Chirurgical : on part du code de l'extrait #90 TEL QU'IL EST EN BASE (jamais de
   la copie locale, qui peut être périmée), on remplace UNIQUEMENT le bloc compris
   entre le bandeau « SENTINELLE REMISE » et le bandeau « MOISSONNEUSE D'IP », on
   vérifie la syntaxe du résultat (php -l), et seulement alors on écrit en base.
   Sauvegarde intégrale de l'ancien code avant toute écriture.

   Usages :
     php pose.php                       -> pose (avec sauvegarde + lint)
     php pose.php --restaurer FICHIER   -> remet le code sauvegardé (retour arrière)
   Mode test hors serveur (aucun accès base) :
     LAFG_TEST_CODE=/chemin/snippet-90.php php pose.php
*/

$BLOC    = '/home/jcm/sentinelle_remise_v2_bloc.php';
$SAUVE   = '/home/jcm/snippet90_sauvegarde_' . gmdate('Ymd_His') . '.php';
$LINT    = '/home/jcm/snippet90_v2_lint.php';
$ID      = 90;

function lafg_v2_assembler($code, $bloc){
  $posS = strpos($code, 'SENTINELLE REMISE');
  if($posS === false) return array(null, 'bandeau « SENTINELLE REMISE » introuvable dans le #90');
  if(strpos($code, 'SENTINELLE REMISE v2') !== false) return array(null, 'la v2 est DEJA posee');
  $debut = strrpos(substr($code, 0, $posS), '/*');
  if($debut === false) return array(null, 'debut du bandeau SENTINELLE introuvable');
  $posM = strpos($code, "MOISSONNEUSE D'IP");
  if($posM === false) return array(null, "bandeau « MOISSONNEUSE D'IP » introuvable dans le #90");
  $fin = strrpos(substr($code, 0, $posM), '/*');
  if($fin === false || $fin <= $debut) return array(null, 'bornes du bloc incoherentes');
  /* Le bloc remplacé doit contenir ce qu'on attend : sinon on ne touche à rien. */
  $ancien = substr($code, $debut, $fin - $debut);
  foreach(array('lafg_lag_sentinelle', 'lafg_lag_mesure', 'lafg_lag_destinataires', 'lafg_lag_silence') as $attendu){
    if(strpos($ancien, $attendu) === false) return array(null, "le bloc a remplacer ne contient pas « $attendu » : code inattendu, abandon");
  }
  $bloc = preg_replace('/^<\?php\s*/', '', $bloc, 1);
  return array(substr($code, 0, $debut) . rtrim($bloc) . "\n\n" . substr($code, $fin), null);
}

function lafg_v2_lint($chemin){
  if(!function_exists('exec')) return array(false, 'exec() indisponible : lint impossible');
  $out = array(); $rc = 1;
  exec('php -l ' . escapeshellarg($chemin) . ' 2>&1', $out, $rc);
  return array($rc === 0, implode("\n", $out));
}

$test = getenv('LAFG_TEST_CODE');
if($test){
  /* Mode test : assemble à partir d'un fichier et lint, sans base ni écriture en prod. */
  $code = file_get_contents($test);
  $code = preg_replace('/^<\?php\s*/', '', $code, 1);
  $bloc = file_get_contents(getenv('LAFG_TEST_BLOC') ?: __DIR__ . '/bloc_sentinelle_remise_v2.php');
  list($nouveau, $err) = lafg_v2_assembler($code, $bloc);
  if($err){ echo "TEST : $err\n"; exit(1); }
  $tmp = sys_get_temp_dir() . '/snippet90_v2_test.php';
  file_put_contents($tmp, "<?php\n" . $nouveau);
  list($ok, $msg) = lafg_v2_lint($tmp);
  echo "TEST assemblage : " . strlen($code) . " -> " . strlen($nouveau) . " caracteres\n";
  echo "TEST lint : " . ($ok ? 'OK' : 'ECHEC') . " — $msg\n";
  echo "Resultat assemble : $tmp\n";
  exit($ok ? 0 : 1);
}

require '/var/www/lafourgale/wp-load.php';
global $wpdb;
$t = $wpdb->prefix . 'snippets';

if(isset($argv[1]) && $argv[1] === '--restaurer'){
  $f = isset($argv[2]) ? $argv[2] : '';
  if($f === '' || !is_readable($f)){ echo "Fichier de sauvegarde illisible.\n"; exit(1); }
  $code = preg_replace('/^<\?php\s*/', '', file_get_contents($f), 1);
  if(strlen($code) < 5000){ echo "Sauvegarde trop courte, abandon.\n"; exit(1); }
  $wpdb->update($t, array('code' => $code), array('id' => $ID), array('%s'), array('%d'));
  wp_cache_flush();
  echo "Extrait #$ID restaure depuis $f (" . strlen($code) . " caracteres). Cache objet purge.\n";
  exit(0);
}

$row = $wpdb->get_row($wpdb->prepare("SELECT id, name, code, active FROM {$t} WHERE id = %d", $ID));
if(!$row){ echo "Extrait #$ID introuvable en base.\n"; exit(1); }
echo "Extrait #$ID : « {$row->name} » — actif=" . (int)$row->active . " — " . strlen($row->code) . " caracteres.\n";

$bloc = file_get_contents($BLOC);
if($bloc === false || strlen($bloc) < 3000){ echo "Bloc v2 illisible ($BLOC).\n"; exit(1); }

/* 1. Sauvegarde INTEGRALE avant toute chose. */
file_put_contents($SAUVE, "<?php\n" . $row->code);
echo "Sauvegarde de l'ancien code : $SAUVE\n";

/* 2. Assemblage. */
list($nouveau, $err) = lafg_v2_assembler($row->code, $bloc);
if($err){ echo "ABANDON : $err\n"; exit(1); }
echo "Assemblage : " . strlen($row->code) . " -> " . strlen($nouveau) . " caracteres.\n";

/* 3. Lint du resultat complet. */
file_put_contents($LINT, "<?php\n" . $nouveau);
list($ok, $msg) = lafg_v2_lint($LINT);
echo "Lint : " . ($ok ? 'OK' : 'ECHEC') . " — $msg\n";
if(!$ok){ echo "ABANDON : rien n'a ete ecrit en base.\n"; exit(1); }

/* 4. Ecriture en base + purge du cache objet (Redis) pour que Code Snippets recharge. */
$res = $wpdb->update($t, array('code' => $nouveau), array('id' => $ID), array('%s'), array('%d'));
if($res === false){ echo "ECHEC de l'ecriture en base : " . $wpdb->last_error . "\n"; exit(1); }
wp_cache_flush();

/* 5. Relecture. */
$relu = (string)$wpdb->get_var($wpdb->prepare("SELECT code FROM {$t} WHERE id = %d", $ID));
if(strpos($relu, 'SENTINELLE REMISE v2') === false){ echo "ATTENTION : relecture sans la v2 — restaurer : php pose.php --restaurer $SAUVE\n"; exit(1); }
echo "POSE OK. Retour arriere si besoin : php " . basename(__FILE__) . " --restaurer $SAUVE\n";
@unlink($LINT);
