"use client"

import { useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { Pencil, Trash2, Plus, Download, Upload, RefreshCw } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { ApiError } from "@/lib/api"
import { products } from "@/lib/endpoints"
import { useAuth } from "@/providers/AuthProvider"
import type { Product, ProductInput } from "@/types"

const EMPTY_FORM: ProductInput = { sku: "", name: "", ean: "", brand_id: null, active: true }

export function ProductsPage() {
  const { platform } = useAuth()
  const queryClient = useQueryClient()

  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(15)
  const [skuFilter, setSkuFilter] = useState("")
  const [activeFilter, setActiveFilter] = useState<"all" | "true" | "false">("all")
  const [sort, setSort] = useState<string>("-created_at")

  const [editing, setEditing] = useState<Product | null>(null)
  const [form, setForm] = useState<ProductInput>(EMPTY_FORM)

  const [importId, setImportId] = useState<string | null>(null)
  const [importFile, setImportFile] = useState<File | null>(null)
  const [importFormat, setImportFormat] = useState("csv")

  const filter: Record<string, unknown> = {}
  if (skuFilter) filter.sku = skuFilter
  if (activeFilter !== "all") filter.active = activeFilter === "true"

  const listQuery = useQuery({
    queryKey: ["products", platform, { page, perPage, skuFilter, activeFilter, sort }],
    queryFn: () => products.list({ filter, sort: [sort], include: ["brand"], page, per_page: perPage }),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["products"] })

  const saveMutation = useMutation({
    mutationFn: (input: ProductInput) =>
      editing ? products.update(editing.id, input) : products.create(input),
    onSuccess: () => {
      toast.success(editing ? "Producto actualizado" : "Producto creado")
      setEditing(null)
      setForm(EMPTY_FORM)
      void invalidate()
    },
    onError: (error: unknown) =>
      toast.error(error instanceof ApiError ? error.message : "No se pudo guardar"),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => products.remove(id),
    onSuccess: () => {
      toast.success("Producto eliminado")
      void invalidate()
    },
    onError: (error: unknown) =>
      toast.error(error instanceof ApiError ? error.message : "No se pudo eliminar"),
  })

  const importMutation = useMutation({
    mutationFn: () => {
      if (!importFile) throw new Error("Selecciona un archivo")
      return products.import(importFile, importFormat)
    },
    onSuccess: (result) => {
      setImportId(result.import_id)
      toast.success(`Import encolado (${result.status})`)
    },
    onError: (error: unknown) =>
      toast.error(error instanceof ApiError ? error.message : "No se pudo importar"),
  })

  const importStatusQuery = useQuery({
    queryKey: ["products", "import", importId],
    queryFn: () => products.importStatus(importId as string),
    enabled: importId !== null,
    refetchInterval: (query) => {
      const status = query.state.data?.status
      return status === "completed" || status === "failed" ? false : 2000
    },
  })

  const startEdit = (product: Product) => {
    setEditing(product)
    setForm({
      sku: product.sku,
      name: product.name,
      ean: product.ean ?? "",
      brand_id: product.brand_id,
      active: product.active,
    })
  }

  const handleExport = async (format: string) => {
    try {
      const blob = await products.export({ format })
      const url = URL.createObjectURL(blob)
      const anchor = document.createElement("a")
      anchor.href = url
      anchor.download = `products.${format}`
      anchor.click()
      URL.revokeObjectURL(url)
      toast.success("Export descargado")
    } catch {
      toast.error("No se pudo exportar")
    }
  }

  const pagination = listQuery.data?.meta.pagination
  const rows = listQuery.data?.data ?? []

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">Products</h2>
          <p className="text-muted-foreground">/admin/api/v1/products — REST adapter sobre el mismo Use Case que GraphQL.</p>
        </div>
        <Button onClick={() => { setEditing(null); setForm(EMPTY_FORM) }}>
          <Plus className="mr-2 h-4 w-4" /> Nuevo
        </Button>
      </div>

      <Tabs defaultValue="list">
        <TabsList>
          <TabsTrigger value="list">Listado</TabsTrigger>
          <TabsTrigger value="transfer">Import / Export</TabsTrigger>
        </TabsList>

        <TabsContent value="list" className="space-y-4">
          <Card>
            <CardContent className="flex flex-wrap items-end gap-3 pt-6">
              <div className="space-y-1">
                <Label htmlFor="sku">SKU</Label>
                <Input id="sku" value={skuFilter} onChange={(e) => { setSkuFilter(e.target.value); setPage(1) }} placeholder="Exacto" />
              </div>
              <div className="space-y-1">
                <Label htmlFor="active">Activo</Label>
                <select
                  id="active"
                  className="h-10 rounded-md border bg-background px-3 text-sm"
                  value={activeFilter}
                  onChange={(e) => { setActiveFilter(e.target.value as typeof activeFilter); setPage(1) }}
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
                    <th className="p-3 text-right">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {listQuery.isLoading && (
                    <tr><td className="p-3 text-muted-foreground" colSpan={6}>Cargando…</td></tr>
                  )}
                  {listQuery.isError && (
                    <tr><td className="p-3 text-destructive" colSpan={6}>Error: {(listQuery.error as ApiError).message}</td></tr>
                  )}
                  {!listQuery.isLoading && rows.length === 0 && (
                    <tr><td className="p-3 text-muted-foreground" colSpan={6}>Sin productos.</td></tr>
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
                      <td className="p-3">
                        <div className="flex justify-end gap-1">
                          <Button variant="ghost" size="icon" onClick={() => startEdit(product)} aria-label="Editar">
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => { if (confirm(`¿Eliminar ${product.sku}?`)) deleteMutation.mutate(product.id) }}
                            aria-label="Eliminar"
                          >
                            <Trash2 className="h-4 w-4 text-destructive" />
                          </Button>
                        </div>
                      </td>
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
                onChange={(e) => { setPerPage(Number(e.target.value)); setPage(1) }}
              >
                {[5, 15, 25, 50].map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
              {pagination && <span>· {pagination.total} total</span>}
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Anterior</Button>
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

          {(editing || form !== EMPTY_FORM) && (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{editing ? `Editar ${editing.sku}` : "Nuevo producto"}</CardTitle>
                <CardDescription>POST/PUT /products — validación en el DTO compartido.</CardDescription>
              </CardHeader>
              <CardContent>
                <form
                  className="grid gap-4 md:grid-cols-2"
                  onSubmit={(e) => { e.preventDefault(); saveMutation.mutate(form) }}
                >
                  <div className="space-y-1">
                    <Label htmlFor="f-sku">SKU</Label>
                    <Input id="f-sku" value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value })} required />
                  </div>
                  <div className="space-y-1">
                    <Label htmlFor="f-name">Nombre</Label>
                    <Input id="f-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
                  </div>
                  <div className="space-y-1">
                    <Label htmlFor="f-ean">EAN</Label>
                    <Input id="f-ean" value={form.ean ?? ""} onChange={(e) => setForm({ ...form, ean: e.target.value })} />
                  </div>
                  <div className="space-y-1">
                    <Label htmlFor="f-brand">Brand ID</Label>
                    <Input
                      id="f-brand"
                      type="number"
                      value={form.brand_id ?? ""}
                      onChange={(e) => setForm({ ...form, brand_id: e.target.value ? Number(e.target.value) : null })}
                    />
                  </div>
                  <label className="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={form.active ?? true}
                      onChange={(e) => setForm({ ...form, active: e.target.checked })}
                    />
                    Activo
                  </label>
                  <div className="flex items-center gap-2 md:col-span-2">
                    <Button type="submit" disabled={saveMutation.isPending}>
                      {editing ? "Guardar cambios" : "Crear"}
                    </Button>
                    <Button type="button" variant="ghost" onClick={() => { setEditing(null); setForm(EMPTY_FORM) }}>
                      Cancelar
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        <TabsContent value="transfer" className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Import</CardTitle>
                <CardDescription>POST /products/import — 202 + polling de estado.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <Input type="file" accept=".csv,.xlsx" onChange={(e) => setImportFile(e.target.files?.[0] ?? null)} />
                <div className="flex items-center gap-2">
                  <select
                    className="h-10 rounded-md border bg-background px-3 text-sm"
                    value={importFormat}
                    onChange={(e) => setImportFormat(e.target.value)}
                  >
                    <option value="csv">CSV</option>
                    <option value="xlsx">XLSX</option>
                  </select>
                  <Button disabled={!importFile || importMutation.isPending} onClick={() => importMutation.mutate()}>
                    <Upload className="mr-2 h-4 w-4" /> Importar
                  </Button>
                </div>

                {importId && (
                  <div className="rounded-md border p-3 text-sm">
                    <div className="font-mono text-xs text-muted-foreground">{importId}</div>
                    <div className="mt-2">
                      Estado: <Badge>{String(importStatusQuery.data?.status ?? "…")}</Badge>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">Export</CardTitle>
                <CardDescription>GET /products/export — descarga autenticada.</CardDescription>
              </CardHeader>
              <CardContent className="flex gap-2">
                <Button variant="outline" onClick={() => void handleExport("xlsx")}>
                  <Download className="mr-2 h-4 w-4" /> XLSX
                </Button>
                <Button variant="outline" onClick={() => void handleExport("csv")}>
                  <Download className="mr-2 h-4 w-4" /> CSV
                </Button>
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  )
}
