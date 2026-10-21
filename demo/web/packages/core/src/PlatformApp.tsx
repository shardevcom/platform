"use client"

import { useMemo } from "react"
import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { Toaster } from "@shardev/ui"
import { createRegistry, type Guard, type PluginDefinition } from "@shardev/plugin-kit"

import { AuthProvider, useAuth } from "./providers/AuthProvider"
import { NavProvider } from "./nav-context"
import { BASE_NAV } from "./nav"
import { Layout } from "./components/layout"
import { LoginPage } from "./pages/LoginPage"
import { RegisterPage } from "./pages/RegisterPage"
import { ForgotPasswordPage } from "./pages/ForgotPasswordPage"
import { ResetPasswordPage } from "./pages/ResetPasswordPage"
import { VerifyEmailPage } from "./pages/VerifyEmailPage"
import { SocialCallbackPage } from "./pages/SocialCallbackPage"
import { AcceptInvitationPage } from "./pages/AcceptInvitationPage"
import { PreferencesPage } from "./pages/PreferencesPage"
import { NotificationsPage } from "./pages/NotificationsPage"
import { DashboardPage } from "./pages/DashboardPage"
import { ProfilePage } from "./pages/ProfilePage"
import { ConfigurationPage } from "./pages/ConfigurationPage"
import { TwoFactorPage } from "./pages/TwoFactorPage"
import { WidgetsPage } from "./pages/WidgetsPage"
import { GraphQLPage } from "./pages/GraphQLPage"

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 30,
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

function RequireAuth() {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) {
    return <div className="flex h-screen items-center justify-center text-muted-foreground">Cargando…</div>
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return <Layout />
}

export interface PlatformAppProps {
  /** The guard this app is bound to. */
  guard: Guard
  /** Shown on the login screen and shell. */
  appName: string
  /** Plugins whose per-guard views should be mounted. */
  plugins?: PluginDefinition[]
  /**
   * Whether this guard allows self-registration. Public guards show the
   * register link; private ones only offer invited access.
   */
  public?: boolean
}

/**
 * Shell shared by every guard app: providers, auth, routes and menu. The app
 * only declares its guard and the plugins it wants; the plugin-kit decides
 * which views that guard actually gets.
 */
export function PlatformApp({ guard, appName, plugins = [], public: isPublic = guard === "web" }: PlatformAppProps) {
  const registry = useMemo(() => createRegistry(plugins, guard), [plugins, guard])

  const nav = useMemo(
    () => [...BASE_NAV, ...registry.nav].sort((a, b) => (a.order ?? 10) - (b.order ?? 10)),
    [registry.nav],
  )

  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider guard={guard}>
          <NavProvider items={nav}>
            <Routes>
              <Route path="/login" element={<LoginPage appName={appName} guard={guard} public={isPublic} />} />
              <Route
                path="/register"
                element={isPublic ? <RegisterPage appName={appName} /> : <Navigate to="/login" replace />}
              />
              <Route path="/forgot-password" element={<ForgotPasswordPage />} />
              <Route path="/reset-password" element={<ResetPasswordPage />} />
              <Route path="/verify-email" element={<VerifyEmailPage />} />
              <Route path="/auth/callback" element={<SocialCallbackPage />} />
              <Route path="/accept-invitation" element={<AcceptInvitationPage />} />
              <Route element={<RequireAuth />}>
                <Route index element={<Navigate to="/dashboard" replace />} />
                <Route path="dashboard" element={<DashboardPage />} />
                <Route path="configuration" element={<ConfigurationPage />} />
                <Route path="2fa" element={<TwoFactorPage />} />
                <Route path="widgets" element={<WidgetsPage />} />
                <Route path="graphql" element={<GraphQLPage />} />
                <Route path="profile" element={<ProfilePage />} />
                <Route path="preferences" element={<PreferencesPage />} />
                <Route path="notifications" element={<NotificationsPage />} />
                {registry.views.map((view) => (
                  <Route key={view.plugin} path={view.path} element={<view.component />} />
                ))}
              </Route>
              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
            <Toaster position="top-right" />
          </NavProvider>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
