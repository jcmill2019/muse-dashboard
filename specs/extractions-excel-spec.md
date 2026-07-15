# Spécification — Onglet « Extractions » (Tableau de bord Vos Productions)

Objectif : permettre aux managers de commander des extractions Excel de l'ensemble
des données de production, filtrées par période, équipe, agent et statut.

## 1. Emplacement & accès

- **Emplacement précis** : dans l'application front **lp-saisie**
  (`https://lafourgale.club/lp-saisie/`), onglet **« Tableau de bord »** —
  y ajouter un bouton/section **« Extractions »** ouvrant le panneau de
  sélecteurs (§2). Ce n'est PAS une page wp-admin : les utilisateurs
  concernés travaillent dans lp-saisie.
- Accès restreint : **administrateurs et directeurs de production uniquement**
  (rôles WordPress `administrator` et « Directeur production » — vérifier le
  slug exact du rôle). Les agents, coachs et managers ne voient pas le bouton.
  (Les données exportées contiennent des coordonnées clients complètes + IP
  → RGPD.)
- Chaque export est **journalisé** : qui a exporté, quand, avec quels filtres
  (table `export_log`).

## 2. Sélecteurs (filtres de l'extraction)

| Filtre | Type | Détail |
|---|---|---|
| Période | Date de début / date de fin | Bornage sur la date de création du dossier (« Créé le »). Presets : Aujourd'hui, 7 jours, 30 jours, Mois en cours, Tout. |
| Équipe | Liste déroulante multi-sélection | Liste des équipes existantes. Défaut : toutes. |
| Agent | Liste déroulante multi-sélection | Filtrée dynamiquement selon l'équipe choisie. Défaut : tous. |
| Statut qualifié | Multi-sélection | `Vente` / `Lead` / `Test`. Défaut : tous. |
| Statut produit | Multi-sélection | `Fourgale` / `Cigale`. Défaut : tous. |
| Mouvement | Multi-sélection | `Upgrade` / `Downgrade` / `Aucun`. Défaut : tous. |

Bouton **« Générer l'extraction »** → génération asynchrone si > 5 000 lignes
(voir §5), sinon téléchargement direct.

## 3. Colonnes du fichier Excel

### 3.1 Identification du dossier
| Colonne | Source |
|---|---|
| ID dossier | interne |
| Créé le (timestamp) | date/heure de création du dossier, TZ Europe/Paris |
| IP client à la création | IP publique du client au moment de l'envoi initial |
| Localisation à la création | ville + pays (géolocalisation IP) |
| Agent (créé par) | login/nom de l'agent |
| Manager | manager de l'agent au moment de la création |
| Équipe | équipe de l'agent |

### 3.2 Optin
| Colonne | Source |
|---|---|
| Optin : timestamp | date/heure du clic optin (« Ok Optin ») |
| Optin : IP | IP publique du client au moment de l'optin |
| Optin : localisation | ville + pays (géolocalisation IP) |

> ⚠️ Prérequis technique — **audit réalisé le 13/07/2026** sur une fiche
> client fraîche (user-edit.php, plugin EWD-SPP) :
>
> **Déjà stocké et affiché** : timestamp d'inscription, agent/manager/directeur
> rattachés, code sponsor, date de naissance, budget/abonnement (Fourgale /
> Cigale + montant), coordonnées de facturation WooCommerce (ville, CP,
> téléphone, email). Les pastilles de suivi (Envoyé, Reçu, Validation, clics
> Club/Shop/Baro, Ok Optin) sont affichées dans la console agents — leurs
> événements/timestamps existent donc en base.
>
> **ABSENT de la fiche (non capturé ou non affiché)** : IP à la création,
> géolocalisation à la création, IP à l'optin, géolocalisation à l'optin.
> Aucun champ `ip`/`geo`/`optin`/`consent`/`lafg_*` rendu sur le profil.
>
> **Conséquence** : le développement doit commencer par ajouter la capture
> IP + géolocalisation (résolution GeoIP côté serveur) aux deux événements
> (création du dossier, clic optin), stockée en usermeta ou table dédiée.
> Vérifier au préalable dans `wp_usermeta` / les tables du plugin si ces
> données existent déjà sans être affichées. Pour l'historique antérieur à
> la mise en production de la capture : colonnes à « n/d ».

