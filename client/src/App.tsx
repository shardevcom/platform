import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom"

import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { Toaster } from "@/components/ui/sonner"
import { Layout } from "@/components/layout"
import { LoginPage } from "@/pages/LoginPage"
import { RegisterPage } from "@/pages/RegisterPage"
import { DashboardPage } from "@/pages/DashboardPage"
import { ProfilePage } from "@/pages/ProfilePage"
import { PasskeyPage } from "@/pages/PasskeyPage"
import { OTPPage } from "@/pages/OTPPage"
import { TwoFactorPage } from "@/pages/TwoFactorPage"
import { StepUpMFAPage } from "@/pages/StepUpMFAPage"
import { AuthProvider as CustomAuthProvider } from "@/providers/AuthProvider"

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5,
      retry: 1,
    },
  },
})

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <div>
            <BrowserRouter>
              <Routes>
                <Route path="/login" element={<LoginPage />} />
                <Route path="/register" element={<RegisterPage />} />
                <Route path="/otp" element={<OTPPage />} />
                <Route path="/2fa" element={<TwoFactorPage />} />
                <Route path="/step-up" element={<StepUpMFAPage />} />
                <Route path="/passkeys" element={<PasskeyPage />} />
                <Route
                  path="/"
                  element={
                    <CustomAuthProvider>
                      <Layout />
                    </CustomAuthProvider>
                  }
                >
                  <Route index element={<Navigate to="/dashboard" replace />} />
                  <Route path="dashboard" element={<DashboardPage />} />
                  <Route path="profile" element={<ProfilePage />} />
                  <Route path="passkeys" element={<PasskeyPage />} />
                  <Route path="2fa" element={<TwoFactorPage />} />
                  <Route path="otp" element={<OTPPage />} />
                </Route>
                <Route path="*" element={<Navigate to="/dashboard" replace />} />
              </Routes>
              <Toaster position="top-right" />
            </BrowserRouter>
          </div>
    </QueryClientProvider>
  )
}

export default App
