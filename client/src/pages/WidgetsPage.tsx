"use client"

import { useQuery } from "@tanstack/react-query"
import { RefreshCw } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { ApiError } from "@/lib/api"
import { widgets } from "@/lib/endpoints"

export function WidgetsPage() {
  const query = useQuery({
    queryKey: ["widgets"],
    queryFn: () => widgets.get(),
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">Widgets</h2>
          <p className="text-muted-foreground">
            GET /api/v1/widgets — provider parcial (207) si algún widget falla.
          </p>
        </div>
        <Button variant="outline" size="icon" onClick={() => void query.refetch()} aria-label="Refrescar">
          <RefreshCw className="h-4 w-4" />
        </Button>
      </div>

      {query.isLoading && <p className="text-muted-foreground">Cargando…</p>}
      {query.isError && <p className="text-destructive">Error: {(query.error as ApiError).message}</p>}

      <div className="grid gap-4 md:grid-cols-2">
        {query.data &&
          Object.entries(query.data).map(([key, result]) => (
            <Card key={key}>
              <CardHeader className="flex-row items-center justify-between space-y-0">
                <div>
                  <CardTitle className="text-base font-mono">{key}</CardTitle>
                  <CardDescription>{result.status === "error" ? result.error?.message : "widget resuelto"}</CardDescription>
                </div>
                <Badge variant={result.status === "error" ? "destructive" : "default"}>{result.status}</Badge>
              </CardHeader>
              <CardContent>
                <pre className="max-h-64 overflow-auto rounded-md bg-muted p-3 text-xs">
                  {JSON.stringify(result.data ?? result.error ?? null, null, 2)}
                </pre>
              </CardContent>
            </Card>
          ))}
      </div>
    </div>
  )
}
