import { apiData, graphqlRequest } from "./api"
import type { LoginResponse, ProviderResults, TwoFactorSetup, User } from "../types"

export const auth = {
  login: (email: string, password: string, deviceName = "spa") =>
    apiData<LoginResponse>("auth/login", {
      method: "POST",
      auth: false,
      body: { email, password, device_name: deviceName },
    }),

  me: () => apiData<User>("auth/me"),

  logout: () => apiData<null>("auth/logout", { method: "POST" }),
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
