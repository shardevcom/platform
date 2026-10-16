import { apiData, apiRequest, downloadFile } from "@shardev/core"

import type { ImportAccepted, ImportStatus, Product, ProductInput, ProductQuery } from "./types"

/**
 * Product REST endpoints. They live with the plugin (not in core) because the
 * resource belongs to the plugin; core only provides the transport.
 */
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
