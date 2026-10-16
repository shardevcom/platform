"use client"

import { useState } from "react"
import { useMutation, useQuery } from "@tanstack/react-query"
import { toast } from "sonner"
import { Download, Upload } from "lucide-react"

import { Badge, Button, Card, CardContent, CardDescription, CardHeader, CardTitle, Input } from "@shardev/ui"
import { ApiError } from "@shardev/core"

import { products } from "../endpoints"

/** Import (202 + polling) and export (authenticated download). Admin-only. */
export function ProductTransfer() {
  const [importId, setImportId] = useState<string | null>(null)
  const [importFile, setImportFile] = useState<File | null>(null)
  const [importFormat, setImportFormat] = useState("csv")

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

  return (
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
  )
}
