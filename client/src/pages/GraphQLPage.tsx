"use client"

import { useState } from "react"
import { Play } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { type GraphQLResponse } from "@/lib/api"
import { graphql } from "@/lib/endpoints"

const QUERY = `query ListProducts {
  products(per_page: 5, sort: [{ field: name, direction: asc }]) {
    data { id sku name active }
    pagination { total current_page last_page }
  }
}`

const MUTATION = `mutation CreateProduct($input: CreateProductInput!) {
  createProduct(input: $input) { id sku name }
}`

const MUTATION_VARIABLES = `{
  "input": { "sku": "GQL-001", "name": "Producto vía GraphQL", "active": true }
}`

export function GraphQLPage() {
  const [operation, setOperation] = useState(QUERY)
  const [variables, setVariables] = useState("")
  const [result, setResult] = useState<GraphQLResponse<unknown> | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [running, setRunning] = useState(false)

  const run = async () => {
    setRunning(true)
    setError(null)
    try {
      let parsedVariables: Record<string, unknown> | undefined
      if (variables.trim()) {
        parsedVariables = JSON.parse(variables) as Record<string, unknown>
      }
      setResult(await graphql.request(operation, parsedVariables))
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error GraphQL")
      setResult(null)
    } finally {
      setRunning(false)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold">GraphQL</h2>
        <p className="text-muted-foreground">POST /graphql — mismo Use Case y lifecycle que REST.</p>
      </div>

      <Tabs defaultValue="query">
        <TabsList>
          <TabsTrigger value="query">Query</TabsTrigger>
          <TabsTrigger value="mutation">Mutation</TabsTrigger>
        </TabsList>
        <TabsContent value="query">
          <Button variant="outline" size="sm" onClick={() => { setOperation(QUERY); setVariables("") }}>
            Cargar query de ejemplo
          </Button>
        </TabsContent>
        <TabsContent value="mutation">
          <Button variant="outline" size="sm" onClick={() => { setOperation(MUTATION); setVariables(MUTATION_VARIABLES) }}>
            Cargar mutation de ejemplo
          </Button>
        </TabsContent>
      </Tabs>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Operación</CardTitle>
            <CardDescription>El token se envía como Bearer.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <textarea
              className="h-56 w-full rounded-md border bg-background p-3 font-mono text-xs"
              value={operation}
              onChange={(e) => setOperation(e.target.value)}
            />
            <textarea
              className="h-24 w-full rounded-md border bg-background p-3 font-mono text-xs"
              placeholder="variables JSON (opcional)"
              value={variables}
              onChange={(e) => setVariables(e.target.value)}
            />
            <Button onClick={() => void run()} disabled={running}>
              <Play className="mr-2 h-4 w-4" /> {running ? "Ejecutando…" : "Ejecutar"}
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Resultado</CardTitle>
            <CardDescription>{result?.errors ? "Con errores" : "Respuesta"}</CardDescription>
          </CardHeader>
          <CardContent>
            {error && <p className="text-destructive">{error}</p>}
            <pre className="max-h-96 overflow-auto rounded-md bg-muted p-3 text-xs">
              {JSON.stringify(result ?? { hint: "Ejecuta una operación" }, null, 2)}
            </pre>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
