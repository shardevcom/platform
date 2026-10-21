import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"

import type { PluginDefinition } from "@shardev/plugin-kit"

import { PlatformApp } from "./PlatformApp"
import { auth, widgets } from "./lib/endpoints"
import type { User } from "./types"

vi.mock("./lib/endpoints", async (importOriginal) => {
  const actual = await importOriginal<typeof import("./lib/endpoints")>()

  return {
    ...actual,
    auth: { ...actual.auth, me: vi.fn() },
    widgets: { ...actual.widgets, get: vi.fn() },
  }
})

const me = vi.mocked(auth.me)
const getWidgets = vi.mocked(widgets.get)

const user: User = {
  id: "1",
  name: "Ada",
  email: "admin@example.com",
  guard: "admin",
  email_verified: true,
  abilities: ["products.*"],
}

const productsPlugin: PluginDefinition = {
  name: "product",
  title: "Products",
  resource: "products",
  guards: {
    admin: {
      path: "products",
      component: () => <div>Productos</div>,
      nav: { label: "Products", to: "/products" },
    },
  },
}

beforeEach(() => {
  window.history.pushState({}, "", "/")
  localStorage.clear()
  me.mockReset()
  getWidgets.mockReset().mockResolvedValue({})
})

afterEach(() => cleanup())

describe("PlatformApp", () => {
  it("shows the login screen when there is no token", async () => {
    render(<PlatformApp guard="admin" appName="Shardev Admin" />)

    expect(await screen.findByText("Shardev Admin")).toBeInTheDocument()
    expect(me).not.toHaveBeenCalled()
  })

  it("renders the shell and the guard's plugin nav once authenticated", async () => {
    localStorage.setItem("shardev.access_token", "token")
    me.mockResolvedValue(user)

    render(<PlatformApp guard="admin" appName="Shardev Admin" plugins={[productsPlugin]} />)

    expect(await screen.findByText(/Hola, Ada/)).toBeInTheDocument()
    expect(screen.getByText("Products")).toBeInTheDocument()
  })

  it("does not mount a plugin declared for another guard", async () => {
    localStorage.setItem("shardev.access_token", "token")
    me.mockResolvedValue({ ...user, guard: "web" })

    render(<PlatformApp guard="web" appName="Shardev Web" plugins={[productsPlugin]} />)

    await screen.findByText(/Hola, Ada/)
    expect(screen.queryByText("Products")).not.toBeInTheDocument()
  })
})
