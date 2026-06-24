// Étape 2 : créer un client + déclencher l'envoi du lien de validation.
//
// Exige une clé OPÉRATIONNELLE (générée via lafourgale-generate-key.ts).
// NE PAS utiliser la master key ici → HTTP 401.
//
// Exécution :
//   LAFOURGALE_API_KEY='prefix.secret...' \
//   TEST_EMAIL='client@example.com' \
//   TEST_MOBILE='+33612345678' \
//   VERIFICATION_METHOD='whatsapp' \
//     npx tsx lafourgale-create-client.ts

const BASE = process.env.API_BASE_URL ?? "https://lafourgale.serverweb-prod.com";
const API_KEY = process.env.LAFOURGALE_API_KEY;
const EMAIL = process.env.TEST_EMAIL ?? "test+lafourgale@example.com";
const MOBILE = process.env.TEST_MOBILE ?? "+33612345678";
const METHOD = (process.env.VERIFICATION_METHOD ?? "whatsapp") as "email" | "whatsapp";

if (!API_KEY) {
  console.error(
    "LAFOURGALE_API_KEY manquant. Générez-la d'abord :\n" +
      "  LAFOURGALE_MASTER_KEY='...' npx tsx lafourgale-generate-key.ts",
  );
  process.exit(2);
}
if (API_KEY.startsWith("A9tboj7mQx5d0")) {
  console.error(
    "Cette valeur est la MASTER KEY — incorrecte pour /create-client (HTTP 401).\n" +
      "Générez une clé opérationnelle via lafourgale-generate-key.ts.",
  );
  process.exit(2);
}

async function main() {
  const res = await fetch(`${BASE}/wp-json/ewd-spp/v1/create-client`, {
    method: "POST",
    headers: {
      "X-API-Key": API_KEY!,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      user_email: EMAIL,
      first_name: "Jean",
      last_name: "Dupont",
      user_mobile: MOBILE,
      verification_method: METHOD,
    }),
  });

  const raw = await res.text();
  console.log(`HTTP ${res.status} ${res.statusText}`);
  console.log("Body:", raw);
  if (!res.ok) process.exit(1);
}

main().catch((err) => {
  console.error("Erreur:", err);
  process.exit(1);
});
