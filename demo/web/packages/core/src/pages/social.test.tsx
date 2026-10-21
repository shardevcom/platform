import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"
import { MemoryRouter, Route, Routes } from "react-router-dom"

import type { User } from "../types"

vi.mock("../lib/endpoints", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../lib/endpoints")>()

  return {
    ...actual,
    auth: {
      ...actual.auth,
      me: vi.fn(),
      socialProviders: vi.fn(),
      socialRedirect: vi.fn(),
    },
  }
})

import { AuthProvider } from "../providers/AuthProvider"
import { auth } from "../lib/endpoints"
import { LoginPage } from "./LoginPage"
import { SocialCallbackPage } from "./SocialCallbackPage"

const me = vi.mocked(auth.me)
const socialProviders = vi.mocked(auth.socialProviders)

const user: User = {
  id: "1",
  name: "Ada",
  email: "ada@example.com",
  guard: "web",
  email_verified: true,
  abilities: ["*"],
}

beforeEach(() => {
  localStorage.clear()
  window.history.replaceState({}, "", "/")
  me.mockReset().mockResolvedValue(user)
  socialProviders.mockReset().mockResolvedValue({
    providers: [{ key: "google", name: "Google" }],
  })
})

afterEach(() => cleanup())

describe("social login", () => {
  it("renders the enabled providers on the web login", async () => {
    render(
      <MemoryRouter>
        <AuthProvider guard="web">
          <LoginPage appName="Shardev Web" guard="web" public />
        </AuthProvider>
      </MemoryRouter>,
    )

    expect(await screen.findByRole("button", { name: "Continuar con Google" })).toBeInTheDocument()
  })

  it("does not ask for providers on a private guard", async () => {
    render(
      <MemoryRouter>
        <AuthProvider guard="admin">
          <LoginPage appName="Shardev Admin" guard="admin" />
        </AuthProvider>
      </MemoryRouter>,
    )

    await screen.findByText("Shardev Admin")
    expect(socialProviders).not.toHaveBeenCalled()
  })

  it("stores the token from the callback fragment and enters the shell", async () => {
    window.history.replaceState({}, "", "/auth/callback#token=social-token")

    render(
      <MemoryRouter initialEntries={["/auth/callback"]}>
        <AuthProvider guard="web">
          <Routes>
            <Route path="/auth/callback" element={<SocialCallbackPage />} />
            <Route path="/dashboard" element={<div>Panel</div>} />
          </Routes>
        </AuthProvider>
      </MemoryRouter>,
    )

    expect(await screen.findByText("Panel")).toBeInTheDocument()
    expect(localStorage.getItem("shardev.access_token")).toBe("social-token")
  })

  it("explains the failure when the callback has no token", async () => {
    window.history.replaceState({}, "", "/auth/callback")

    render(
      <MemoryRouter initialEntries={["/auth/callback"]}>
        <AuthProvider guard="web">
          <SocialCallbackPage />
        </AuthProvider>
      </MemoryRouter>,
    )

    expect(await screen.findByText(/no devolvió un token/i)).toBeInTheDocument()
  })
})
