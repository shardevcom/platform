"use client"

import { useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { Pencil, Plus, RefreshCw, Trash2 } from "lucide-react"

import { Badge, Button, Card, CardContent, Input, Label } from "@shardev/ui"
import { ApiError, useAuth } from "@shardev/core"

import { products } from "../endpoints"
import { ProductForm } from "./ProductForm"
import type { Product, ProductCapabilities } from "../types"

/**
 * Shared product table: filters, pagination and the (capability-gated) actions.
 * Each guard view renders it with different capabilities, so the same logic
 * backs a read-only catalog, an editor and the full admin console.
 */
export function ProductList({ capabilities }: { capabilities: ProductCapabilities }) {
  const { guard } = useAuth()
  const queryClient = useQueryClient()

  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(15)
  const [skuFilter, setSkuFilter] = useState("")
  const [activeFilter, setActiveFilter] = useState<"all" | "true" | "false">("all")
  const [sort, setSort] = useState<string>("-created_at")

  const [editing, setEditing] = useState<Product | null>(null)
  const [creating, setCreating] = useState(false)

  const filter: Record<string, unknown> = {}
  if (skuFilter) filter.sku = skuFilter
  if (activeFilter !== "all") filter.active = activeFilter === "true"

  const listQuery = useQuery({
    queryKey: ["products", guard, { page, perPage, skuFilter, activeFilter, sort }],
    queryFn: () => products.list({ filter, sort: [sort], include: ["brand"], page, per_page: perPage }),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["products"] })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => products.remove(id),
    onSuccess: () => {
      toast.success("Producto eliminado")
      void invalidate()
    },
    onError: (error: unknown) =>
      toast.error(error instanceof ApiError ? error.message : "No se pudo eliminar"),
  })

  const formOpen = creating || editing !== null
  const closeForm = () => {
    setCreating(false)
    setEditing(null)
  }

  const pagination = listQuery.data?.meta.pagination
  const rows = listQuery.data?.data ?? []

  return (
    <div className="space-y-4">
      <Card>
        <CardContent className="flex flex-wrap items-end gap-3 pt-6">
          <div className="space-y-1">
            <Label htmlFor="sku">SKU</Label>
            <Input
              id="sku"
              value={skuFilter}
              onChange={(e) => {
                setSkuFilter(e.target.value)
                setPage(1)
              }}
              placeholder="Exacto"
            />
          </div>
          <div className="space-y-1">
            <Label htmlFor="active">Activo</Label>
            <select
              id="active"
              className="h-10 rounded-md border bg-background px-3 text-sm"
              value={activeFilter}
              onChange={(e) => {
                setActiveFilter(e.target.value as typeof activeFilter)
                setPage(1)
              }}
            >
              <option value="all">Todos</option>
              <option value="true">Sí</option>
              <option value="false">No</option>
            </select>
          </div>
          <div className="space-y-1">
            <Label htmlFor="sort">Orden</Label>
            <select
              id="sort"
              className="h-10 rounded-md border bg-background px-3 text-sm"
              value={sort}
              onChange={(e) => setSort(e.target.value)}
            >
              <option value="-created_at">Recientes</option>
              <option value="created_at">Antiguos</option>
              <option value="name">Nombre A-Z</option>
              <option value="-name">Nombre Z-A</option>
            </select>
          </div>
          <Button variant="outline" size="icon" onClick={() => void listQuery.refetch()} aria-label="Refrescar">
            <RefreshCw className="h-4 w-4" />
          </Button>
          {capabilities.create && (
            <Button
              className="ml-auto"
              onClick={() => {
                setEditing(null)
                setCreating(true)
              }}
            >
              <Plus className="mr-2 h-4 w-4" /> Nuevo
            </Button>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-0">
          <table className="w-full text-sm">
            <thead className="border-b text-left text-muted-foreground">
              <tr>
                <th className="p-3">SKU</th>
                <th className="p-3">Nombre</th>
                <th className="p-3">EAN</th>
                <th className="p-3">Brand</th>
                <th className="p-3">Activo</th>
                {(capabilities.edit || capabilities.remove) && <th className="p-3 text-right">Acciones</th>}
              </tr>
            </thead>
            <tbody>
              {listQuery.isLoading && (
                <tr>
                  <td className="p-3 text-muted-foreground" colSpan={6}>
                    Cargando…
                  </td>
                </tr>
              )}
              {listQuery.isError && (
                <tr>
                  <td className="p-3 text-destructive" colSpan={6}>
                    Error: {(listQuery.error as ApiError).message}
                  </td>
                </tr>
              )}
              {!listQuery.isLoading && rows.length === 0 && (
                <tr>
                  <td className="p-3 text-muted-foreground" colSpan={6}>
                    Sin productos.
                  </td>
                </tr>
              )}
              {rows.map((product) => (
                <tr key={product.id} className="border-b last:border-0">
                  <td className="p-3 font-mono text-xs">{product.sku}</td>
                  <td className="p-3">{product.name}</td>
                  <td className="p-3 text-muted-foreground">{product.ean ?? "—"}</td>
                  <td className="p-3 text-muted-foreground">{product.brand?.name ?? product.brand_id ?? "—"}</td>
                  <td className="p-3">
                    <Badge variant={product.active ? "default" : "secondary"}>
                      {product.active ? "activo" : "inactivo"}
                    </Badge>
                  </td>
                  {(capabilities.edit || capabilities.remove) && (
                    <td className="p-3">
                      <div className="flex justify-end gap-1">
                        {capabilities.edit && (
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => {
                              setCreating(false)
                              setEditing(product)
                            }}
                            aria-label="Editar"
                          >
                            <Pencil className="h-4 w-4" />
                          </Button>
                        )}
                        {capabilities.remove && (
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => {
                              if (confirm(`¿Eliminar ${product.sku}?`)) deleteMutation.mutate(product.id)
                            }}
                            aria-label="Eliminar"
                          >
                            <Trash2 className="h-4 w-4 text-destructive" />
                          </Button>
                        )}
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </CardContent>
      </Card>

      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span>Por página</span>
          <select
            className="h-9 rounded-md border bg-background px-2"
            value={perPage}
            onChange={(e) => {
              setPerPage(Number(e.target.value))
              setPage(1)
            }}
          >
            {[5, 15, 25, 50].map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
          {pagination && <span>· {pagination.total} total</span>}
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Anterior
          </Button>
          <span className="text-sm text-muted-foreground">
            {pagination ? `${pagination.current_page} / ${pagination.last_page}` : "—"}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={!!pagination && page >= pagination.last_page}
            onClick={() => setPage((p) => p + 1)}
          >
            Siguiente
          </Button>
        </div>
      </div>

      {formOpen && (
        <ProductForm
          editing={editing}
          onClose={closeForm}
          onSaved={() => {
            closeForm()
            void invalidate()
          }}
        />
      )}
    </div>
  )
}
