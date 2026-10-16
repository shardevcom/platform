"use client"

import { useState } from "react"
import { toast } from "sonner"
import { ShieldCheck, ShieldOff, KeyRound, RefreshCw } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { ApiError } from "@/lib/api"
import { twoFactor } from "@/lib/endpoints"
import { useAuth } from "@/providers/AuthProvider"
import type { TwoFactorSetup } from "@/types"

export function TwoFactorPage() {
  const { platform } = useAuth()
  const [setup, setSetup] = useState<TwoFactorSetup | null>(null)
  const [code, setCode] = useState("")
  const [password, setPassword] = useState("")
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([])
  const [busy, setBusy] = useState(false)

  const guard = (fn: () => Promise<void>) => async () => {
    setBusy(true)
    try {
      await fn()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Operación fallida")
    } finally {
      setBusy(false)
    }
  }

  const enable = guard(async () => {
    const result = await twoFactor.enable()
    setSetup(result)
    toast.success("Secreto generado. Escanea el QR y verifica.")
  })

  const verify = guard(async () => {
    await twoFactor.verify(code)
    toast.success("2FA habilitado")
    setCode("")
  })

  const disable = guard(async () => {
    await twoFactor.disable(password, code || undefined)
    toast.success("2FA deshabilitado")
    setSetup(null)
    setPassword("")
    setCode("")
  })

  const loadRecovery = guard(async () => {
    const result = await twoFactor.recoveryCodes()
    setRecoveryCodes(result.recovery_codes)
  })

  const regenerate = guard(async () => {
    const result = await twoFactor.regenerate()
    setRecoveryCodes(result.recovery_codes)
    toast.success("Recovery codes regenerados")
  })

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold">Two-Factor Authentication</h2>
        <p className="text-muted-foreground">
          TOTP sobre el guard <span className="font-medium uppercase">{platform}</span> — enable/verify/qr-code/recovery-codes.
        </p>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <ShieldCheck className="h-5 w-5 text-primary" /> Habilitar
            </CardTitle>
            <CardDescription>POST /enable genera el secreto; POST /verify lo confirma.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <Button onClick={() => void enable()} disabled={busy}>
              <KeyRound className="mr-2 h-4 w-4" /> Generar secreto
            </Button>

            {setup && (
              <div className="space-y-3">
                {setup.qr_code && (
                  <div
                    className="mx-auto w-48 rounded-md border bg-white p-2"
                    dangerouslySetInnerHTML={{ __html: setup.qr_code }}
                  />
                )}
                <p className="text-center font-mono text-xs text-muted-foreground">{setup.secret}</p>

                <div className="space-y-1">
                  <Label htmlFor="code">Código TOTP</Label>
                  <div className="flex gap-2">
                    <Input id="code" value={code} maxLength={6} onChange={(e) => setCode(e.target.value)} placeholder="123456" />
                    <Button onClick={() => void verify()} disabled={busy || code.length !== 6}>Verificar</Button>
                  </div>
                </div>

                <div>
                  <p className="mb-1 text-sm font-medium">Recovery codes</p>
                  <div className="grid grid-cols-2 gap-1 font-mono text-xs">
                    {setup.recovery_codes.map((rc) => <span key={rc} className="rounded bg-muted px-2 py-1">{rc}</span>)}
                  </div>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <ShieldOff className="h-5 w-5 text-destructive" /> Deshabilitar
            </CardTitle>
            <CardDescription>POST /disable requiere tu contraseña (y código si está confirmado).</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="space-y-1">
              <Label htmlFor="pw">Contraseña</Label>
              <Input id="pw" type="password" value={password} onChange={(e) => setPassword(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="dcode">Código (opcional)</Label>
              <Input id="dcode" value={code} onChange={(e) => setCode(e.target.value)} />
            </div>
            <Button variant="destructive" onClick={() => void disable()} disabled={busy || !password}>
              Deshabilitar
            </Button>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader className="flex-row items-center justify-between space-y-0">
          <div>
            <CardTitle className="text-base">Recovery codes</CardTitle>
            <CardDescription>GET /recovery-codes · POST /regenerate-recovery-codes</CardDescription>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => void loadRecovery()} disabled={busy}>
              <RefreshCw className="mr-2 h-4 w-4" /> Ver
            </Button>
            <Button variant="outline" size="sm" onClick={() => void regenerate()} disabled={busy}>
              Regenerar
            </Button>
          </div>
        </CardHeader>
        {recoveryCodes.length > 0 && (
          <CardContent>
            <div className="grid grid-cols-2 gap-1 font-mono text-xs md:grid-cols-4">
              {recoveryCodes.map((rc) => <span key={rc} className="rounded bg-muted px-2 py-1">{rc}</span>)}
            </div>
          </CardContent>
        )}
      </Card>
    </div>
  )
}
