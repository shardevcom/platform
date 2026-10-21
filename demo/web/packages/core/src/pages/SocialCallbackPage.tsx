"use client"

import { useEffect, useState } from "react"
import { useNavigate } from "react-router-dom"

import { useAuth } from "../providers/AuthProvider"
import { errorMessage } from "../lib/errors"

/**
 * Landing point after the OAuth provider redirects back to the API: reads the
 * token from the fragment, exchanges it for the current user and enters the
 * shell. The fragment is removed so the token does not linger in history.
 */
export function SocialCallbackPage() {
  const navigate = useNavigate()
  const { completeSocialLogin } = useAuth()
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const token = new URLSearchParams(window.location.hash.slice(1)).get("token")

    window.history.replaceState({}, "", window.location.pathname)

    if (!token) {
      setError("El proveedor no devolvió un token de acceso.")
      return
    }

    completeSocialLogin(token)
      .then(() => navigate("/dashboard", { replace: true }))
      .catch((err) => setError(errorMessage(err, "No se pudo completar el acceso social")))
  }, [completeSocialLogin, navigate])

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4 py-12">
      <p className="text-sm text-muted-foreground" role="status">
        {error ?? "Completando el acceso…"}
      </p>
    </div>
  )
}
