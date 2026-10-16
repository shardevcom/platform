import type { ApiEnvelope, Platform } from "@/types"

const TOKEN_KEY = "shardev.access_token"
const PLATFORM_KEY = "shardev.platform"

/**
 * Error carrying the platform's semantic code (`FORBIDDEN`, `VALIDATION_FAILED`,
 * ...) plus the HTTP status, so callers can branch on meaning instead of
 * parsing messages.
 */
export class ApiError extends Error {
  constructor(
    message: string,
    readonly code: string,
    readonly status: number,
    readonly details?: Record<string, unknown>,
  ) {
    super(message)
    this.name = "ApiError"
  }
}

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

export function getPlatform(): Platform {
  return (localStorage.getItem(PLATFORM_KEY) as Platform | null) ?? "admin"
}

export function setPlatform(platform: Platform): void {
  localStorage.setItem(PLATFORM_KEY, platform)
}

/** Base path of the platform's versioned REST surface. */
export function apiBase(platform: Platform = getPlatform()): string {
  const prefix = platform === "web" ? "" : `${platform}/`
  return `/${prefix}api/v1`
}

/**
 * Serialize the query shape the REST adapter expects: nested objects as
 * `filter[sku]`, lists as `sort[]`, scalars verbatim.
 */
function buildQuery(query?: Record<string, unknown>): string {
  if (!query) return ""

  const params = new URLSearchParams()

  const append = (key: string, value: unknown): void => {
    if (value === undefined || value === null || value === "") return

    if (Array.isArray(value)) {
      value.forEach((item) => append(`${key}[]`, item))
    } else if (typeof value === "object") {
      Object.entries(value as Record<string, unknown>).forEach(([k, v]) => append(`${key}[${k}]`, v))
    } else {
      params.append(key, String(value))
    }
  }

  Object.entries(query).forEach(([key, value]) => append(key, value))

  const serialized = params.toString()
  return serialized ? `?${serialized}` : ""
}

export interface RequestOptions {
  method?: string
  query?: Record<string, unknown>
  body?: unknown
  platform?: Platform
  auth?: boolean
  signal?: AbortSignal
}

function parseError(status: number, envelope: ApiEnvelope<unknown> | null, statusText: string): ApiError {
  return new ApiError(
    envelope?.message || statusText || "Request failed",
    envelope?.code ?? `HTTP_${status}`,
    status,
    (envelope?.meta?.details as Record<string, unknown> | undefined) ?? undefined,
  )
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  const { method = "GET", query, body, platform = getPlatform(), auth = true, signal } = options

  const headers: Record<string, string> = { Accept: "application/json" }

  if (body !== undefined && !(body instanceof FormData)) {
    headers["Content-Type"] = "application/json"
  }

  if (auth) {
    const token = getToken()
    if (token) headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${apiBase(platform)}${path}${buildQuery(query)}`, {
    method,
    headers,
    body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body),
    signal,
  })

  if (response.status === 204) {
    return { status: "success", code: "OK", message: "", data: null as T, meta: {} as ApiEnvelope<T>["meta"] }
  }

  const envelope = (await response.json().catch(() => null)) as ApiEnvelope<T> | null

  if (!response.ok || envelope?.status === "error") {
    throw parseError(response.status, envelope, response.statusText)
  }

  return envelope as ApiEnvelope<T>
}

export async function apiData<T>(path: string, options?: RequestOptions): Promise<T> {
  return (await apiRequest<T>(path, options)).data
}

/** GET a binary export (spreadsheet) and hand it back as a Blob. */
export async function downloadFile(path: string, query?: Record<string, unknown>): Promise<Blob> {
  const token = getToken()
  const response = await fetch(`${apiBase()}${path}${buildQuery(query)}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })

  if (!response.ok) {
    throw new ApiError("Download failed", `HTTP_${response.status}`, response.status)
  }

  return response.blob()
}

export interface GraphQLResponse<T> {
  data?: T
  errors?: Array<{ message: string; extensions?: Record<string, unknown> }>
}

/** Run a GraphQL operation against the demo's `/graphql` endpoint. */
export async function graphqlRequest<T>(
  query: string,
  variables?: Record<string, unknown>,
): Promise<GraphQLResponse<T>> {
  const token = getToken()
  const response = await fetch("/graphql", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ query, variables }),
  })

  return (await response.json()) as GraphQLResponse<T>
}
