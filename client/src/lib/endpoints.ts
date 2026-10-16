import { apiData, apiRequest, downloadFile, graphqlRequest } from "./api"
import type {
  ImportAccepted,
  ImportStatus,
  LoginResponse,
  Product,
  ProductInput,
  ProviderResults,
  TwoFactorSetup,
  User,
} from "@/types"

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

export interface ProductQuery {
  filter?: Record<string, unknown>
  include?: string[]
  sort?: string[]
  page?: number
  per_page?: number
  fields?: string[]
  [key: string]: unknown
}

export const products = {
  list: (query: ProductQuery = {}) => apiRequest<Product[]>("products", { query }),

  select: (query: ProductQuery = {}) => apiRequest<Product[]>("products/select", { query }),

  get: (id: number | string) => apiData<Product>(`products/${id}`),

  create: (input: ProductInput) => apiData<Product>("products", { method: "POST", body: input }),

  update: (id: number | string, input: Partial<ProductInput>) =>
    apiData<Product>(`products/${id}`, { method: "PUT", body: input }),

  remove: (id: number | string) => apiData<null>(`products/${id}`, { method: "DELETE" }),

  import: (file: File, format: string) => {
    const body = new FormData()
    body.append("file", file)
    body.append("format", format)
    return apiData<ImportAccepted>("products/import", { method: "POST", body })
  },

  importStatus: (importId: string) => apiData<ImportStatus>(`products/import/${importId}/status`),

  importErrors: (importId: string) => apiData<ImportStatus>(`products/import/${importId}/errors`),

  export: (query: { format?: string } & ProductQuery = {}) => downloadFile("products/export", query),
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
