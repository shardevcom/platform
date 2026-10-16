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

export interface ProductQuery {
  filter?: Record<string, unknown>
  include?: string[]
  sort?: string[]
  page?: number
  per_page?: number
  fields?: string[]
  [key: string]: unknown
}

/** What a guard's product view is allowed to do (mirrors the token abilities). */
export interface ProductCapabilities {
  create: boolean
  edit: boolean
  remove: boolean
  transfer: boolean
}
