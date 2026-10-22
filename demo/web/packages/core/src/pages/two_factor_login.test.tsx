import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { MemoryRouter, Route, Routes } from "react-router-dom"

import type { LoginResponse, TwoFactorSetup } from "../types"

vi.mock("../lib/endpoints", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../lib/endpoints")>()

  return {
    ...actual,
    auth: {
      ...actual.auth,
      login: vi.fn(),
      twoFactorChallenge: vi.fn(),
      twoFactorSetup: vi.fn(),
      twoFactorSetupConfirm: vi.fn(),
      socialProviders: vi.fn().mockResolvedValue({ providers: [] }),
    },
  }
})

import { AuthProvider } from "../providers/AuthProvider"
import { auth } from "../lib/endpoints"
import { LoginPage } from "./LoginPage"

const login = vi.mocked(auth.login)
const challenge = vi.mocked(auth.twoFactorChallenge)
const setup = vi.mocked(auth.twoFactorSetup)
const confirm = vi.mocked(auth.twoFactorSetupConfirm)

const session: LoginResponse = {
  token_type: "Bearer",
  access_token: "mfa-token",
  expires_in: 3600,
  abilities: ["*"],
  user: {
    id: "1",
    name: "Ada",
    email: "admin@example.com",
    guard: "admin",
    email_verified: true,
    abilities: ["*"],
  },
}

const enrollment: TwoFactorSetup = {
  secret: "JBSWY3DPEHPK3PXP",
  qr_code: "<svg role=\"img\" aria-label=\"qr\"></svg>",
  recovery_codes: ["CODE-ONE"],
}

beforeEach(() => {
  localStorage.clear()
  login.mockReset()
  challenge.mockReset()
  setup.mockReset()
  confirm.mockReset()
})

afterEach(() => cleanup())

function renderLogin() {
  render(
    <MemoryRouter initialEntries={["/login"]}>
      <AuthProvider guard="admin">
        <Routes>
          <Route path="/login" element={<LoginPage appName="Shardev Admin" guard="admin" />} />
          <Route path="/dashboard" element={<div>Panel</div>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe("two-factor login", () => {
  it("enrolls a privileged account before issuing a token", async () => {
    login.mockResolvedValue({
      two_factor_enrollment_required: true,
      enrollment_token: "enroll-token",
      methods: ["totp"],
      expires_in: 600,
    })
    setup.mockResolvedValue(enrollment)
    confirm.mockResolvedValue(session)

    renderLogin()
    fireEvent.click(screen.getByRole("button", { name: "Iniciar sesión" }))

    expect(await screen.findByText("Configura tu segundo factor")).toBeInTheDocument()
    expect(screen.getByText("JBSWY3DPEHPK3PXP")).toBeInTheDocument()
    expect(setup).toHaveBeenCalledWith("enroll-token")

    fireEvent.change(screen.getByLabelText("Código TOTP"), { target: { value: "123456" } })
    fireEvent.click(screen.getByRole("button", { name: "Activar y entrar" }))

    expect(await screen.findByText("Panel")).toBeInTheDocument()
    expect(confirm).toHaveBeenCalledWith("enroll-token", "123456")
    expect(localStorage.getItem("shardev.access_token")).toBe("mfa-token")
  })

  it("accepts a recovery code for a confirmed challenge", async () => {
    login.mockResolvedValue({
      two_factor_required: true,
      challenge_token: "challenge-token",
      methods: ["totp", "recovery_code"],
      expires_in: 600,
    })
    challenge.mockResolvedValue(session)

    renderLogin()
    fireEvent.click(screen.getByRole("button", { name: "Iniciar sesión" }))

    expect(await screen.findByText("Verificación en dos pasos")).toBeInTheDocument()
    fireEvent.click(screen.getByRole("button", { name: "Usar un recovery code" }))
    fireEvent.change(screen.getByLabelText("Recovery code"), { target: { value: "CODE-ONE" } })
    fireEvent.click(screen.getByRole("button", { name: "Verificar y entrar" }))

    expect(await screen.findByText("Panel")).toBeInTheDocument()
    await waitFor(() => expect(challenge).toHaveBeenCalledWith("challenge-token", "CODE-ONE", "recovery_code"))
  })
})
