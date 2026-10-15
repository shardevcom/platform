"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useAuth } from "@/hooks/useAuth"
import { cn } from "@/lib/utils"
import { QrCode, Smartphone, RotateCcw, Shield, ArrowRight } from "lucide-react"
import { QRCodeSVG } from "@/components/ui/qrcode"
import { useState } from "react"
import { useAuth } from "@/hooks/useAuth"

export function TwoFactorPage() {
  const { user } = useAuth()
  const [activeTab, setActiveTab] = useState("enable")
  const [secret, setSecret] = useState("")
  const [qrCode, setQrCode] = useState("")
  const [code, setCode] = useState("")
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const has2FA = user?.two_factor_confirmed_at

  const handleEnable = async () => {
    try {
      setError(null)
      const res = await fetch("/api/2fa/enable", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("access_token")}`
        }
      })
      if (!res.ok) throw new Error("Error al generar secret")
      const data = await res.json()
      setSecret(data.secret)
      setQrCode(data.qr_code)
      setActiveTab("verify")
    } catch (err) {
      console.error(err)
    }
  }

  const handleVerify = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      setError(null)
      const res = await fetch("/api/2fa/verify", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("access_token")}`
        },
        body: JSON.stringify({ code })
      })
      if (!res.ok) throw new Error("Código inválido")
      setSuccess("2FA habilitado correctamente")
      setActiveTab("recovery")
      // Refresh user data would be needed here
    } catch (err) {
      setError(err instanceof Error ? err.message : "Código inválido")
    }
  }

  const handleRecovery = async (e: React.FormEvent) => {
    e.preventDefault()
    // Implementation for recovery codes
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Autenticación de Dos Factores (2FA)</h1>
        <p className="text-muted-foreground">
          Añade una capa extra de seguridad a tu cuenta
        </p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
        <TabsList className="grid w-full grid-cols-3">
          <TabsTrigger value="enable" disabled={has2FA}>
            {has2FA ? "Habilitado" : "Activar 2FA"}
          </TabsTrigger>
          <TabsTrigger value="verify" disabled={has2FA}>
            Verificar
          </TabsTrigger>
          <TabsTrigger value="recovery" disabled={!has2FA}>
            Códigos de Recuperación
          </TabsTrigger>
        </TabsList>

        <TabsContent value="enable" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Activar Autenticación de Dos Factores</CardTitle>
              <CardDescription>
                Usa una aplicación de autenticación (Google Authenticator, Authy, etc.)
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {!secret ? (
                <Button onClick={handleEnable} disabled={loading}>
                  {loading ? "Generando..." : "Activar 2FA"}
                </Button>
              ) : (
                <div className="space-y-4">
                  <div className="text-center">
                    <QRCodeSVG
                      value={`otpauth://totp/Shardev:${user?.email}?secret=${secret}&issuer=Shardev Platform`}
                      size={200}
                    />
                    <p className="font-mono text-lg break-all">{secret}</p>
                  </div>
                  <Separator />Separar contenido
                  <form onSubmit={handleVerify}>
                    <div className="space-y-2">
                      <Label htmlFor="code">Código de 6 dígitos</Label>
                      <Input
                        id="code"
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                        placeholder="000000"
                        maxLength={6}
                        autoComplete="one-time-code"
                        inputMode="numeric"
                      />
                    </div>
                    <Button type="submit" disabled={!code || code.length !== 6}>
                      Verificar y Activar
                    </Button>
                  </form>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="verify" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Verificar 2FA</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleVerify} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="code">Código de 6 dígitos</Label>
                  <Input
                    id="code"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    placeholder="000000"
                    maxLength={6}
                    autoComplete="one-time-code"
                    inputMode="numeric"
                  />
                </div>
                <Button type="submit" disabled={!code || code.length !== 6} className="w-full">
                  Verificar
                </Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="recovery" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Códigos de Recuperación</CardTitle>
              <CardDescription>
                Guarda estos códigos en un lugar seguro. Cada código solo se puede usar una vez.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-2 sm:grid-cols-2">
                {recoveryCodes.map((code, i) => (
                  <div key={i} className="font-mono bg-muted p-2 rounded text-center">
                    {code}
                  </div>
                ))}
              </div>
              <Button variant="outline" onClick={() => {/* regenerar */}}>
                Regenerar Códigos
              </Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
