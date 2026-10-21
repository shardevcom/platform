import { apiData, graphqlRequest } from "./api"
import type {
  AccountNotification,
  InvitationSummary,
  LoginResponse,
  NotificationsPayload,
  Passkey,
  Preferences,
  ProviderResults,
  PublicKeyCredentialCreationOptionsJSON,
  PublicKeyCredentialRequestOptionsJSON,
  RegisterPayload,
  TwoFactorSetup,
  User,
} from "../types"

export interface CredentialPayload {
  id: string
  rawId: string
  type: string
  response: Record<string, unknown>
  clientExtensionResults?: Record<string, unknown>
}

export const auth = {
  login: (email: string, password: string, deviceName = "spa") =>
    apiData<LoginResponse>("auth/login", {
      method: "POST",
      auth: false,
      body: { email, password, device_name: deviceName },
    }),

  register: (payload: RegisterPayload) =>
    apiData<LoginResponse>("auth/register", { method: "POST", auth: false, body: payload }),

  me: () => apiData<User>("auth/me"),

  logout: () => apiData<null>("auth/logout", { method: "POST" }),

  forgotPassword: (email: string) =>
    apiData<{ sent: true }>("auth/forgot-password", { method: "POST", auth: false, body: { email } }),

  resetPassword: (payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }) => apiData<null>("auth/reset-password", { method: "POST", auth: false, body: payload }),

  verifyEmail: (id: string, hash: string, query: Record<string, unknown>) =>
    apiData<{ verified: true }>(`auth/email/verify/${id}/${hash}`, { auth: false, query }),

  resendVerification: () =>
    apiData<{ sent?: true; verified?: true }>("auth/email/verify/resend", { method: "POST" }),

  otpRequest: (email: string) =>
    apiData<{ sent: true }>("auth/otp/request", { method: "POST", auth: false, body: { email } }),

  otpVerify: (email: string, code: string, deviceName = "spa") =>
    apiData<LoginResponse>("auth/otp/verify", {
      method: "POST",
      auth: false,
      body: { email, code, device_name: deviceName },
    }),

  webauthnLoginOptions: (email?: string) =>
    apiData<{ publicKey: PublicKeyCredentialRequestOptionsJSON }>("auth/webauthn/login/options", {
      method: "POST",
      auth: false,
      body: email ? { email } : {},
    }),

  webauthnLogin: (credential: CredentialPayload, deviceName = "spa") =>
    apiData<LoginResponse>("auth/webauthn/login", {
      method: "POST",
      auth: false,
      body: { credential, device_name: deviceName },
    }),

  webauthnRegisterOptions: () =>
    apiData<{ publicKey: PublicKeyCredentialCreationOptionsJSON }>("auth/webauthn/register/options", {
      method: "POST",
    }),

  webauthnRegister: (credential: CredentialPayload, name?: string) =>
    apiData<Passkey>("auth/webauthn/register", { method: "POST", body: { credential, name } }),

  passkeys: () => apiData<{ items: Passkey[] }>("auth/webauthn/credentials"),

  removePasskey: (id: number) =>
    apiData<null>(`auth/webauthn/credentials/${id}`, { method: "DELETE" }),
}

export const account = {
  updateProfile: (payload: { name?: string; email?: string }) =>
    apiData<{ user: User }>("auth/profile", { method: "PATCH", body: payload }),

  changePassword: (payload: {
    current_password: string
    password: string
    password_confirmation: string
  }) => apiData<null>("auth/password", { method: "PUT", body: payload }),

  preferences: () => apiData<Preferences>("auth/preferences"),

  savePreferences: (payload: Partial<Preferences>) =>
    apiData<Preferences>("auth/preferences", { method: "PUT", body: payload }),

  notifications: () => apiData<NotificationsPayload>("auth/notifications"),

  readNotification: (id: string) =>
    apiData<AccountNotification>(`auth/notifications/${id}/read`, { method: "POST" }),

  readAllNotifications: () =>
    apiData<{ unread_count: number }>("auth/notifications/read-all", { method: "POST" }),

  deleteNotification: (id: string) =>
    apiData<null>(`auth/notifications/${id}`, { method: "DELETE" }),

  disable: (currentPassword: string, reason?: string) =>
    apiData<{ disabled: true }>("auth/account/disable", {
      method: "POST",
      body: { current_password: currentPassword, reason },
    }),

  reactivate: (email: string, password: string) =>
    apiData<{ disabled: false }>("auth/account/reactivate", {
      method: "POST",
      auth: false,
      body: { email, password },
    }),

  invite: (email: string) =>
    apiData<{ email: string; expires_at: string | null }>("auth/invitations", {
      method: "POST",
      body: { email },
    }),

  invitation: (token: string) =>
    apiData<InvitationSummary>(`auth/invitations/${encodeURIComponent(token)}`, { auth: false }),

  acceptInvitation: (payload: {
    token: string
    name: string
    password: string
    password_confirmation: string
    device_name?: string
  }) =>
    apiData<LoginResponse>("auth/invitations/accept", {
      method: "POST",
      auth: false,
      body: payload,
    }),
}


export const configuration = {
  get: (include?: string[]) => apiData<ProviderResults>("configuration", { query: include?.length ? { include } : undefined }),
}

export const widgets = {
  get: (include?: string[]) => apiData<ProviderResults>("widgets", { query: include?.length ? { include } : undefined }),
}

export const twoFactor = {
  enable: () => apiData<TwoFactorSetup>("enable", { method: "POST" }),

  verify: (code: string) => apiData<null>("verify", { method: "POST", body: { code } }),

  disable: (password: string, code?: string) =>
    apiData<null>("disable", { method: "POST", body: { password, code } }),

  qrCode: () => apiData<{ qr_code: string }>("qr-code"),

  recoveryCodes: () => apiData<{ recovery_codes: string[] }>("recovery-codes"),

  regenerate: () => apiData<{ recovery_codes: string[] }>("regenerate-recovery-codes", { method: "POST" }),
}

export const graphql = {
  request: graphqlRequest,
}
