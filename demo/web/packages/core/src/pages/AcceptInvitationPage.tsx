"use client"

import { useEffect, useState } from "react"
import { Link, useNavigate, useSearchParams } from "react-router-dom"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"

import {
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
import { account } from "../lib/endpoints"
import { errorMessage } from "../lib/errors"
import type { InvitationSummary } from "../types"

const schema = z
  .object({
    name: z.string().min(1, "El nombre es obligatorio"),
    password: z.string().min(8, "Mínimo 8 caracteres"),
    password_confirmation: z.string().min(8, "Mínimo 8 caracteres"),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ["password_confirmation"],
    message: "Las contraseñas no coinciden",
  })

type Form = z.infer<typeof schema>

export function AcceptInvitationPage() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const token = params.get("token") ?? ""
  const { acceptInvitation } = useAuth()
  const [invitation, setInvitation] = useState<InvitationSummary | null>(null)
  const [error, setError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Form>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (!token) return

    account
      .invitation(token)
      .then(setInvitation)
      .catch((err: unknown) => setError(errorMessage(err, "La invitación no es válida o caducó.")))
  }, [token])

  const onSubmit = async (data: Form) => {
    try {
      setError(null)
      await acceptInvitation({ token, ...data })
      navigate("/dashboard")
    } catch (err) {
      setError(errorMessage(err, "No se pudo aceptar la invitación"))
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4 py-12">
      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <CardTitle className="text-2xl">Aceptar invitación</CardTitle>
          <CardDescription>
            {invitation ? `Únete como ${invitation.email}` : "Completa tus datos para activar la cuenta."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {(error ?? (!token ? "Falta el token de la invitación." : null)) && (
            <div className="mb-4 text-center text-sm text-destructive" role="alert">
              {error ?? "Falta el token de la invitación."}
            </div>
          )}

          {invitation && (
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Nombre</Label>
                <Input id="name" {...register("name")} disabled={isSubmitting} />
                {errors.name && <p className="text-sm text-destructive">{errors.name.message}</p>}
              </div>

              <div className="space-y-2">
                <Label>Email</Label>
                <Input value={invitation.email} readOnly disabled className="bg-muted" />
              </div>

              <div className="space-y-2">
                <Label htmlFor="password">Contraseña</Label>
                <Input id="password" type="password" {...register("password")} disabled={isSubmitting} />
                {errors.password && <p className="text-sm text-destructive">{errors.password.message}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="password_confirmation">Repetir contraseña</Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  {...register("password_confirmation")}
                  disabled={isSubmitting}
                />
                {errors.password_confirmation && (
                  <p className="text-sm text-destructive">{errors.password_confirmation.message}</p>
                )}
              </div>

              <Button type="submit" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? "Activando..." : "Crear cuenta y entrar"}
              </Button>
            </form>
          )}
        </CardContent>
        <CardFooter className="flex flex-col space-y-3">
          <Separator />
          <p className="text-center text-xs text-muted-foreground">
            <Link to="/login" className="underline underline-offset-4">
              Volver al login
            </Link>
          </p>
        </CardFooter>
      </Card>
    </div>
  )
}
