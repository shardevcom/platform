"use client"

import { useCallback, useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
  Input,
  Label,
  Separator,
} from "@shardev/ui"

import { useAuth } from "../providers/AuthProvider"
import { account, auth } from "../lib/endpoints"
import { errorMessage } from "../lib/errors"
import { createPasskey, isWebAuthnAvailable } from "../lib/webauthn"
import type { Passkey } from "../types"

const profileSchema = z.object({
  name: z.string().min(1, "El nombre es obligatorio"),
  email: z.string().email("Email inválido"),
})

const passwordSchema = z
  .object({
    current_password: z.string().min(1, "La contraseña actual es obligatoria"),
    password: z.string().min(8, "Mínimo 8 caracteres"),
    password_confirmation: z.string().min(8, "Mínimo 8 caracteres"),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ["password_confirmation"],
    message: "Las contraseñas no coinciden",
  })

type ProfileForm = z.infer<typeof profileSchema>
type PasswordForm = z.infer<typeof passwordSchema>

export function ProfilePage() {
  const { user, guard, logout, refreshUser } = useAuth()
  const [profileMessage, setProfileMessage] = useState<string | null>(null)
  const [passwordMessage, setPasswordMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [passkeys, setPasskeys] = useState<Passkey[]>([])
  const [passkeyBusy, setPasskeyBusy] = useState(false)
  const [disableReason, setDisableReason] = useState("")
  const [disablePassword, setDisablePassword] = useState("")

  const profileForm = useForm<ProfileForm>({
    resolver: zodResolver(profileSchema),
    defaultValues: { name: user?.name ?? "", email: user?.email ?? "" },
  })

  const passwordForm = useForm<PasswordForm>({ resolver: zodResolver(passwordSchema) })

  const loadPasskeys = useCallback(() => {
    auth
      .passkeys()
      .then((payload) => setPasskeys(payload.items))
      .catch(() => setPasskeys([]))
  }, [])

  useEffect(() => {
    loadPasskeys()
  }, [loadPasskeys])

  useEffect(() => {
    profileForm.reset({ name: user?.name ?? "", email: user?.email ?? "" })
  }, [user, profileForm])

  if (!user) return null

  const saveProfile = async (data: ProfileForm) => {
    try {
      setError(null)
      setProfileMessage(null)
      const result = await account.updateProfile(data)
      await refreshUser()
      setProfileMessage(
        result.user.email_verified ? "Perfil actualizado." : "Perfil actualizado. Revisa tu email para verificarlo.",
      )
    } catch (err) {
      setError(errorMessage(err, "No se pudo actualizar el perfil."))
    }
  }

  const savePassword = async (data: PasswordForm) => {
    try {
      setError(null)
      setPasswordMessage(null)
      await account.changePassword(data)
      passwordForm.reset()
      setPasswordMessage("Contraseña actualizada.")
    } catch (err) {
      setError(errorMessage(err, "No se pudo cambiar la contraseña."))
    }
  }

  const resendVerification = async () => {
    try {
      setError(null)
      await auth.resendVerification()
      setProfileMessage("Te enviamos un nuevo enlace de verificación.")
    } catch (err) {
      setError(errorMessage(err, "No se pudo enviar el enlace."))
    }
  }

  const addPasskey = async () => {
    try {
      setError(null)
      setPasskeyBusy(true)
      const options = await auth.webauthnRegisterOptions()
      const credential = await createPasskey(options.publicKey)
      await auth.webauthnRegister(credential, "Passkey")
      loadPasskeys()
    } catch (err) {
      setError(errorMessage(err, "No se pudo registrar la passkey."))
    } finally {
      setPasskeyBusy(false)
    }
  }

  const removePasskey = async (id: number) => {
    try {
      setError(null)
      await auth.removePasskey(id)
      loadPasskeys()
    } catch (err) {
      setError(errorMessage(err, "No se pudo eliminar la passkey."))
    }
  }

  const disableAccount = async () => {
    try {
      setError(null)
      await account.disable(disablePassword, disableReason || undefined)
      await logout()
    } catch (err) {
      setError(errorMessage(err, "No se pudo deshabilitar la cuenta."))
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <h2 className="text-2xl font-bold">Perfil</h2>
        <p className="text-muted-foreground">Identidad resuelta por GET /auth/me.</p>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Cuenta</CardTitle>
          <CardDescription className="flex items-center gap-2">
            {user.email}
            {user.email_verified ? (
              <Badge variant="secondary">Verificado</Badge>
            ) : (
              <Badge variant="outline">Sin verificar</Badge>
            )}
            {!user.email_verified && (
              <Button size="sm" variant="link" className="h-auto p-0" onClick={() => void resendVerification()}>
                Reenviar enlace
              </Button>
            )}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <form onSubmit={profileForm.handleSubmit(saveProfile)} className="space-y-3">
            <div className="space-y-2">
              <Label htmlFor="name">Nombre</Label>
              <Input id="name" {...profileForm.register("name")} disabled={profileForm.formState.isSubmitting} />
              {profileForm.formState.errors.name && (
                <p className="text-sm text-destructive">{profileForm.formState.errors.name.message}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="profile-email">Email</Label>
              <Input
                id="profile-email"
                type="email"
                {...profileForm.register("email")}
                disabled={profileForm.formState.isSubmitting}
              />
              {profileForm.formState.errors.email && (
                <p className="text-sm text-destructive">{profileForm.formState.errors.email.message}</p>
              )}
            </div>
            <Button type="submit" disabled={profileForm.formState.isSubmitting}>
              Guardar perfil
            </Button>
            {profileMessage && <span className="ml-3 text-sm text-emerald-600">{profileMessage}</span>}
          </form>

          <Separator />

          <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">Plataforma:</span>
            <Badge variant="outline" className="uppercase">{guard}</Badge>
            <span className="text-muted-foreground">· Guard:</span>
            <span className="font-mono">{user.guard}</span>
          </div>

          <div>
            <p className="mb-2 text-sm font-medium">Permisos del token ({user.abilities.length})</p>
            <div className="flex flex-wrap gap-1.5">
              {user.abilities.map((ability) => (
                <Badge key={ability} variant="secondary" className="font-mono text-[10px]">
                  {ability}
                </Badge>
              ))}
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Contraseña</CardTitle>
          <CardDescription>Cambia la contraseña de tu cuenta.</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={passwordForm.handleSubmit(savePassword)} className="space-y-3">
            <div className="space-y-2">
              <Label htmlFor="current_password">Contraseña actual</Label>
              <Input
                id="current_password"
                type="password"
                {...passwordForm.register("current_password")}
                disabled={passwordForm.formState.isSubmitting}
              />
              {passwordForm.formState.errors.current_password && (
                <p className="text-sm text-destructive">{passwordForm.formState.errors.current_password.message}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="new_password">Nueva contraseña</Label>
              <Input
                id="new_password"
                type="password"
                {...passwordForm.register("password")}
                disabled={passwordForm.formState.isSubmitting}
              />
              {passwordForm.formState.errors.password && (
                <p className="text-sm text-destructive">{passwordForm.formState.errors.password.message}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="new_password_confirmation">Repetir contraseña</Label>
              <Input
                id="new_password_confirmation"
                type="password"
                {...passwordForm.register("password_confirmation")}
                disabled={passwordForm.formState.isSubmitting}
              />
              {passwordForm.formState.errors.password_confirmation && (
                <p className="text-sm text-destructive">
                  {passwordForm.formState.errors.password_confirmation.message}
                </p>
              )}
            </div>
            <Button type="submit" disabled={passwordForm.formState.isSubmitting}>
              Cambiar contraseña
            </Button>
            {passwordMessage && <span className="ml-3 text-sm text-emerald-600">{passwordMessage}</span>}
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Passkeys</CardTitle>
          <CardDescription>Inicia sesión sin contraseña con WebAuthn.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {passkeys.length === 0 && <p className="text-sm text-muted-foreground">No tienes passkeys registradas.</p>}
          {passkeys.map((passkey) => (
            <div key={passkey.id} className="flex items-center justify-between rounded border p-3 text-sm">
              <div>
                <p className="font-medium">{passkey.name ?? "Passkey"}</p>
                <p className="text-xs text-muted-foreground">
                  {passkey.created_at ?? "—"}
                  {passkey.last_used_at ? ` · último uso ${passkey.last_used_at}` : ""}
                </p>
              </div>
              <Button size="sm" variant="ghost" onClick={() => void removePasskey(passkey.id)}>
                Eliminar
              </Button>
            </div>
          ))}

          {isWebAuthnAvailable() && (
            <Button variant="outline" onClick={() => void addPasskey()} disabled={passkeyBusy}>
              {passkeyBusy ? "Esperando..." : "Añadir passkey"}
            </Button>
          )}
        </CardContent>
      </Card>

      <Card className="border-destructive/40">
        <CardHeader>
          <CardTitle className="text-base text-destructive">Deshabilitar cuenta</CardTitle>
          <CardDescription>Se revocan tus tokens y no podrás iniciar sesión hasta reactivarla.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="space-y-2">
            <Label htmlFor="disable_password">Contraseña actual</Label>
            <Input
              id="disable_password"
              type="password"
              value={disablePassword}
              onChange={(event) => setDisablePassword(event.target.value)}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="disable_reason">Motivo (opcional)</Label>
            <Input id="disable_reason" value={disableReason} onChange={(event) => setDisableReason(event.target.value)} />
          </div>
          <Button variant="destructive" onClick={() => void disableAccount()} disabled={!disablePassword}>
            Deshabilitar cuenta
          </Button>
        </CardContent>
        <CardFooter>
          <Button variant="ghost" onClick={() => void logout()}>
            Cerrar sesión
          </Button>
        </CardFooter>
      </Card>
    </div>
  )
}
