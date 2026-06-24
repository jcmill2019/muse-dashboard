// Étape 1 : générer une clé OPÉRATIONNELLE.
//
// La clé "A9tboj7mQx5d0<ySl9-A54kZ8{Gzn.G[" est la MASTER KEY.
// Elle ne sert QU'à cet appel (POST /api-key).
// L'utiliser sur /create-client ou /clients renvoie HTTP 401.
//
// Exécution :
//   LAFOURGALE_MASTER_KEY='A9tboj7mQx5d0<ySl9-A54kZ8{Gzn.G[' \
//     npx tsx lafourgale-generate-key.ts
//
// La sortie contient data.key (format prefix.secret).
// À stocker dans LAFOURGALE_API_KEY pour les autres scripts.
// ⚠️ Cette valeur n'est retournée qu'UNE seule fois.

const BASE = process.env.API_BASE_URL ?? "https://lafourgale.serverweb-prod.com";
const MASTER_KEY = process.env.LAFOURGALE_MASTER_KEY;

if (!MASTER_KEY) {
  console.error("LAFOURGALE_MASTER_KEY manquant dans l'environnement.");
  process.exit(2);
}

async function main() {
  const res = await fetch(`${BASE}/wp-json/ewd-spp/v1/api-key`, {
    method: "POST",
    headers: {
      "X-Master-Key": MASTER_KEY!,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      label: "Test integration",
      expires_in_days: 30,
      revoke_others: false,
    }),
  });

  const raw = await res.text();
  console.log(`HTTP ${res.status} ${res.statusText}`);
  console.log("Body:", raw);

  if (!res.ok) process.exit(1);

  const json = JSON.parse(raw);
  const key: string | undefined = json?.data?.key;
  if (key) {
    console.log("\n>>> Conservez cette clé (non réaffichée ensuite) :");
    console.log(`export LAFOURGALE_API_KEY='${key}'`);
  }
}

main().catch((err) => {
  console.error("Erreur:", err);
  process.exit(1);
});
