"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useAuth } from "@/hooks/useAuth"
import { Mail, Send, CheckCircle, Loader2 } from "lucide-react"

export function OTPPage() {
  const { user } = useAuth()
  const [activeTab, setActiveTab] = useState("send")
  const [email, setEmail] = useState("")
  const [code, setCode] = useState("")
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const handleSend = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)
    try {
      const res = await fetch("/api/otp/send", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("access_token")}`
        },
        body: JSON.stringify({ email, purpose: "login" })
      })
      if (!res.ok) throw new Error("Error enviando OTP")
      setSuccess("Código enviado al correo")
      setActiveTab("verify")
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error enviando OTP")
    } finally {
      setLoading(false)
    }
  }

  const handleVerify = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)
    try {
      const res = await fetch("/api/otp/verify", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("access_token")}`
        },
        body: JSON.stringify({ email, code })
      })
      if (!res.ok) throw new Error("Código inválido o expirado")
      setSuccess("OTP verificado correctamente")
      setCode("")
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error verificando OTP")
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-md mx-auto space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">OTP por Correo Electrónico</h1>
        <p className="text-muted-foreground">
          Autenticación mediante códigos de un solo uso enviados por email
        </p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
        <TabsList className="grid w-full grid-cols-2">
          <TabsTrigger value="send">Enviar OTP</TabsTrigger>
          <TabsTrigger value="verify">Verificar OTP</TabsTrigger>
        </TabsList>

        <TabsContent value="send" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Enviar Código OTP</CardTitle>
              <CardDescription>Recibirás un código de 6 dígitos en tu correo</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSend} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="email">Correo Electrónico</Label>
                  <Input
                    id="email"
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="usuario@ejemplo.com"
                    required
                  />
                </div>
                <Button type="submit" disabled={loading} className="w-full">
                  {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : "Enviar Código"}
                </Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="verify" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Verificar Código OTP</CardTitle>
              <CardDescription>Ingresa el código de 6 dígitos recibido por correo</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleVerify} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="code">Código OTP (6 dígitos)</Label>
                  <Input
                    id="code"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    placeholder="000000"
                    maxLength={6}
                    inputMode="numeric"
                    pattern="[0-9]*"
                    required
                  />
                </div>
                <Button type="submit" disabled={loading || code.length !== 6} className="w-full">
                  {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : "Verificar"}
                </Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {(success || error) && (
        <div className={cn("p-4 rounded-lg text-sm", success ? "bg-green-50 text-green-700" : "bg-red-50 text-red-700")}>
          {success || error}
        </div>
      )}
    </div>
  )
}
