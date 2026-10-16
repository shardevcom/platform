"use client"

import { Tabs, TabsContent, TabsList, TabsTrigger } from "@shardev/ui"

import { ProductList } from "../../components/ProductList"
import { ProductTransfer } from "../../components/ProductTransfer"
import type { ProductCapabilities } from "../../types"

const CAPABILITIES: ProductCapabilities = { create: true, edit: true, remove: true, transfer: true }

/** Admin guard: full CRUD plus import/export. */
export function ProductAdminView() {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold">Products</h2>
        <p className="text-muted-foreground">
          REST adapter sobre el mismo Use Case que GraphQL. Consola completa de administración.
        </p>
      </div>

      <Tabs defaultValue="list">
        <TabsList>
          <TabsTrigger value="list">Listado</TabsTrigger>
          <TabsTrigger value="transfer">Import / Export</TabsTrigger>
        </TabsList>

        <TabsContent value="list" className="space-y-4">
          <ProductList capabilities={CAPABILITIES} />
        </TabsContent>

        <TabsContent value="transfer" className="space-y-4">
          <ProductTransfer />
        </TabsContent>
      </Tabs>
    </div>
  )
}
