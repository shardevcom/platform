import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, render, screen, waitFor } from "@testing-library/react"

import type { CredentialPayload } from "../lib/endpoints"

vi.mock("../lib/endpoints", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../lib/endpoints")>()

  return {
    ...actual,
    auth: {
      ...actual.auth,
      webauthnLoginOptions: vi.fn(),
      webauthnLogin: vi.fn(),
      webauthnRegisterOptions: vi.fn(),
      webauthnRegister: vi.fn(),
    },
  }
})

vi.mock("../lib/webauthn", () => ({
  getPasskey: vi.fn(),
  createPasskey: vi.fn(),
}))

import { auth } from "../lib/endpoints"
import { createPasskey, getPasskey } from "../lib/webauthn"
import { buildPasskeyReturnUrl, passkeyReturnTarget } from "../lib/passkey-return"
import { PasskeyBridgePage } from "./PasskeyBridgePage"

const loginOptions = vi.mocked(auth.webauthnLoginOptions)
const login = vi.mocked(auth.webauthnLogin)
const getPasskeyMock = vi.mocked(getPasskey)
const createPasskeyMock = vi.mocked(createPasskey)
const registerOptions = vi.mocked(auth.webauthnRegisterOptions)
const register = vi.mocked(auth.webauthnRegister)

const credential: CredentialPayload = {
  id: "credential",
  rawId: "credential",
  type: "public-key",
  response: {},
}

beforeEach(() => {
  localStorage.clear()
  window.history.replaceState(
    {},
    "",
    "/passkey?mode=login&email=ada%40example.com&state=abc&return=http%3A%2F%2F127.0.0.1%3A9999%2Fcallback",
  )
  loginOptions.mockReset().mockResolvedValue({
    publicKey: { challenge: "challenge", rpId: "example.com", allowCredentials: [] },
  })
  login.mockReset().mockResolvedValue({
    access_token: "desktop-token",
    token_type: "Bearer",
    expires_in: 3600,
    abilities: [],
    user: {
      id: "1",
      name: "Ada",
      email: "ada@example.com",
      guard: "web",
      email_verified: true,
      abilities: [],
    },
  })
  getPasskeyMock.mockReset().mockResolvedValue(credential)
  createPasskeyMock.mockReset().mockResolvedValue(credential)
  registerOptions.mockReset().mockResolvedValue({
    publicKey: { challenge: "challenge", rp: { id: "example.com", name: "Demo" } },
  } as never)
  register.mockReset().mockResolvedValue({
    id: 1,
    name: "Desktop",
    credential_id: "credential",
    created_at: "2026-01-01",
    last_used_at: null,
  })
})

afterEach(() => cleanup())

describe("passkey return URL", () => {
  it("accepts loopback HTTP callbacks only", () => {
    expect(passkeyReturnTarget("http://127.0.0.1:9999/callback")?.port).toBe("9999")
    expect(passkeyReturnTarget("http://localhost:9999/callback")).not.toBeNull()
    expect(passkeyReturnTarget("https://127.0.0.1:9999/callback")).toBeNull()
    expect(passkeyReturnTarget("http://evil.example/callback")).toBeNull()
    expect(passkeyReturnTarget("not a url")).toBeNull()
  })

  it("keeps the state and adds the result", () => {
    const target = passkeyReturnTarget("http://127.0.0.1:9999/callback?foo=bar")
    const url = new URL(buildPasskeyReturnUrl(target!, "abc", { token: "tok" }))

    expect(url.searchParams.get("state")).toBe("abc")
    expect(url.searchParams.get("token")).toBe("tok")
    expect(url.searchParams.get("foo")).toBe("bar")
  })
})

describe("PasskeyBridgePage", () => {
  it("runs the login ceremony and returns the token to the desktop", async () => {
    const onFinish = vi.fn()

    render(<PasskeyBridgePage guard="web" onFinish={onFinish} />)

    screen.getByRole("button", { name: "Continuar con passkey" }).click()

    await waitFor(() => expect(onFinish).toHaveBeenCalledTimes(1))

    const url = new URL(onFinish.mock.calls[0]![0])

    expect(loginOptions).toHaveBeenCalledWith("ada@example.com")
    expect(login).toHaveBeenCalledWith(credential, "desktop")
    expect(url.searchParams.get("token")).toBe("desktop-token")
    expect(url.searchParams.get("state")).toBe("abc")
  })

  it("registers with the desktop token and returns the status", async () => {
    const onFinish = vi.fn()
    window.history.replaceState(
      {},
      "",
      "/passkey?mode=register&name=Desktop&state=abc&return=http%3A%2F%2F127.0.0.1%3A9999%2Fcallback#token=desktop-token",
    )

    render(<PasskeyBridgePage guard="web" onFinish={onFinish} />)

    screen.getByRole("button", { name: "Continuar con passkey" }).click()

    await waitFor(() => expect(onFinish).toHaveBeenCalledTimes(1))

    const url = new URL(onFinish.mock.calls[0]![0])

    expect(localStorage.getItem("shardev.token.web")).toBeNull()
    expect(createPasskeyMock).toHaveBeenCalled()
    expect(register).toHaveBeenCalledWith(credential, "Desktop")
    expect(url.searchParams.get("status")).toBe("registered")
  })

  it("reports an invalid return URL without running the ceremony", () => {
    window.history.replaceState({}, "", "/passkey?mode=login&state=abc&return=https%3A%2F%2Fevil.example")

    render(<PasskeyBridgePage guard="web" />)

    expect(screen.getByRole("alert")).toHaveTextContent(/no es válido/i)
    expect(screen.getByRole("button", { name: "Continuar con passkey" })).toBeDisabled()
  })
})
