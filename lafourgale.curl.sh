#!/usr/bin/env bash
# Workflow lafourgale en 2 étapes.
#
# Usage :
#   export API_BASE_URL='https://lafourgale.serverweb-prod.com'
#   export LAFOURGALE_MASTER_KEY='A9tboj7mQx5d0<ySl9-A54kZ8{Gzn.G['
#   ./lafourgale.curl.sh generate-key
#   export LAFOURGALE_API_KEY='prefix.secret...'   # valeur retournée
#   ./lafourgale.curl.sh create-client client@example.com '+33612345678'

set -euo pipefail

: "${API_BASE_URL:=https://lafourgale.serverweb-prod.com}"

case "${1:-}" in
  generate-key)
    : "${LAFOURGALE_MASTER_KEY:?manquant}"
    curl -sS -i -X POST "$API_BASE_URL/wp-json/ewd-spp/v1/api-key" \
      -H "X-Master-Key: $LAFOURGALE_MASTER_KEY" \
      -H 'Content-Type: application/json' \
      -d '{"label":"Test integration","expires_in_days":30}'
    ;;

  flow)
    # Tout-en-un : génère une clé opérationnelle puis crée le client.
    # Usage : ./lafourgale.curl.sh flow EMAIL [MOBILE] [METHOD]
    : "${LAFOURGALE_MASTER_KEY:?manquant}"
    EMAIL="${2:?email requis}"
    MOBILE="${3:-+33612345678}"
    METHOD="${4:-whatsapp}"
    KEY=$(curl -sS -X POST "$API_BASE_URL/wp-json/ewd-spp/v1/api-key" \
      -H "X-Master-Key: $LAFOURGALE_MASTER_KEY" \
      -H 'Content-Type: application/json' \
      -d '{"label":"Test integration","expires_in_days":30}' \
      | sed -n 's/.*"key":"\([^"]*\)".*/\1/p')
    if [ -z "$KEY" ]; then
      echo "Echec : impossible de generer la cle operationnelle." >&2
      exit 1
    fi
    echo "Cle operationnelle : $KEY"
    curl -sS -X POST "$API_BASE_URL/wp-json/ewd-spp/v1/create-client" \
      -H "X-API-Key: $KEY" \
      -H 'Content-Type: application/json' \
      -d "{
        \"user_email\": \"$EMAIL\",
        \"first_name\": \"Jean\",
        \"last_name\": \"Dupont\",
        \"user_mobile\": \"$MOBILE\",
        \"verification_method\": \"$METHOD\"
      }"
    echo ""
    ;;

  create-client)
    : "${LAFOURGALE_API_KEY:?manquant (utilisez generate-key d abord)}"
    EMAIL="${2:?email requis}"
    MOBILE="${3:-+33612345678}"
    METHOD="${4:-whatsapp}"
    curl -sS -i -X POST "$API_BASE_URL/wp-json/ewd-spp/v1/create-client" \
      -H "X-API-Key: $LAFOURGALE_API_KEY" \
      -H 'Content-Type: application/json' \
      -d "{
        \"user_email\": \"$EMAIL\",
        \"first_name\": \"Jean\",
        \"last_name\": \"Dupont\",
        \"user_mobile\": \"$MOBILE\",
        \"verification_method\": \"$METHOD\"
      }"
    ;;

  list-clients)
    : "${LAFOURGALE_API_KEY:?manquant}"
    SEARCH="${2:-}"
    curl -sS -i \
      -H "X-API-Key: $LAFOURGALE_API_KEY" \
      "$API_BASE_URL/wp-json/ewd-spp/v1/clients?per_page=20&include_meta=true&search=$SEARCH"
    ;;

  *)
    echo "Usage: $0 {flow EMAIL [MOBILE] [METHOD]|generate-key|create-client EMAIL [MOBILE] [METHOD]|list-clients [SEARCH]}" >&2
    exit 1
    ;;
esac
