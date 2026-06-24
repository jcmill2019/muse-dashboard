#!/usr/bin/env bash
# Test minimal de l'endpoint lafourgale via curl.
# Important : la clé API contient < { [ — d'où les guillemets simples autour du -H.

set -euo pipefail

URL='https://lafourgale.serverweb-prod.com/wp-json/ewd-spp/v1/create-client'
API_KEY='A9tboj7mQx5d0<ySl9-A54kZ8{Gzn.G['

curl -sS -i -X POST "$URL" \
  -H "X-API-Key: ${API_KEY}" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "user_email": "jean.dupont+test@example.com",
    "first_name": "Jean",
    "last_name": "Dupont",
    "birth_date": "1990-01-15",
    "postcode": "75001",
    "user_mobile": "+33612345678",
    "sponsor_code": "AGENT001",
    "verification_method": "whatsapp"
  }'
