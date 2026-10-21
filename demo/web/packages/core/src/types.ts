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

export interface SocialProvider {
  key: string
  name: string
}

export interface User {
  id: string
  name: string | null
  email: string | null
  guard: string | null
  email_verified: boolean
  abilities: string[]
}

/** Preferences always arrive complete: the API merges them over its defaults. */
export interface Preferences {
  theme: "light" | "dark" | "system"
  language: string
  notifications: Record<string, boolean>
}

export interface AccountNotification {
  id: string
  type: string
  data: Record<string, unknown>
  read_at: string | null
  created_at: string | null
}

export interface NotificationsPayload {
  items: AccountNotification[]
  unread_count: number
}

export interface InvitationSummary {
  email: string
  guard: string
  valid: true
}

export interface Passkey {
  id: number
  name: string | null
  credential_id: string
  created_at: string | null
  last_used_at: string | null
}

/** Options returned by the API for `navigator.credentials.create`. */
export interface PublicKeyCredentialCreationOptionsJSON {
  challenge: string
  rp: { id: string; name: string }
  user: { id: string; name: string; displayName: string }
  pubKeyCredParams: Array<{ type: "public-key"; alg: number }>
  timeout?: number
  attestation?: AttestationConveyancePreference
  authenticatorSelection?: AuthenticatorSelectionCriteria
  excludeCredentials?: PublicKeyCredentialDescriptorJSON[]
}

/** Options returned by the API for `navigator.credentials.get`. */
export interface PublicKeyCredentialRequestOptionsJSON {
  challenge: string
  rpId: string
  allowCredentials: PublicKeyCredentialDescriptorJSON[]
  userVerification?: UserVerificationRequirement
  timeout?: number
}

export interface PublicKeyCredentialDescriptorJSON {
  type: "public-key"
  id: string
  transports?: AuthenticatorTransport[]
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  device_name?: string
}

export interface LoginResponse {
  token_type: string
  access_token: string
  expires_in: number | null
  abilities: string[]
  user: User
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
