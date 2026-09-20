/**
 * Thin HTTP client for the MSP Agent API ({MSP_API_BASE}/api/v1).
 */

export type QueryValue = string | number | boolean | null | undefined;

export class MspApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly body: string,
  ) {
    super(message);
    this.name = "MspApiError";
  }
}

export class MspClient {
  readonly baseUrl: string;
  readonly token: string;

  constructor(baseUrl: string, token: string) {
    this.baseUrl = baseUrl.replace(/\/+$/, "");
    this.token = token;
  }

  static fromEnv(): MspClient {
    const base = process.env.MSP_API_BASE?.trim();
    const token = process.env.MSP_API_TOKEN?.trim();
    if (!base) {
      throw new Error("MSP_API_BASE is required (e.g. http://localhost)");
    }
    if (!token) {
      throw new Error("MSP_API_TOKEN is required (Sanctum personal access token)");
    }
    return new MspClient(base, token);
  }

  private buildUrl(path: string, query?: Record<string, QueryValue>): string {
    const url = new URL(`${this.baseUrl}/api/v1${path.startsWith("/") ? path : `/${path}`}`);
    if (query) {
      for (const [key, value] of Object.entries(query)) {
        if (value === undefined || value === null || value === "") continue;
        url.searchParams.set(key, String(value));
      }
    }
    return url.toString();
  }

  async request(
    method: string,
    path: string,
    options: {
      query?: Record<string, QueryValue>;
      body?: unknown;
    } = {},
  ): Promise<unknown> {
    const headers: Record<string, string> = {
      Authorization: `Bearer ${this.token}`,
      Accept: "application/json",
    };
    let body: string | undefined;
    if (options.body !== undefined) {
      headers["Content-Type"] = "application/json";
      body = JSON.stringify(options.body);
    }

    const res = await fetch(this.buildUrl(path, options.query), {
      method,
      headers,
      body,
    });

    const text = await res.text();
    if (res.status === 204) {
      return { ok: true, status: 204 };
    }

    let parsed: unknown = text;
    if (text) {
      try {
        parsed = JSON.parse(text);
      } catch {
        parsed = text;
      }
    }

    if (!res.ok) {
      throw new MspApiError(
        `MSP API ${method} ${path} failed: HTTP ${res.status}`,
        res.status,
        typeof parsed === "string" ? parsed : JSON.stringify(parsed),
      );
    }

    return parsed;
  }

  get(path: string, query?: Record<string, QueryValue>) {
    return this.request("GET", path, { query });
  }

  post(path: string, body?: unknown) {
    return this.request("POST", path, { body });
  }

  patch(path: string, body?: unknown) {
    return this.request("PATCH", path, { body });
  }

  delete(path: string) {
    return this.request("DELETE", path);
  }
}

/** Drop undefined/null keys so create/update payloads stay clean. */
export function compactBody(obj: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(obj)) {
    if (v !== undefined && v !== null) out[k] = v;
  }
  return out;
}

export function jsonResult(data: unknown) {
  return {
    content: [{ type: "text" as const, text: JSON.stringify(data, null, 2) }],
  };
}

export function errorResult(err: unknown) {
  if (err instanceof MspApiError) {
    return {
      isError: true as const,
      content: [
        {
          type: "text" as const,
          text: JSON.stringify(
            { error: err.message, status: err.status, body: tryParse(err.body) },
            null,
            2,
          ),
        },
      ],
    };
  }
  const message = err instanceof Error ? err.message : String(err);
  return {
    isError: true as const,
    content: [{ type: "text" as const, text: JSON.stringify({ error: message }, null, 2) }],
  };
}

function tryParse(s: string): unknown {
  try {
    return JSON.parse(s);
  } catch {
    return s;
  }
}
