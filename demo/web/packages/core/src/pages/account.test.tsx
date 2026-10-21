import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { MemoryRouter } from "react-router-dom"

import type { NotificationsPayload, Preferences } from "../types"

vi.mock("../lib/endpoints", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../lib/endpoints")>()

  return {
    ...actual,
    auth: {
      ...actual.auth,
      forgotPassword: vi.fn(),
      resetPassword: vi.fn(),
    },
    account: {
      ...actual.account,
      preferences: vi.fn(),
      savePreferences: vi.fn(),
      notifications: vi.fn(),
      readAllNotifications: vi.fn(),
    },
  }
})

import { ForgotPasswordPage } from "./ForgotPasswordPage"
import { ResetPasswordPage } from "./ResetPasswordPage"
import { PreferencesPage } from "./PreferencesPage"
import { NotificationsPage } from "./NotificationsPage"
import { account, auth } from "../lib/endpoints"

const forgotPassword = vi.mocked(auth.forgotPassword)
const resetPassword = vi.mocked(auth.resetPassword)
const getPreferences = vi.mocked(account.preferences)
const savePreferences = vi.mocked(account.savePreferences)
const getNotifications = vi.mocked(account.notifications)
const readAllNotifications = vi.mocked(account.readAllNotifications)

const preferences: Preferences = {
  theme: "system",
  language: "es",
  notifications: { email: true, push: false },
}

const notifications: NotificationsPayload = {
  unread_count: 1,
  items: [
    { id: "n1", type: "invitation", data: {}, read_at: null, created_at: "2026-10-20T10:00:00Z" },
  ],
}

beforeEach(() => {
  vi.clearAllMocks()
})

afterEach(() => cleanup())

describe("auth pages", () => {
  it("requests a password reset email", async () => {
    forgotPassword.mockResolvedValue({ sent: true })

    render(
      <MemoryRouter>
        <ForgotPasswordPage />
      </MemoryRouter>,
    )

    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "ada@example.com" } })
    fireEvent.click(screen.getByRole("button", { name: "Enviar enlace" }))

    await waitFor(() => expect(forgotPassword).toHaveBeenCalledWith("ada@example.com"))
    expect(await screen.findByText(/recibirás un enlace/i)).toBeInTheDocument()
  })

  it("submits the token from the reset link", async () => {
    resetPassword.mockResolvedValue(null)

    render(
      <MemoryRouter initialEntries={["/reset-password?token=tok&email=ada@example.com"]}>
        <ResetPasswordPage />
      </MemoryRouter>,
    )

    fireEvent.change(screen.getByLabelText("Contraseña", { exact: true }), { target: { value: "secret123" } })
    fireEvent.change(screen.getByLabelText("Repetir contraseña"), { target: { value: "secret123" } })
    fireEvent.click(screen.getByRole("button", { name: "Guardar contraseña" }))

    await waitFor(() =>
      expect(resetPassword).toHaveBeenCalledWith({
        token: "tok",
        email: "ada@example.com",
        password: "secret123",
        password_confirmation: "secret123",
      }),
    )
  })
})

describe("account pages", () => {
  it("loads and saves preferences", async () => {
    getPreferences.mockResolvedValue(preferences)
    savePreferences.mockImplementation(async (payload) => ({ ...preferences, ...payload }))

    render(<PreferencesPage />)

    const theme = await screen.findByLabelText("Tema")
    expect(theme).toHaveValue("system")

    fireEvent.change(theme, { target: { value: "dark" } })
    fireEvent.click(screen.getByRole("button", { name: "Guardar preferencias" }))

    await waitFor(() => expect(savePreferences).toHaveBeenCalledWith({ ...preferences, theme: "dark" }))
    expect(await screen.findByText("Guardado.")).toBeInTheDocument()
  })

  it("marks every notification as read", async () => {
    getNotifications.mockResolvedValue(notifications)
    readAllNotifications.mockResolvedValue({ unread_count: 0 })

    render(<NotificationsPage />)

    expect(await screen.findByText("invitation")).toBeInTheDocument()
    fireEvent.click(screen.getByRole("button", { name: "Marcar todas como leídas" }))

    await waitFor(() => expect(readAllNotifications).toHaveBeenCalled())
  })
})
