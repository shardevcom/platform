"use client"

import { useState } from "react"
import { useMutation } from "@tanstack/react-query"
import { toast } from "sonner"

import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle, Input, Label } from "@shardev/ui"
import { ApiError } from "@shardev/core"

import { products } from "../endpoints"
import type { Product, ProductInput } from "../types"

const EMPTY_FORM: ProductInput = { sku: "", name: "", ean: "", brand_id: null, active: true }

/** Create/edit form. Validation lives in the shared DTO on the API side. */
export function ProductForm({
  editing,
  onClose,
  onSaved,
}: {
  editing: Product | null
  onClose: () => void
  onSaved: () => void
}) {
  const [form, setForm] = useState<ProductInput>(
    editing
      ? {
          sku: editing.sku,
          name: editing.name,
          ean: editing.ean ?? "",
          brand_id: editing.brand_id,
          active: editing.active,
        }
      : EMPTY_FORM,
  )

  const saveMutation = useMutation({
    mutationFn: (input: ProductInput) => (editing ? products.update(editing.id, input) : products.create(input)),
    onSuccess: () => {
      toast.success(editing ? "Producto actualizado" : "Producto creado")
      onSaved()
    },
    onError: (error: unknown) =>
      toast.error(error instanceof ApiError ? error.message : "No se pudo guardar"),
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{editing ? `Editar ${editing.sku}` : "Nuevo producto"}</CardTitle>
        <CardDescription>POST/PUT /products — validación en el DTO compartido.</CardDescription>
      </CardHeader>
      <CardContent>
        <form
          className="grid gap-4 md:grid-cols-2"
          onSubmit={(e) => {
            e.preventDefault()
            saveMutation.mutate(form)
          }}
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
            <Button type="button" variant="ghost" onClick={onClose}>
              Cancelar
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
