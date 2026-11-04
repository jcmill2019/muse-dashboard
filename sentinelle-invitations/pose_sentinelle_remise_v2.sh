#!/bin/bash
# Pose de la SENTINELLE REMISE v2 dans l'extrait #90 de lafourgale.club — 04/11/2026
# Même mécanique que pose_snippet_kpi_20260829.sh : transfert, lint, pose, contrôle vivant.
# Le script serveur sauvegarde l'ancien #90 et n'écrit rien en base si le lint échoue.
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"

echo "1/4 — Transfert et lint du bloc seul…"
scp "$DIR/bloc_sentinelle_remise_v2.php" lafourgale:/home/jcm/sentinelle_remise_v2_bloc.php
scp "$DIR/pose_sentinelle_remise_v2.php" lafourgale:/home/jcm/pose_sentinelle_remise_v2.php
ssh lafourgale "php -l /home/jcm/sentinelle_remise_v2_bloc.php"

echo "2/4 — Pose chirurgicale dans le #90 (sauvegarde + assemblage + lint + écriture + purge Redis)…"
ssh lafourgale "php /home/jcm/pose_sentinelle_remise_v2.php"

echo "3/4 — Contrôle vivant : la page d'accueil doit répondre normalement (200/301/302)…"
code=$(curl -s -o /dev/null -w "%{http_code}" "https://lafourgale.club/")
echo "→ HTTP $code"
case "$code" in 200|301|302) echo "→ Le site répond normalement." ;; *) echo "→ ATTENTION : code inattendu — restaurer aussitôt : ssh lafourgale 'php /home/jcm/pose_sentinelle_remise_v2.php --restaurer /home/jcm/snippet90_sauvegarde_<horodatage>.php'" ;; esac

echo "4/4 — Ménage des fichiers de transit (la sauvegarde snippet90_sauvegarde_*.php est CONSERVÉE)…"
ssh lafourgale "rm -f /home/jcm/sentinelle_remise_v2_bloc.php /home/jcm/pose_sentinelle_remise_v2.php"

echo
echo "Terminé. Contrôles :"
echo "  1. Point de lecture (remplacer SECRET par l'option lafg_sentinelle_secret) :"
echo "     curl -s 'https://lafourgale.club/wp-admin/admin-ajax.php?action=lafg_sentinelle_remise&s=SECRET' | head -c 1500"
echo "     → attendu : {\"success\":true,...} avec derniere_heure.niveau et journee.envoyes"
echo "  2. Dans les 5 minutes, derniere_evaluation.evalue_le doit se remplir (cron WordPress)."
echo "  3. Activer ensuite la tâche planifiée Cowork « Sentinelle invitations — ronde horaire »."
