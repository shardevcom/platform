"use client"

import { useEffect, useState } from "react"

import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle, Input, Label } from "@shardev/ui"

import { account } from "../lib/endpoints"
import { errorMessage } from "../lib/errors"
import type { Preferences } from "../types"

const THEMES: Preferences["theme"][] = ["system", "light", "dark"]

export function PreferencesPage() {
  const [preferences, setPreferences] = useState<Preferences | null>(null)
  const [status, setStatus] = useState<"idle" | "saving" | "saved">("idle")
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    account
      .preferences()
      .then(setPreferences)
      .catch((err: unknown) => setError(errorMessage(err, "No se pudieron cargar las preferencias.")))
  }, [])

  const update = <K extends keyof Preferences>(key: K, value: Preferences[K]) => {
    setPreferences((current) => (current ? { ...current, [key]: value } : current))
    setStatus("idle")
  }

  const toggleNotification = (key: string, value: boolean) => {
    setPreferences((current) =>
      current ? { ...current, notifications: { ...current.notifications, [key]: value } } : current,
    )
    setStatus("idle")
  }

  const save = async () => {
    if (!preferences) return

    try {
      setError(null)
      setStatus("saving")
      setPreferences(await account.savePreferences(preferences))
      setStatus("saved")
    } catch (err) {
      setError(errorMessage(err, "No se pudieron guardar las preferencias."))
      setStatus("idle")
    }
  }

  if (!preferences) {
    return (
      <div className="text-sm text-muted-foreground">
        {error ?? "Cargando preferencias…"}
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <h2 className="text-2xl font-bold">Preferencias</h2>
        <p className="text-muted-foreground">Ajustes de interfaz y avisos de tu cuenta.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Interfaz</CardTitle>
          <CardDescription>Se aplican a tu cuenta en todas las plataformas.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="theme">Tema</Label>
            <select
              id="theme"
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={preferences.theme}
              onChange={(event) => update("theme", event.target.value as Preferences["theme"])}
            >
              {THEMES.map((theme) => (
                <option key={theme} value={theme}>
                  {theme}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="language">Idioma</Label>
            <Input
              id="language"
              value={preferences.language}
              onChange={(event) => update("language", event.target.value)}
              placeholder="es"
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Notificaciones</CardTitle>
          <CardDescription>Elige por qué canales quieres recibir avisos.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {Object.entries(preferences.notifications).map(([channel, enabled]) => (
            <label key={channel} className="flex items-center justify-between gap-4 text-sm">
              <span className="capitalize">{channel}</span>
              <input
                type="checkbox"
                className="h-4 w-4"
                checked={enabled}
                onChange={(event) => toggleNotification(channel, event.target.checked)}
              />
            </label>
          ))}
        </CardContent>
      </Card>

      <div className="flex items-center gap-3">
        <Button onClick={() => void save()} disabled={status === "saving"}>
          {status === "saving" ? "Guardando..." : "Guardar preferencias"}
        </Button>
        {status === "saved" && <span className="text-sm text-emerald-600">Guardado.</span>}
        {error && <span className="text-sm text-destructive">{error}</span>}
      </div>
    </div>
  )
}
