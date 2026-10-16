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

import { getPlatform, getToken, setPlatform as persistPlatform, setToken } from "@/lib/api"
import { auth } from "@/lib/endpoints"
import type { Platform, User } from "@/types"

interface AuthContextValue {
  user: User | null
  platform: Platform
  isAuthenticated: boolean
  isLoading: boolean
  login: (email: string, password: string, platform: Platform) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

/**
 * Real auth against the platform API: exchanges credentials for a Passport
 * personal access token, keeps it in localStorage, and resolves the identity
 * through `auth/me`. The platform (web/admin/employee) is part of the
 * credential exchange because a token is only valid for the guard that minted
 * it.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [platform, setPlatformState] = useState<Platform>(() => getPlatform())
  const [isLoading, setIsLoading] = useState(() => getToken() !== null)

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

  const login = useCallback(async (email: string, password: string, nextPlatform: Platform) => {
    persistPlatform(nextPlatform)
    setPlatformState(nextPlatform)

    const result = await auth.login(email, password)
    setToken(result.access_token)
    setUser(result.user)
  }, [])

  const logout = useCallback(async () => {
    try {
      await auth.logout()
    } catch {
      // A revoked or expired token must still be dropped locally.
    }
    setToken(null)
    setUser(null)
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({ user, platform, isAuthenticated: user !== null, isLoading, login, logout }),
    [user, platform, isLoading, login, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error("useAuth must be used within AuthProvider")
  return context
}
