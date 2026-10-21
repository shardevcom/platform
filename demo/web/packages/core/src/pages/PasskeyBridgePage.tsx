"use client"

import { useEffect, useMemo, useState } from "react"

import { Button } from "@shardev/ui"
import type { Guard } from "@shardev/plugin-kit"

import { setPlatform, setToken } from "../lib/api"
import { buildPasskeyReturnUrl, passkeyReturnTarget } from "../lib/passkey-return"
import { auth } from "../lib/endpoints"
import { errorMessage } from "../lib/errors"
import { createPasskey, getPasskey } from "../lib/webauthn"

/**
 * Desktop hand-off: the app opens this page in the system browser, the user
 * completes the WebAuthn ceremony here and the result returns through the
 * loopback callback. Register mode receives the desktop token in the fragment.
 */
export function PasskeyBridgePage({
  guard,
  onFinish,
}: {
  guard: Guard
  onFinish?: (url: string) => void
}) {
  const params = useMemo(() => new URLSearchParams(window.location.search), [])
  const state = params.get("state") ?? ""
  const target = useMemo(() => passkeyReturnTarget(params.get("return")), [params])
  const mode = params.get("mode") === "register" ? "register" : "login"
  const email = params.get("email") ?? ""
  const name = params.get("name") ?? undefined
  const token = useMemo(
    () => new URLSearchParams(window.location.hash.slice(1)).get("token") ?? undefined,
    [],
  )
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(
    target && state ? null : "El enlace de la app no es válido.",
  )

  useEffect(() => {
    if (token) {
      window.history.replaceState({}, "", window.location.pathname + window.location.search)
    }
  }, [token])

  const finish = (values: Record<string, string>) => {
    if (!target) return

    const url = buildPasskeyReturnUrl(target, state, values)

    if (onFinish) {
      onFinish(url)
    } else {
      window.location.assign(url)
    }
  }

  const run = async () => {
    if (!target || !state) {
      setError("El enlace de la app no es válido.")
      return
    }

    setBusy(true)
    setError(null)
    setPlatform(guard)

    try {
      if (mode === "register") {
        if (!token) throw new Error("Falta el token de la app para registrar la passkey.")

        setToken(token)

        const options = await auth.webauthnRegisterOptions()
        const credential = await createPasskey(options.publicKey)
        await auth.webauthnRegister(credential, name)

        setToken(null)
        finish({ status: "registered" })
      } else {
        const options = await auth.webauthnLoginOptions(email || undefined)
        const credential = await getPasskey(options.publicKey)
        const result = await auth.webauthnLogin(credential, "desktop")

        finish({ token: result.access_token })
      }
    } catch (err) {
      const message = errorMessage(err, "No se pudo completar la passkey.")

      if (mode === "register") {
        setToken(null)
      }

      setBusy(false)

      if (target) {
        finish({ error: message })
      } else {
        setError(message)
      }
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4 py-12">
      <div className="w-full max-w-md space-y-4 text-center">
        <h1 className="text-2xl font-semibold">
          {mode === "register" ? "Añadir passkey" : "Entrar con passkey"}
        </h1>
        <p className="text-sm text-muted-foreground">
          {mode === "register"
            ? "Confirma con la biometría del dispositivo para vincular esta passkey a la app de escritorio."
            : `Completa la passkey${email ? ` para ${email}` : ""} y vuelve a la aplicación.`}
        </p>
        {error && (
          <p className="text-sm text-destructive" role="alert">
            {error}
          </p>
        )}
        <Button type="button" className="w-full" onClick={() => void run()} disabled={busy || !target}>
          {busy ? "Esperando passkey…" : "Continuar con passkey"}
        </Button>
      </div>
    </div>
  )
}
