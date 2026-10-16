"use client"

import { Badge } from "@shardev/ui"

import { ProductList } from "../../components/ProductList"
import type { ProductCapabilities } from "../../types"

const CAPABILITIES: ProductCapabilities = { create: false, edit: false, remove: false, transfer: false }

/** Web guard: read-only catalog. The API still authorizes every request. */
export function ProductCatalogView() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">Catálogo</h2>
          <p className="text-muted-foreground">Vista de solo lectura para la plataforma web.</p>
        </div>
        <Badge variant="secondary">solo lectura</Badge>
      </div>

      <ProductList capabilities={CAPABILITIES} />
    </div>
  )
}
