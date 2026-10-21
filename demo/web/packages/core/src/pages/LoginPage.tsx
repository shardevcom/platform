"use client"

import { useState } from "react"
import { Link, useNavigate } from "react-router-dom"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"

import {
  Button,
  Input,
  Label,
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
  Separator,
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@shardev/ui"
import type { Guard } from "@shardev/plugin-kit"

import { useAuth } from "../providers/AuthProvider"
import { auth } from "../lib/endpoints"
import { errorMessage } from "../lib/errors"
import { getPasskey, isWebAuthnAvailable } from "../lib/webauthn"

const loginSchema = z.object({
  email: z.string().email("Email inválido"),
  password: z.string().min(1, "La contraseña es obligatoria"),
})

const otpSchema = z.object({
  email: z.string().email("Email inválido"),
  code: z.string().min(4, "Introduce el código"),
})

type LoginForm = z.infer<typeof loginSchema>
type OtpForm = z.infer<typeof otpSchema>

const DEMO_EMAIL: Record<Guard, string> = {
  admin: "admin@example.com",
  employee: "employee@example.com",
  web: "user@example.com",
}

const GUARD_LABEL: Record<Guard, string> = {
  admin: "Admin",
  employee: "Employee",
  web: "Web",
}

export function LoginPage({
  appName,
  guard,
  public: isPublic = false,
}: {
  appName: string
  guard: Guard
  public?: boolean
}) {
  const navigate = useNavigate()
  const { login, loginWithOtp, loginWithPasskey } = useAuth()
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [sendingCode, setSendingCode] = useState(false)
  const [passkeyBusy, setPasskeyBusy] = useState(false)

  const passwordForm = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: DEMO_EMAIL[guard], password: "password" },
  })

  const otpForm = useForm<OtpForm>({
    resolver: zodResolver(otpSchema),
    defaultValues: { email: DEMO_EMAIL[guard], code: "" },
  })

  const onPassword = async (data: LoginForm) => {
    try {
      setError(null)
      await login(data.email, data.password)
      navigate("/dashboard")
    } catch (err) {
      setError(errorMessage(err, "Error al iniciar sesión"))
    }
  }

  const onSendCode = async () => {
    const email = otpForm.getValues("email")

    if (!z.string().email().safeParse(email).success) {
      otpForm.setError("email", { message: "Email inválido" })
      return
    }

    try {
      setError(null)
      setSendingCode(true)
      await auth.otpRequest(email)
      setNotice("Si la cuenta existe, te enviamos un código de acceso.")
    } catch (err) {
      setError(errorMessage(err, "No se pudo enviar el código"))
    } finally {
      setSendingCode(false)
    }
  }

  const onOtp = async (data: OtpForm) => {
    try {
      setError(null)
      await loginWithOtp(data.email, data.code)
      navigate("/dashboard")
    } catch (err) {
      setError(errorMessage(err, "Código inválido o caducado"))
    }
  }

  const onPasskey = async () => {
    try {
      setError(null)
      setPasskeyBusy(true)
      const email = passwordForm.getValues("email")
      const options = await auth.webauthnLoginOptions(email || undefined)
      const credential = await getPasskey(options.publicKey)
      await loginWithPasskey(credential)
      navigate("/dashboard")
    } catch (err) {
      setError(errorMessage(err, "No se pudo iniciar sesión con passkey"))
    } finally {
      setPasskeyBusy(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4 py-12">
      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <CardTitle className="text-2xl">{appName}</CardTitle>
          <CardDescription>Consume la API REST y GraphQL — plataforma {GUARD_LABEL[guard]}</CardDescription>
        </CardHeader>
        <CardContent>
          {error && (
            <div className="mb-4 text-center text-sm text-destructive" role="alert">
              {error}
            </div>
          )}
          {notice && (
            <div className="mb-4 text-center text-sm text-muted-foreground" role="status">
              {notice}
            </div>
          )}

          <Tabs defaultValue="password">
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="password">Contraseña</TabsTrigger>
              <TabsTrigger value="otp">Código por email</TabsTrigger>
            </TabsList>

            <TabsContent value="password">
              <form onSubmit={passwordForm.handleSubmit(onPassword)} className="space-y-4 pt-4">
                <div className="space-y-2">
                  <Label htmlFor="email">Email</Label>
                  <Input id="email" type="email" {...passwordForm.register("email")} disabled={passwordForm.formState.isSubmitting} />
                  {passwordForm.formState.errors.email && (
                    <p className="text-sm text-destructive">{passwordForm.formState.errors.email.message}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <Label htmlFor="password">Contraseña</Label>
                    <Link to="/forgot-password" className="text-xs text-muted-foreground underline-offset-4 hover:underline">
                      ¿La olvidaste?
                    </Link>
                  </div>
                  <Input
                    id="password"
                    type="password"
                    {...passwordForm.register("password")}
                    disabled={passwordForm.formState.isSubmitting}
                  />
                  {passwordForm.formState.errors.password && (
                    <p className="text-sm text-destructive">{passwordForm.formState.errors.password.message}</p>
                  )}
                </div>

                <Button type="submit" className="w-full" disabled={passwordForm.formState.isSubmitting}>
                  {passwordForm.formState.isSubmitting ? "Iniciando..." : "Iniciar sesión"}
                </Button>
              </form>
            </TabsContent>

            <TabsContent value="otp">
              <form onSubmit={otpForm.handleSubmit(onOtp)} className="space-y-4 pt-4">
                <div className="space-y-2">
                  <Label htmlFor="otp-email">Email</Label>
                  <Input id="otp-email" type="email" {...otpForm.register("email")} />
                  {otpForm.formState.errors.email && (
                    <p className="text-sm text-destructive">{otpForm.formState.errors.email.message}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="otp-code">Código</Label>
                  <div className="flex gap-2">
                    <Input id="otp-code" inputMode="numeric" autoComplete="one-time-code" {...otpForm.register("code")} />
                    <Button type="button" variant="outline" onClick={() => void onSendCode()} disabled={sendingCode}>
                      {sendingCode ? "Enviando..." : "Enviar"}
                    </Button>
                  </div>
                  {otpForm.formState.errors.code && (
                    <p className="text-sm text-destructive">{otpForm.formState.errors.code.message}</p>
                  )}
                </div>

                <Button type="submit" className="w-full" disabled={otpForm.formState.isSubmitting}>
                  {otpForm.formState.isSubmitting ? "Verificando..." : "Entrar con código"}
                </Button>
              </form>
            </TabsContent>
          </Tabs>

          {isWebAuthnAvailable() && (
            <>
              <Separator className="my-4" />
              <Button type="button" variant="outline" className="w-full" onClick={() => void onPasskey()} disabled={passkeyBusy}>
                {passkeyBusy ? "Esperando passkey..." : "Entrar con passkey"}
              </Button>
            </>
          )}
        </CardContent>
        <CardFooter className="flex flex-col space-y-3">
          <Separator />
          {isPublic ? (
            <p className="text-center text-xs text-muted-foreground">
              ¿No tienes cuenta?{" "}
              <Link to="/register" className="underline underline-offset-4">
                Crear una
              </Link>
            </p>
          ) : (
            <p className="text-center text-xs text-muted-foreground">
              El acceso es por invitación. Pide una a un miembro del equipo.
            </p>
          )}
          <p className="text-center text-xs text-muted-foreground">
            Credenciales demo: <span className="font-mono">{DEMO_EMAIL[guard]}</span> /{" "}
            <span className="font-mono">password</span>
          </p>
        </CardFooter>
      </Card>
    </div>
  )
}
