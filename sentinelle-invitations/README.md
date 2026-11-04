# Sentinelle invitations Baromètre — correctif v2 (04/11/2026)

Dossier de livraison du correctif de la sentinelle de remise des e-mails d'invitation
(email 1, lien vers le Baromètre) envoyés depuis `/lp-saisie` sur lafourgale.club.

> **Emplacement définitif** : ces fichiers appartiennent au dépôt privé Signial.net, dossier
> `LAFRGL/Snippets WP/`. Ils sont livrés ici parce que la session a été ouverte sur
> muse-dashboard. Ce dépôt est **public** : aucun secret n'y figure, volontairement.

## 1. Diagnostic (résumé)

La sentinelle vit dans l'extrait WordPress **#90** (webhook Brevo), bloc « SENTINELLE REMISE »
du 19/08/2026. Son état est affiché dans l'onglet « Envois en direct » du tableau de bord
`/lp-saisie` (extrait #09). Quatre raisons expliquent le silence :

| Cause | Effet |
|---|---|
| Seuil de 15 invitations par fenêtre de 20 min | En dessous de 45 envois/heure, elle ne juge jamais rien |
| Alerte envoyée par `wp_mail`, le canal surveillé | Une panne d'envoi emporte l'alerte avec elle |
| Alerte seulement sous 50 % de remise | Blocage partiel chez un fournisseur ou spam : invisible |
| `FORCE INDEX (lafg_tel9)` sans repli | Index absent (déjà arrivé le 11/08) = zéro silencieux |

## 2. Contenu du correctif

| Fichier | Rôle |
|---|---|
| `bloc_sentinelle_remise_v2.php` | Nouveau bloc « SENTINELLE REMISE v2 » qui remplace l'ancien dans le #90 |
| `pose_sentinelle_remise_v2.php` | Script serveur : sauvegarde, remplacement chirurgical du bloc, lint, écriture, purge Redis, retour arrière |
| `pose_sentinelle_remise_v2.sh` | Lanceur local (même mécanique que `pose_snippet_kpi_20260829.sh`) |
| `ROUTINE_COWORK_ronde_invitations.md` | Prompt et horaire de la tâche planifiée Cowork (canal push) |

Ce que fait la v2 :

- fenêtre d'envoi d'**une heure**, seuil de volume **8** invitations ;
- **échecs au départ** (`_lafg_send_ok = 0`, refus du relais SMTP) : critique dès 3 dans l'heure, quel que soit le volume ;
- dégradé sous **60 %** de remise, critique sous **30 %**, dégradé si remontée webhook > 600 s ;
- **repli** sans `FORCE INDEX` et signalement explicite si la mesure échoue ;
- **par fournisseur** (gmail, orange, free, sfr, bouygues, laposte, microsoft, yahoo, apple, autres) et motifs de rejet, sans adresses ;
- verdict écrit à chaque passage dans l'option `lafg_remise_etat` ;
- **point de lecture HTTPS** `admin-ajax.php?action=lafg_sentinelle_remise&s=<secret>` (même secret que la sentinelle admins, option `lafg_sentinelle_secret`), consommé par la ronde Cowork ;
- inchangé : cadence 5 min (`lafg_lag_sentinelle`), silence 45 min, transient `lafg_lag_silence` lu par le #09, destinataires DP + JC.

## 3. Pose (depuis le poste de JC, alias SSH `lafourgale`)

```bash
bash pose_sentinelle_remise_v2.sh
```

Le script serveur part du code du #90 **tel qu'il est en base** (pas de la copie locale), sauvegarde
l'ancien code dans `/home/jcm/snippet90_sauvegarde_<horodatage>.php`, ne remplace que le bloc compris
entre les bandeaux « SENTINELLE REMISE » et « MOISSONNEUSE D'IP », vérifie que ce bloc contient bien
les repères attendus, lint le résultat complet, puis seulement écrit en base et purge le cache objet.
Si une étape échoue, rien n'est écrit.

Retour arrière :

```bash
ssh lafourgale 'php /home/jcm/pose_sentinelle_remise_v2.php --restaurer /home/jcm/snippet90_sauvegarde_<horodatage>.php'
```

(le script de pose est effacé du serveur à la fin du lanceur : le renvoyer avec `scp` avant de restaurer).

## 4. Recette après pose

1. `curl -s 'https://lafourgale.club/wp-admin/admin-ajax.php?action=lafg_sentinelle_remise&s=<secret>'`
   doit renvoyer `{"success":true,...}` avec `derniere_heure.niveau` et `journee.envoyes`.
2. Dans les 5 minutes, `derniere_evaluation.evalue_le` se remplit : le cron tourne.
3. La page `https://lafourgale.club/` répond normalement ; l'onglet « Envois en direct » de `/lp-saisie` s'affiche.
4. Activer la tâche planifiée Cowork « Sentinelle invitations — ronde horaire » (créée désactivée).
5. Mettre à jour la copie de référence `LAFRGL/Snippets WP/snippet-90_brevo_webhook.php` dans Signial.net
   depuis le code en base, et consigner l'évolution dans le Blog Direction (consigne JC du 24/09/2026).

## 5. Tests réalisés hors serveur

- `php -l` sur le bloc et sur le script de pose : aucune erreur.
- Simulation de l'assemblage sur la copie du #90 du dépôt (`LAFG_TEST_CODE=... php pose_sentinelle_remise_v2.php`) :
  bloc remplacé aux bonnes bornes, moissonneuse d'IP intacte, résultat complet sans erreur de syntaxe.
- Verdicts vérifiés sur neuf cas (calme, trop tôt, échecs au départ, normal, dégradé, critique, remontée lente, erreur).

Non testé ici, faute d'accès au serveur : les requêtes SQL contre la base de production. Le lanceur
contrôle la réponse du site juste après la pose, et le retour arrière est immédiat.
