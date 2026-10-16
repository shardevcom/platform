"use client"

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react"

import type { Guard } from "@shardev/plugin-kit"
import { getToken, setPlatform, setToken } from "../lib/api"
import { auth } from "../lib/endpoints"
import type { User } from "../types"

interface AuthContextValue {
  user: User | null
  guard: Guard
  isAuthenticated: boolean
  isLoading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  /** True when the token's abilities grant the permission (supports `resource.*` and `*`). */
  can: (permission: string) => boolean
}

const AuthContext = createContext<AuthContextValue | null>(null)

/**
 * Real auth against one platform guard. Each app is bound to a single guard
 * (web/admin/employee): the credential exchange targets that platform's
 * endpoint and the returned Passport token is only valid for that guard.
 */
export function AuthProvider({ guard, children }: { guard: Guard; children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(() => getToken() !== null)

  useEffect(() => {
    setPlatform(guard)
  }, [guard])

  useEffect(() => {
    let active = true

    if (!getToken()) {
      return
    }

    auth
      .me()
      .then((me) => {
        if (active) setUser(me)
      })
      .catch(() => {
        setToken(null)
        if (active) setUser(null)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  const login = useCallback(
    async (email: string, password: string) => {
      setPlatform(guard)
      const result = await auth.login(email, password)
      setToken(result.access_token)
      setUser(result.user)
    },
    [guard],
  )

  const logout = useCallback(async () => {
    try {
      await auth.logout()
    } catch {
      // A revoked or expired token must still be dropped locally.
    }
    setToken(null)
    setUser(null)
  }, [])

  const can = useCallback(
    (permission: string) => {
      const abilities = user?.abilities ?? []
      if (abilities.includes("*") || abilities.includes(permission)) return true
      const [resource] = permission.split(".")
      return abilities.includes(`${resource}.*`)
    },
    [user],
  )

  const value = useMemo<AuthContextValue>(
    () => ({ user, guard, isAuthenticated: user !== null, isLoading, login, logout, can }),
    [user, guard, isLoading, login, logout, can],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error("useAuth must be used within AuthProvider")
  return context
}
