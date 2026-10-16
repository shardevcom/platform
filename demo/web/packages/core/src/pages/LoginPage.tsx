"use client"

import { useState } from "react"
import { useNavigate } from "react-router-dom"
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
} from "@shardev/ui"
import type { Guard } from "@shardev/plugin-kit"

import { useAuth } from "../providers/AuthProvider"

const loginSchema = z.object({
  email: z.string().email("Email inválido"),
  password: z.string().min(1, "La contraseña es obligatoria"),
})

type LoginForm = z.infer<typeof loginSchema>

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

export function LoginPage({ appName, guard }: { appName: string; guard: Guard }) {
  const navigate = useNavigate()
  const { login } = useAuth()
  const [error, setError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: DEMO_EMAIL[guard], password: "password" },
  })

  const onSubmit = async (data: LoginForm) => {
    try {
      setError(null)
      await login(data.email, data.password)
      navigate("/dashboard")
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al iniciar sesión")
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
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            {error && (
              <div className="text-center text-sm text-destructive" role="alert">
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
          <p className="text-center text-xs text-muted-foreground">
            Credenciales demo: <span className="font-mono">{DEMO_EMAIL[guard]}</span> /{" "}
            <span className="font-mono">password</span>
          </p>
        </CardFooter>
      </Card>
    </div>
  )
}
