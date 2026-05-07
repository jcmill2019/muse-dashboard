// Test de l'endpoint lafourgale — create-client.
//
// Exécution :
//   npx tsx test-lafourgale.ts
// ou avec Node >= 20 (fetch natif) après compilation TS,
// ou directement en JS via : node test-lafourgale.mjs
//
// La clé API contient des caractères spéciaux (< { [ . - ` ' ") :
// elle DOIT être passée telle quelle, sans échappement ni encodage.

const URL = "https://lafourgale.serverweb-prod.com/wp-json/ewd-spp/v1/create-client";
const API_KEY = "A9tboj7mQx5d0<ySl9-A54kZ8{Gzn.G[";

type CreateClientPayload = {
  user_email: string;
  last_name?: string;
  first_name?: string;
  birth_date?: string;
  postcode?: string;
  user_mobile?: string;
  sponsor_code?: string;
  verification_method?: "email" | "whatsapp";
};

type CreateClientSuccess = {
  success: true;
  message: string;
  validation_link: string;
  token: string;
  ttl_hours: number;
  verification_method: "email" | "whatsapp";
  client_data: {
    first_name?: string;
    last_name?: string;
    email: string;
    mobile?: string;
    sponsor_code?: string;
  };
};

async function createClient(payload: CreateClientPayload): Promise<CreateClientSuccess> {
  const res = await fetch(URL, {
    method: "POST",
    headers: {
      "X-API-Key": API_KEY,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(payload),
  });

  const raw = await res.text();
  console.log(`HTTP ${res.status} ${res.statusText}`);
  console.log("Headers:", Object.fromEntries(res.headers.entries()));
  console.log("Body:", raw);

  if (!res.ok) {
    throw new Error(`Echec ${res.status}: ${raw}`);
  }

  return JSON.parse(raw) as CreateClientSuccess;
}

async function main() {
  const result = await createClient({
    user_email: "jean.dupont+test@example.com",
    first_name: "Jean",
    last_name: "Dupont",
    birth_date: "1990-01-15",
    postcode: "75001",
    user_mobile: "+33612345678",
    sponsor_code: "AGENT001",
    verification_method: "email",
  });
  console.log("\nSuccès :", result);
}

main().catch((err) => {
  console.error("\nErreur:", err.message);
  process.exit(1);
});