### 3.3 Statuts & suivi (pastilles)
| Colonne | Valeurs |
|---|---|
| Statut qualifié | Vente / Lead / Test |
| Statut produit | Fourgale / Cigale (+ montant si applicable, ex. « Cigale 40 € ») |
| Mouvement | Upgrade / Downgrade / Aucun (+ date du mouvement) |
| Envoyé | oui/non + timestamp |
| Reçu | oui/non + timestamp |
| Validation | oui/non + timestamp |
| Clic Club | oui/non + timestamp |
| Clic Shop | oui/non + timestamp |
| Mot de passe créé | oui/non + timestamp |
| Baromètre | oui/non + timestamp |
| WhatsApp | oui/non + timestamp |
| Ok Optin | oui/non + timestamp |

### 3.4 Coordonnées client (complètes)
| Colonne |
|---|
| Civilité, Prénom, Nom |
| Email |
| Mobile |
| Date de naissance |
| Adresse postale complète (rue, CP, ville, pays) — selon champs disponibles |
| Code sponsor / parrain |
| Référence client (ex. `_673135687_Tt/`) |

## 4. Format du fichier

- **.xlsx** (une feuille « Données » + une feuille « Paramètres » rappelant
  les filtres utilisés, la date d'export et l'auteur).
- En-têtes figés (freeze panes ligne 1), auto-filtre activé sur toutes les colonnes.
- Encodage UTF-8, dates au format `JJ/MM/AAAA HH:MM:SS` (Europe/Paris).
- Nom du fichier : `extraction_production_YYYYMMDD_HHMM_<auteur>.xlsx`.
- Option secondaire : export CSV (même colonnes) pour intégrations tierces.

## 5. Génération asynchrone (gros volumes)

- Si > 5 000 lignes : job en file d'attente, notification (email ou in-app)
  avec lien de téléchargement à expiration (72 h).
- Les fichiers générés sont stockés dans un espace privé (non accessible
  publiquement), purgés automatiquement après 7 jours.

## 6. RGPD & sécurité

1. IP + géolocalisation = données personnelles. La capture à l'optin est
   légitime (preuve du consentement) ; la mentionner dans la politique de
   confidentialité.
2. Export réservé aux rôles manager/admin ; journal des exports conservé 12 mois.
3. Le fichier contient des données sensibles : bandeau d'avertissement dans
   l'UI avant téléchargement (« Ce fichier contient des données personnelles —
   ne pas le diffuser hors de l'entreprise »).
4. Purge automatique des fichiers générés (cf. §5).

## 7. Constats complémentaires (audit RGPD du 15/07/2026)

- L'export RGPD natif (Outils → Exporter les données personnelles) d'un client
  confirme : aucune donnée IP/géolocalisation/optin déclarée. Groupes présents :
  Compte, WooCommerce, E-mails (plugin Email Log v1.16.0).
- **Source « Envoyé » pour l'export** : le plugin Email Log trace chaque envoi
  (timestamp, destinataire, sujet, erreur). À utiliser comme source de la
  colonne « Envoyé » — MAIS son champ `error` ne reflète pas les rejets SMTP
  en aval (un envoi refusé par le relais peut apparaître `error: None`).
  Croiser avec l'Email Log Search de Google Workspace pour le statut réel.
- **Non-conformité RGPD à corriger** : le plugin EWD-SPP n'enregistre pas
  d'exporter/eraser RGPD WordPress — ses données (code sponsor, date de
  naissance, abonnement, événements de suivi) n'apparaissent pas dans les
  exports « droit d'accès ». Ajouter `wp_privacy_personal_data_exporters`
  + eraser au plugin.

## 8. Critères d'acceptation

- [ ] Un manager peut générer une extraction bornée du 01/07 au 13/07 pour
      une équipe donnée et obtenir un .xlsx conforme au §3.
- [ ] Les filtres se combinent (ET logique) ; « tous » par défaut.
- [ ] Les timestamps sont en heure de Paris ; les colonnes IP/localisation
      sont remplies pour tout nouveau dossier créé après la mise en production.
- [ ] Le bouton « Extractions » apparaît dans l'onglet Tableau de bord de
      lp-saisie pour un administrateur et un directeur de production.
- [ ] Un agent, un coach ou un manager connecté à lp-saisie ne voit PAS le
      bouton Extractions (ni ne peut appeler l'endpoint d'export directement —
      contrôle du rôle côté serveur, pas seulement masquage UI).
- [ ] Chaque export apparaît dans le journal avec auteur + filtres.
