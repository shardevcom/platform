"use client"

import { useState } from "react"
import { useNavigate } from "react-router-dom"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import { useAuth } from "@/providers/AuthProvider"
import type { Platform } from "@/types"

const loginSchema = z.object({
  email: z.string().email("Email inválido"),
  password: z.string().min(1, "La contraseña es obligatoria"),
})

type LoginForm = z.infer<typeof loginSchema>

const PLATFORMS: Array<{ value: Platform; label: string; hint: string }> = [
  { value: "admin", label: "Admin", hint: "admin@example.com" },
  { value: "employee", label: "Employee", hint: "employee@example.com" },
  { value: "web", label: "Web", hint: "user@example.com" },
]

export function LoginPage() {
  const navigate = useNavigate()
  const { login } = useAuth()
  const [platform, setPlatform] = useState<Platform>("admin")
  const [error, setError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "admin@example.com", password: "password" },
  })

  const onSubmit = async (data: LoginForm) => {
    try {
      setError(null)
      await login(data.email, data.password, platform)
      navigate("/dashboard")
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al iniciar sesión")
    }
  }

  const selectPlatform = (value: Platform) => {
    setPlatform(value)
    const preset = PLATFORMS.find((p) => p.value === value)
    if (preset) setValue("email", preset.hint)
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-muted/50 px-4 py-12">
      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <CardTitle className="text-2xl">Shardev Platform</CardTitle>
          <CardDescription>Demo cliente — consume la API REST y GraphQL</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="mb-6 grid grid-cols-3 gap-2">
            {PLATFORMS.map((option) => (
              <button
                key={option.value}
                type="button"
                onClick={() => selectPlatform(option.value)}
                className={
                  "rounded-md border px-3 py-2 text-sm font-medium transition-colors " +
                  (platform === option.value
                    ? "border-primary bg-primary/10 text-primary"
                    : "text-muted-foreground hover:bg-accent")
                }
              >
                {option.label}
              </button>
            ))}
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            {error && (
              <div className="text-sm text-destructive text-center" role="alert">
                {error}
              </div>
            )}

            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <Input id="email" type="email" {...register("email")} disabled={isSubmitting} />
              {errors.email && <p className="text-sm text-destructive">{errors.email.message}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="password">Contraseña</Label>
              <Input id="password" type="password" {...register("password")} disabled={isSubmitting} />
              {errors.password && <p className="text-sm text-destructive">{errors.password.message}</p>}
            </div>

            <Button type="submit" className="w-full" disabled={isSubmitting}>
              {isSubmitting ? "Iniciando..." : "Iniciar sesión"}
            </Button>
          </form>
        </CardContent>
        <CardFooter className="flex flex-col space-y-3">
          <Separator />
          <p className="text-xs text-muted-foreground text-center">
            Credenciales demo: <span className="font-mono">{PLATFORMS.find((p) => p.value === platform)?.hint}</span> /{" "}
            <span className="font-mono">password</span>
          </p>
        </CardFooter>
      </Card>
    </div>
  )
}
