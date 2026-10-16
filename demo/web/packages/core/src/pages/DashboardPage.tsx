"use client"

import { Link } from "react-router-dom"
import { useQuery } from "@tanstack/react-query"
import { Settings, Shield, LayoutGrid, Code2, KeyRound } from "lucide-react"

import { Card, CardContent, CardDescription, CardHeader, CardTitle, Badge } from "@shardev/ui"

import { useAuth } from "../providers/AuthProvider"
import { widgets } from "../lib/endpoints"

const SECTIONS = [
  { to: "/configuration", title: "Configuration", description: "Providers públicos y admin", icon: Settings },
  { to: "/2fa", title: "Two-Factor", description: "TOTP, QR y recovery codes", icon: Shield },
  { to: "/widgets", title: "Widgets", description: "Métricas, charts y tablas", icon: LayoutGrid },
  { to: "/graphql", title: "GraphQL", description: "Queries y mutaciones", icon: Code2 },
]

export function DashboardPage() {
  const { user, guard } = useAuth()

  const widgetsQuery = useQuery({
    queryKey: ["dashboard", "widgets"],
    queryFn: () => widgets.get(),
  })

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold">Hola{user?.name ? `, ${user.name}` : ""}</h2>
        <p className="text-muted-foreground">
          Cliente demo consumiendo la plataforma <span className="font-medium uppercase">{guard}</span>.
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Widgets</CardDescription>
            <CardTitle className="text-3xl">
              {widgetsQuery.data ? Object.keys(widgetsQuery.data).length : "—"}
            </CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-muted-foreground">vía GET /widgets</CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Permisos del token</CardDescription>
            <CardTitle className="flex items-center gap-2 text-3xl">
              <KeyRound className="h-6 w-6 text-muted-foreground" />
              {user?.abilities.length ?? 0}
            </CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-muted-foreground">abilities en el PAT</CardContent>
        </Card>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {SECTIONS.map((section) => (
          <Link key={section.to} to={section.to}>
            <Card className="h-full transition-colors hover:border-primary/50">
              <CardHeader>
                <section.icon className="mb-2 h-6 w-6 text-primary" />
                <CardTitle>{section.title}</CardTitle>
                <CardDescription>{section.description}</CardDescription>
              </CardHeader>
            </Card>
          </Link>
        ))}
      </div>

      {user && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Scopes del token</CardTitle>
            <CardDescription>Vocabulario derivado del ResourceRegistry</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-1.5">
            {user.abilities.map((ability) => (
              <Badge key={ability} variant="secondary" className="font-mono text-[10px]">
                {ability}
              </Badge>
            ))}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
