"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useAuth } from "@/hooks/useAuth"
import { Smartphone, RotateCcw, Shield, Key, Loader2 } from "lucide-react"

export function StepUpMFAPage() {
  const { user } = useAuth()
  const [challenge, setChallenge] = useState<{ type: string; fingerprint: string } | null>(null)
  const [code, setCode] = useState("")
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [verified, setVerified] = useState(false)

  const handleChallenge = async (type: "totp" | "recovery") => {
    try {
      const res = await fetch("/api/2fa/step-up/challenge", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("access_token")}`
        },
        body: JSON.stringify({ type })
      })
      if (!res.ok) throw new Error("Error generando challenge")
      const data = await res.json()
      setChallenge({ type, fingerprint: data.fingerprint })
    } catch (err) {
      console.error(err)
    }
  }

  const handleVerify = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!challenge) return
    try {
      const res = await fetch("/api/2fa/step-up/verify", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("access_token")}`
        },
        body: JSON.stringify({ challenge: challenge.fingerprint, code, type: challenge.type })
      })
      if (!res.ok) throw new Error("Challenge inválido")
      setVerified(true)
    } catch (err) {
      // Handle error
    }
  }

  return (
    <div className="max-w-md mx-auto space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Verificación de Paso Adicional (Step-Up MFA)</h1>
        <p className="text-muted-foreground">
          Se requiere verificación adicional para esta acción sensible
        </p>
      </div>

      <Tabs defaultValue="totp" className="space-y-4">
        <TabsList className="grid w-full grid-cols-2">
          <TabsTrigger value="totp">Autenticador (TOTP)</TabsTrigger>
          <TabsTrigger value="recovery">Código de Recuperación</TabsTrigger>
        </TabsList>

        <TabsContent value="totp" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Verificación TOTP</CardTitle>
              <CardDescription>Ingresa el código de 6 dígitos de tu aplicación autenticadora</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleVerify} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="totp-code">Código TOTP (6 dígitos)</Label>
                  <Input
                    id="totp-code"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    placeholder="000000"
                    maxLength={6}
                    inputMode="numeric"
                    required
                    disabled={loading}
                  />
                </div>
                <Button type="submit" disabled={loading || code.length !== 6} className="w-full">
                  {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : "Verificar"}
                </Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="recovery" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Código de Recuperación</CardTitle>
              <CardDescription>Usa uno de tus códigos de recuperación guardados</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleVerify} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="recovery-code">Código de Recuperación</Label>
                  <Input
                    id="recovery-code"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    placeholder="XXXXXXXX"
                    maxLength={10}
                    required
                    disabled={loading}
                  />
                </div>
                <Button type="submit" disabled={loading || !code} className="w-full">
                  Verificar
                </Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
