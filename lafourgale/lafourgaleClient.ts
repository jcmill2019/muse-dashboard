// lafourgaleClient.ts
//
// Client TypeScript réutilisable pour l'API REST Lafourgale (plugin ewd-spp).
// À utiliser CÔTÉ SERVEUR uniquement : la clé API ne doit jamais atteindre le navigateur.
//
// Aucune dépendance externe (fetch natif, Node >= 18 / Edge / Deno).
//
// Configuration via variables d'environnement :
//   LAFOURGALE_BASE_URL    (défaut: https://lafourgale.serverweb-prod.com)
//   LAFOURGALE_API_KEY     clé OPÉRATIONNELLE (format "prefix.secret") — requise
//   LAFOURGALE_MASTER_KEY  master key — UNIQUEMENT pour générer une clé opérationnelle

export type VerificationMethod = "email" | "whatsapp";

export interface CreateClientInput {
  user_email: string;
  first_name?: string;
  last_name?: string;
  user_mobile?: string;
  birth_date?: string; // YYYY-MM-DD
  postcode?: string;
  sponsor_code?: string;
  verification_method?: VerificationMethod; // défaut côté API: "email"
}

export interface CreateClientResult {
  success: true;
  message: string;
  data: {
    validation_link: string;
    expires_at: string;
    verification_method: VerificationMethod;
  };
}

export interface GenerateKeyResult {
  success: true;
  message: string;
  data: {
    key: string; // "prefix.secret" — affiché UNE seule fois
    key_id: string;
    prefix: string;
    label: string;
    created_at: string;
    expires_at: string;
  };
}

/**
 * Erreur typée renvoyée par l'API Lafourgale.
 * `code` reprend les codes documentés : email_invalid, email_exists,
 * rest_invalid_api_key, rest_api_key_missing, etc.
 */
export class LafourgaleApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly raw: string,
  ) {
    super(message);
    this.name = "LafourgaleApiError";
  }

  /** L'email existe déjà (HTTP 409) — souvent à traiter comme « déjà déclaré ». */
  get isAlreadyExists(): boolean {
    return this.status === 409 || this.code === "email_exists";
  }

  /** Clé absente / invalide / expirée (HTTP 401). */
  get isAuthError(): boolean {
    return this.status === 401;
  }
}

function baseUrl(): string {
  return (
    process.env.LAFOURGALE_BASE_URL?.replace(/\/+$/, "") ??
    "https://lafourgale.serverweb-prod.com"
  );
}

async function parseError(res: Response): Promise<LafourgaleApiError> {
  const raw = await res.text();
  let code = "unknown_error";
  let message = `HTTP ${res.status}`;
  try {
    const json = JSON.parse(raw);
    code = json?.code ?? code;
    message = json?.message ?? message;
  } catch {
    if (raw) message = raw;
  }
  return new LafourgaleApiError(res.status, code, message, raw);
}

/**
 * Crée un client dans Lafourgale et déclenche l'envoi du lien de validation
 * (email ou WhatsApp selon `verification_method`).
 *
 * @throws LafourgaleApiError en cas de réponse non-2xx.
 */
export async function createClient(
  input: CreateClientInput,
  opts: { apiKey?: string; timeoutMs?: number } = {},
): Promise<CreateClientResult> {
  const apiKey = opts.apiKey ?? process.env.LAFOURGALE_API_KEY;
  if (!apiKey) {
    throw new LafourgaleApiError(
      0,
      "config_missing",
      "LAFOURGALE_API_KEY non configurée (clé opérationnelle requise).",
      "",
    );
  }
  if (apiKey.startsWith("A9tboj7mQx5d0")) {
    throw new LafourgaleApiError(
      0,
      "master_key_misuse",
      "Vous utilisez la master key. Générez une clé opérationnelle via generateOperationalKey().",
      "",
    );
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), opts.timeoutMs ?? 20_000);
  try {
    const res = await fetch(`${baseUrl()}/wp-json/ewd-spp/v1/create-client`, {
      method: "POST",
      headers: {
        "X-API-Key": apiKey,
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        verification_method: "whatsapp",
        ...input,
      }),
      signal: controller.signal,
    });
    if (!res.ok) throw await parseError(res);
    return (await res.json()) as CreateClientResult;
  } finally {
    clearTimeout(timer);
  }
}

/**
 * Génère une nouvelle clé OPÉRATIONNELLE (valable 30 jours par défaut)
 * à partir de la master key. À exécuter rarement (rotation mensuelle).
 * Le champ data.key n'est retourné qu'UNE seule fois — stockez-le aussitôt.
 *
 * @throws LafourgaleApiError en cas de réponse non-2xx.
 */
export async function generateOperationalKey(
  params: { label?: string; expires_in_days?: number; revoke_others?: boolean } = {},
  opts: { masterKey?: string; timeoutMs?: number } = {},
): Promise<GenerateKeyResult> {
  const masterKey = opts.masterKey ?? process.env.LAFOURGALE_MASTER_KEY;
  if (!masterKey) {
    throw new LafourgaleApiError(
      0,
      "config_missing",
      "LAFOURGALE_MASTER_KEY non configurée.",
      "",
    );
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), opts.timeoutMs ?? 20_000);
  try {
    const res = await fetch(`${baseUrl()}/wp-json/ewd-spp/v1/api-key`, {
      method: "POST",
      headers: {
        "X-Master-Key": masterKey,
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        label: params.label ?? "Signial integration",
        expires_in_days: params.expires_in_days ?? 30,
        revoke_others: params.revoke_others ?? false,
      }),
      signal: controller.signal,
    });
    if (!res.ok) throw await parseError(res);
    return (await res.json()) as GenerateKeyResult;
  } finally {
    clearTimeout(timer);
  }
}
