"use client"

import { useEffect, useState } from "react"
import { Link, useSearchParams } from "react-router-dom"

import { Button, Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle, Separator } from "@shardev/ui"

export function VerifyEmailPage() {
  const [params] = useSearchParams()
  const verifyUrl = params.get("verify_url")
  const [state, setState] = useState<"loading" | "done" | "error">("loading")
  const [message, setMessage] = useState("")

  useEffect(() => {
    if (!verifyUrl) return

    let cancelled = false
    const url = new URL(verifyUrl, window.location.origin)

    fetch(url.pathname + url.search, { headers: { Accept: "application/json" } })
      .then(async (response) => {
        if (!response.ok) {
          const body = (await response.json().catch(() => null)) as { message?: string } | null
          throw new Error(body?.message ?? "El enlace no es válido o caducó.")
        }
        if (!cancelled) setState("done")
      })
      .catch((error: unknown) => {
        if (cancelled) return
        setMessage(error instanceof Error ? error.message : "El enlace no es válido o caducó.")
        setState("error")
      })

    return () => {
      cancelled = true
    }
  }, [verifyUrl])

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4 py-12">
      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <CardTitle className="text-2xl">Verificación de email</CardTitle>
          <CardDescription>Confirmamos tu dirección para activar la cuenta.</CardDescription>
        </CardHeader>
        <CardContent className="text-center text-sm">
          {!verifyUrl && (
            <p className="text-destructive" role="alert">
              Falta el enlace de verificación.
            </p>
          )}
          {verifyUrl && state === "loading" && <p className="text-muted-foreground">Verificando…</p>}
          {state === "done" && <p className="text-emerald-600">Tu email quedó verificado.</p>}
          {state === "error" && (
            <p className="text-destructive" role="alert">
              {message}
            </p>
          )}
        </CardContent>
        <CardFooter className="flex flex-col space-y-3">
          <Separator />
          <Button asChild className="w-full">
            <Link to="/login">Ir al login</Link>
          </Button>
        </CardFooter>
      </Card>
    </div>
  )
}
