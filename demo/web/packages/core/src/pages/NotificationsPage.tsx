"use client"

import { useCallback, useEffect, useState } from "react"

import { Badge, Button, Card, CardContent, CardDescription, CardHeader, CardTitle } from "@shardev/ui"

import { account } from "../lib/endpoints"
import { errorMessage } from "../lib/errors"
import type { NotificationsPayload } from "../types"

export function NotificationsPage() {
  const [payload, setPayload] = useState<NotificationsPayload | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    account
      .notifications()
      .then(setPayload)
      .catch((err: unknown) => setError(errorMessage(err, "No se pudieron cargar las notificaciones.")))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const act = async (action: () => Promise<unknown>) => {
    try {
      setError(null)
      await action()
      load()
    } catch (err) {
      setError(errorMessage(err, "No se pudo completar la acción."))
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">Notificaciones</h2>
          <p className="text-muted-foreground">
            {payload ? `${payload.unread_count} sin leer` : "Cargando…"}
          </p>
        </div>
        <Button
          variant="outline"
          onClick={() => void act(() => account.readAllNotifications())}
          disabled={!payload || payload.unread_count === 0}
        >
          Marcar todas como leídas
        </Button>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}

      {payload?.items.length === 0 && (
        <Card>
          <CardContent className="py-10 text-center text-sm text-muted-foreground">
            No tienes notificaciones.
          </CardContent>
        </Card>
      )}

      <div className="space-y-3">
        {payload?.items.map((notification) => (
          <Card key={notification.id} className={notification.read_at ? "opacity-70" : undefined}>
            <CardHeader className="pb-2">
              <div className="flex items-center justify-between gap-3">
                <CardTitle className="text-base">{notification.type}</CardTitle>
                {!notification.read_at && <Badge>Nueva</Badge>}
              </div>
              <CardDescription>{notification.created_at ?? ""}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <pre className="overflow-x-auto rounded bg-muted p-2 text-xs">
                {JSON.stringify(notification.data, null, 2)}
              </pre>
              <div className="flex gap-2">
                {!notification.read_at && (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => void act(() => account.readNotification(notification.id))}
                  >
                    Marcar leída
                  </Button>
                )}
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => void act(() => account.deleteNotification(notification.id))}
                >
                  Eliminar
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
