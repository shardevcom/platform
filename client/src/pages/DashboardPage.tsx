"use client"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Users, Key, Shield, Mail, BarChart3 } from "lucide-react"
import { cn } from "@/lib/utils"
import { useAuth } from "@/hooks/useAuth"

const stats = [
  { title: "Usuarios", value: "1,234", icon: Users, color: "text-blue-500 bg-blue-100" },
  { title: "Passkeys", value: "56", icon: Key, color: "text-green-500 bg-green-100" },
  { title: "2FA Activos", value: "89%", icon: Shield, color: "text-purple-500 bg-purple-100" },
  { title: "OTP Enviados", value: "1,234", icon: Mail, color: "text-orange-500 bg-orange-100" },
]

export function DashboardPage() {
  const { user } = useAuth()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>
        <p className="text-muted-foreground">
          Bienvenido, {user?.name || "Usuario"} — Panel de control
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.title} className="border-l-4">
            <CardContent className="flex items-center justify-between">
              <div className="space-y-1">
                <p className="text-sm font-medium text-muted-foreground">{stat.title}</p>
                <p className="text-2xl font-bold">{stat.value}</p>
              </div>
              <div className={cn("p-3 rounded-full", stat.color)}>
                <stat.icon className="h-5 w-5 text-white" />
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Actividad Reciente</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              <div className="flex items-center justify-between text-sm">
                <span>Nuevo usuario registrado</span>
                <span className="text-muted-foreground">Hace 5 min</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span>Passkey registrado</span>
                <span className="text-muted-foreground">Hace 15 min</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span>2FA habilitado</span>
                <span className="text-muted-foreground">Hace 1 hora</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Accesos Rápidos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-2 sm:grid-cols-2">
              <a href="/passkeys" className={cn("p-4 rounded-lg border hover:bg-accent transition-colors")}>
                <h3 className="font-medium">Gestionar Passkeys</h3>
                <p className="text-sm text-muted-foreground">Registrar y gestionar passkeys</p>
              </a>
              <a href="/2fa" className={cn("p-4 rounded-lg border hover:bg-accent transition-colors")}>
                <h3 className="font-medium">Configurar 2FA</h3>
                <p className="text-sm text-muted-foreground">Autenticación de dos factores</p>
              </a>
              <a href="/otp" className={cn("p-4 rounded-lg border hover:bg-accent transition-colors")}>
                <h3 className="font-medium">Configurar OTP</h3>
                <p className="text-sm text-muted-foreground">Códigos por correo electrónico</p>
              </a>
              <a href="/profile" className={cn("p-4 rounded-lg border hover:bg-accent transition-colors")}>
                <h3 className="font-medium">Mi Perfil</h3>
                <p className="text-sm text-muted-foreground">Configuración de cuenta</p>
              </a>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
