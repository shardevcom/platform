"use client"

import { useEffect, useState } from "react"
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

import { isLoginResponse, type SocialProvider, type TwoFactorSetup } from "../types"

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
  const { login, verifyTwoFactor, startTwoFactorSetup, confirmTwoFactorSetup, loginWithOtp, loginWithPasskey } = useAuth()
  const [error, setError] = useState<string | null>(null)
  const [twoFactor, setTwoFactor] = useState<{
    token: string
    enrollment: boolean
    setup?: TwoFactorSetup
  } | null>(null)
  const [twoFactorCode, setTwoFactorCode] = useState("")
  const [useRecoveryCode, setUseRecoveryCode] = useState(false)
  const [twoFactorBusy, setTwoFactorBusy] = useState(false)
  const [remember, setRemember] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)
  const [sendingCode, setSendingCode] = useState(false)
  const [passkeyBusy, setPasskeyBusy] = useState(false)
  const [socialBusy, setSocialBusy] = useState<string | null>(null)
  const [providers, setProviders] = useState<SocialProvider[]>([])

  useEffect(() => {
    let active = true

    if (guard !== "web") {
      return
    }

    auth
      .socialProviders()
      .then((result) => {
        if (active) setProviders(result.providers)
      })
      .catch(() => {
        // Social login is optional: a disabled provider must not block login.
      })

    return () => {
      active = false
    }
  }, [guard])

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
      const result = await login(data.email, data.password, remember)

      if (isLoginResponse(result)) {
        navigate("/dashboard")
        return
      }

      if (result.two_factor_enrollment_required && result.enrollment_token) {
        setTwoFactorBusy(true)
        const setup = await startTwoFactorSetup(result.enrollment_token)
        setTwoFactor({ token: result.enrollment_token, enrollment: true, setup })
      } else if (result.two_factor_required && result.challenge_token) {
        setTwoFactor({ token: result.challenge_token, enrollment: false })
      } else {
        setError("La respuesta de autenticación no incluyó un segundo factor válido.")
      }
    } catch (err) {
      setError(errorMessage(err, "Error al iniciar sesión"))
    } finally {
      setTwoFactorBusy(false)
    }
  }

  const onTwoFactor = async (event: React.FormEvent) => {
    event.preventDefault()

    if (!twoFactor) return

    try {
      setError(null)
      setTwoFactorBusy(true)

      if (twoFactor.enrollment) {
        await confirmTwoFactorSetup(twoFactor.token, twoFactorCode)
      } else {
        await verifyTwoFactor(
          twoFactor.token,
          twoFactorCode,
          useRecoveryCode ? "recovery_code" : "totp",
        )
      }

      navigate("/dashboard")
    } catch (err) {
      setError(errorMessage(err, "Código de verificación inválido"))
    } finally {
      setTwoFactorBusy(false)
    }
  }

  const resetTwoFactor = () => {
    setTwoFactor(null)
    setTwoFactorCode("")
    setUseRecoveryCode(false)
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

  const onSocial = async (provider: string) => {
    try {
      setError(null)
      setSocialBusy(provider)
      const { redirect_url } = await auth.socialRedirect(provider)
      window.location.assign(redirect_url)
    } catch (err) {
      setError(errorMessage(err, "No se pudo iniciar el acceso social"))
      setSocialBusy(null)
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

          {twoFactor ? (
            <form onSubmit={onTwoFactor} className="space-y-4 pt-2">
              <div className="space-y-1 text-center">
                <h3 className="text-lg font-semibold">
                  {twoFactor.enrollment ? "Configura tu segundo factor" : "Verificación en dos pasos"}
                </h3>
                <p className="text-sm text-muted-foreground">
                  {twoFactor.enrollment
                    ? "Escanea el QR con tu app de autenticación y guarda los recovery codes."
                    : "Introduce el código de tu app de autenticación."}
                </p>
              </div>

              {twoFactor.setup && (
                <div className="space-y-3">
                  <div
                    className="mx-auto w-44 rounded-md border bg-white p-2"
                    dangerouslySetInnerHTML={{ __html: twoFactor.setup.qr_code }}
                  />
                  <p className="break-all text-center font-mono text-xs text-muted-foreground">
                    {twoFactor.setup.secret}
                  </p>
                  <div>
                    <p className="mb-1 text-sm font-medium">Recovery codes</p>
                    <div className="grid grid-cols-2 gap-1 font-mono text-xs">
                      {twoFactor.setup.recovery_codes.map((code) => (
                        <span key={code} className="rounded bg-muted px-2 py-1">{code}</span>
                      ))}
                    </div>
                  </div>
                </div>
              )}

              <div className="space-y-2">
                <Label htmlFor="two-factor-code">
                  {useRecoveryCode ? "Recovery code" : "Código TOTP"}
                </Label>
                <Input
                  id="two-factor-code"
                  value={twoFactorCode}
                  onChange={(event) => setTwoFactorCode(event.target.value.trim())}
                  inputMode={useRecoveryCode ? "text" : "numeric"}
                  autoComplete="one-time-code"
                  maxLength={useRecoveryCode ? undefined : 6}
                  autoFocus
                />
              </div>

              <Button type="submit" className="w-full" disabled={twoFactorBusy || twoFactorCode.length === 0}>
                {twoFactorBusy ? "Verificando..." : twoFactor.enrollment ? "Activar y entrar" : "Verificar y entrar"}
              </Button>

              {!twoFactor.enrollment && (
                <Button
                  type="button"
                  variant="ghost"
                  className="w-full"
                  onClick={() => {
                    setUseRecoveryCode((value) => !value)
                    setTwoFactorCode("")
                  }}
                >
                  {useRecoveryCode ? "Usar código TOTP" : "Usar un recovery code"}
                </Button>
              )}

              <Button type="button" variant="ghost" className="w-full" onClick={resetTwoFactor}>
                Cancelar
              </Button>
            </form>
          ) : (
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

                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                  <input
                    type="checkbox"
                    checked={remember}
                    onChange={(event) => setRemember(event.target.checked)}
                    disabled={passwordForm.formState.isSubmitting}
                  />
                  Recordarme (el segundo factor sigue siendo obligatorio)
                </label>

                <Button type="submit" className="w-full" disabled={passwordForm.formState.isSubmitting || twoFactorBusy}>
                  {passwordForm.formState.isSubmitting || twoFactorBusy ? "Iniciando..." : "Iniciar sesión"}
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
          )}

          {providers.length > 0 && (
            <>
              <Separator className="my-4" />
              <div className="space-y-2">
                {providers.map((provider) => (
                  <Button
                    key={provider.key}
                    type="button"
                    variant="outline"
                    className="w-full"
                    onClick={() => void onSocial(provider.key)}
                    disabled={socialBusy !== null}
                  >
                    {socialBusy === provider.key ? "Redirigiendo…" : `Continuar con ${provider.name}`}
                  </Button>
                ))}
              </div>
            </>
          )}

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
