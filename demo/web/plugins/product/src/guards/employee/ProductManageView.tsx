"use client"

import { Badge } from "@shardev/ui"

import { ProductList } from "../../components/ProductList"
import type { ProductCapabilities } from "../../types"

const CAPABILITIES: ProductCapabilities = { create: true, edit: true, remove: false, transfer: false }

/** Employee guard: create and edit, but no destructive or bulk operations. */
export function ProductManageView() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">Productos</h2>
          <p className="text-muted-foreground">Alta y edición de productos; sin borrado ni import/export.</p>
        </div>
        <Badge variant="outline">edición</Badge>
      </div>

      <ProductList capabilities={CAPABILITIES} />
    </div>
  )
}
