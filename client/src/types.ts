export type Platform = "web" | "admin" | "employee"

export interface ApiMeta {
  request_id: string
  correlation_id: string
  pagination?: Pagination
  details?: Record<string, string[]>
  [key: string]: unknown
}

export interface ApiEnvelope<T> {
  status: "success" | "partial" | "error"
  code: string
  message: string
  data: T
  meta: ApiMeta
}

export interface Pagination {
  total: number
  count?: number
  per_page: number
  current_page: number
  last_page: number
}

export interface User {
  id: string
  name: string | null
  email: string | null
  guard: string | null
  abilities: string[]
}

export interface LoginResponse {
  token_type: string
  access_token: string
  expires_in: number | null
  abilities: string[]
  user: User
}

export interface Brand {
  id: number
  name: string
}

export interface Product {
  id: number
  sku: string
  name: string
  ean: string | null
  brand_id: number | null
  active: boolean
  brand?: Brand | null
  created_at: string
  updated_at: string
}

export interface ProductInput {
  sku: string
  name: string
  ean?: string | null
  brand_id?: number | null
  active?: boolean
}

export interface ImportAccepted {
  import_id: string
  status: string
}

export interface ImportStatus {
  import_id?: string
  status?: string
  total?: number
  processed?: number
  failed?: number
  [key: string]: unknown
}

/** Per-provider outcome returned by the configuration and widgets endpoints. */
export interface ProviderResult<T = unknown> {
  status: "success" | "error"
  data?: T
  error?: { code: string; message: string }
}

export type ProviderResults = Record<string, ProviderResult>

export interface TwoFactorSetup {
  secret: string
  qr_code: string
  recovery_codes: string[]
  instructions?: string
}
