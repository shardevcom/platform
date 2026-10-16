"use client"

import { Badge } from "@shardev/ui"
import { Button } from "@shardev/ui"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@shardev/ui"
import { useAuth } from "../providers/AuthProvider"

export function ProfilePage() {
  const { user, guard, logout } = useAuth()

  if (!user) return null

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold">Perfil</h2>
        <p className="text-muted-foreground">Identidad resuelta por GET /auth/me.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{user.name}</CardTitle>
          <CardDescription>{user.email}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">Plataforma:</span>
            <Badge variant="outline" className="uppercase">{guard}</Badge>
            <span className="text-muted-foreground">· Guard:</span>
            <span className="font-mono">{user.guard}</span>
          </div>

          <div>
            <p className="mb-2 text-sm font-medium">Permisos del token ({user.abilities.length})</p>
            <div className="flex flex-wrap gap-1.5">
              {user.abilities.map((ability) => (
                <Badge key={ability} variant="secondary" className="font-mono text-[10px]">
                  {ability}
                </Badge>
              ))}
            </div>
          </div>

          <Button variant="destructive" onClick={() => void logout()}>
            Cerrar sesión
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}
