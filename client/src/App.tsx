import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { Toaster } from "@/components/ui/sonner"
import { Layout } from "@/components/layout"
import { AuthProvider, useAuth } from "@/providers/AuthProvider"
import { LoginPage } from "@/pages/LoginPage"
import { DashboardPage } from "@/pages/DashboardPage"
import { ProductsPage } from "@/pages/ProductsPage"
import { ConfigurationPage } from "@/pages/ConfigurationPage"
import { TwoFactorPage } from "@/pages/TwoFactorPage"
import { WidgetsPage } from "@/pages/WidgetsPage"
import { GraphQLPage } from "@/pages/GraphQLPage"
import { ProfilePage } from "@/pages/ProfilePage"

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

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route element={<RequireAuth />}>
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="dashboard" element={<DashboardPage />} />
              <Route path="products" element={<ProductsPage />} />
              <Route path="configuration" element={<ConfigurationPage />} />
              <Route path="2fa" element={<TwoFactorPage />} />
              <Route path="widgets" element={<WidgetsPage />} />
              <Route path="graphql" element={<GraphQLPage />} />
              <Route path="profile" element={<ProfilePage />} />
            </Route>
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Routes>
          <Toaster position="top-right" />
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  )
}

export default App
