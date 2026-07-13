#!/usr/bin/env bash
# Outil de saisie de secours — déclare un client Lafourgale via l'API REST.
# Remplace temporairement lp-saisie quand le site est indisponible.
#
# Usage :
#   1. Générer une clé opérationnelle (une fois par mois) :
#        LAFOURGALE_MASTER_KEY='<master key>' ./saisie.sh init
#   2. Saisir des clients (mode interactif, en boucle) :
#        ./saisie.sh
#
# Environnement ciblé : défini par API_BASE_URL (défaut = PRÉPROD).
#   Préprod (tests, emails désactivés) : https://lafourgale.serverweb-prod.com
#   Production : remplacer par l'URL donnée par Mouad, ex.
#        export API_BASE_URL='https://lafourgale.shop'
#
# La clé opérationnelle est stockée dans ~/.lafourgale_api_key (chmod 600).

set -euo pipefail

: "${API_BASE_URL:=https://lafourgale.serverweb-prod.com}"
KEY_FILE="$HOME/.lafourgale_api_key"

bold()  { printf '\033[1m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }
red()   { printf '\033[31m%s\033[0m\n' "$*"; }

if [ "${1:-}" = "init" ]; then
  : "${LAFOURGALE_MASTER_KEY:?Définissez LAFOURGALE_MASTER_KEY pour générer la clé}"
  bold "Génération d'une clé opérationnelle sur $API_BASE_URL ..."
  RESP=$(curl -sS -X POST "$API_BASE_URL/wp-json/ewd-spp/v1/api-key" \
    -H "X-Master-Key: $LAFOURGALE_MASTER_KEY" \
    -H 'Content-Type: application/json' \
    -d '{"label":"Outil saisie secours","expires_in_days":30}')
  KEY=$(printf '%s' "$RESP" | sed -n 's/.*"key":"\([^"]*\)".*/\1/p')
  if [ -z "$KEY" ]; then
    red "Échec de génération. Réponse du serveur :"; echo "$RESP"; exit 1
  fi
  printf '%s' "$KEY" > "$KEY_FILE"; chmod 600 "$KEY_FILE"
  green "Clé opérationnelle enregistrée dans $KEY_FILE (valable 30 jours)."
  exit 0
fi

if [ ! -f "$KEY_FILE" ]; then
  red "Aucune clé opérationnelle trouvée ($KEY_FILE)."
  echo "Lancez d'abord :  LAFOURGALE_MASTER_KEY='...' $0 init"
  exit 1
fi
API_KEY=$(cat "$KEY_FILE")

bold "=== Saisie Lafourgale (secours) — environnement : $API_BASE_URL ==="
echo "Ctrl+C pour quitter à tout moment."
echo

while true; do
  read -r -p "Email du client (obligatoire)      : " EMAIL
  [ -z "$EMAIL" ] && { red "Email obligatoire."; continue; }
  read -r -p "Prénom                              : " FIRST
  read -r -p "Nom                                 : " LAST
  read -r -p "Mobile (+33612345678, vide=aucun)   : " MOBILE
  read -r -p "Code postal (vide=aucun)            : " POSTCODE
  read -r -p "Code sponsor (vide=aucun)           : " SPONSOR
  read -r -p "Validation par email ou whatsapp ? [whatsapp] : " METHOD
  METHOD=${METHOD:-whatsapp}

  BODY="{\"user_email\":\"$EMAIL\""
  [ -n "$FIRST" ]    && BODY="$BODY,\"first_name\":\"$FIRST\""
  [ -n "$LAST" ]     && BODY="$BODY,\"last_name\":\"$LAST\""
  [ -n "$MOBILE" ]   && BODY="$BODY,\"user_mobile\":\"$MOBILE\""
  [ -n "$POSTCODE" ] && BODY="$BODY,\"postcode\":\"$POSTCODE\""
  [ -n "$SPONSOR" ]  && BODY="$BODY,\"sponsor_code\":\"$SPONSOR\""
  BODY="$BODY,\"verification_method\":\"$METHOD\"}"

  echo
  bold "Envoi de la déclaration..."
  HTTP_CODE=$(curl -sS -o /tmp/lafourgale_resp.json -w '%{http_code}' \
    -X POST "$API_BASE_URL/wp-json/ewd-spp/v1/create-client" \
    -H "X-API-Key: $API_KEY" \
    -H 'Content-Type: application/json' \
    -d "$BODY") || { red "Erreur réseau."; continue; }

  case "$HTTP_CODE" in
    200|201) green "✔ Client déclaré avec succès ($EMAIL)";;
    409)     red "✖ Déjà déclaré : un compte existe avec cet email.";;
    401)     red "✖ Clé API invalide ou expirée. Relancez : LAFOURGALE_MASTER_KEY='...' $0 init";;
    400)     red "✖ Données invalides (email mal formé ?).";;
    *)       red "✖ Erreur HTTP $HTTP_CODE";;
  esac
  echo "Réponse : $(cat /tmp/lafourgale_resp.json)"
  echo; echo "--- Client suivant ---"; echo
done
