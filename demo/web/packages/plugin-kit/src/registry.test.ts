import { describe, expect, it } from "vitest"

import { definePlugin } from "./definePlugin"
import { createRegistry } from "./registry"
import type { GuardView, PluginDefinition } from "./types"

const View = () => null
const OtherView = () => null

function plugin(name: string, guards: PluginDefinition["guards"]): PluginDefinition {
  return definePlugin({ name, title: name.toUpperCase(), resource: name, guards })
}

describe("definePlugin", () => {
  it("returns the definition unchanged so editors keep type-checking it", () => {
    const definition = { name: "product", title: "Products", guards: {} }

    expect(definePlugin(definition)).toBe(definition)
  })
})

describe("createRegistry", () => {
  it("mounts only the active guard's view", () => {
    const registry = createRegistry(
      [
        plugin("product", {
          web: { path: "products", component: View },
          admin: { path: "products", component: OtherView },
        }),
      ],
      "web",
    )

    expect(registry.views).toHaveLength(1)
    expect(registry.views[0]?.plugin).toBe("product")
    expect(registry.views[0]?.component).toBe(View)
  })

  it("ignores plugins with no view for the guard", () => {
    const registry = createRegistry([plugin("product", { admin: { path: "products", component: View } })], "web")

    expect(registry.views).toEqual([])
    expect(registry.nav).toEqual([])
  })

  it("keeps a nav-less view reachable but out of the menu", () => {
    const registry = createRegistry([plugin("product", { web: { path: "products", component: View } })], "web")

    expect(registry.views).toHaveLength(1)
    expect(registry.nav).toEqual([])
  })

  it("sorts plugin nav by order, defaulting to 10", () => {
    const registry = createRegistry(
      [
        plugin("b", { web: { path: "b", component: View, nav: { label: "B", to: "/b" } } }),
        plugin("a", { web: { path: "a", component: View, nav: { label: "A", to: "/a", order: 5 } } }),
        plugin("c", { web: { path: "c", component: View, nav: { label: "C", to: "/c", order: 50 } } }),
      ],
      "web",
    )

    expect(registry.nav.map((item) => item.label)).toEqual(["A", "B", "C"])
  })

  it("preserves the order plugins were declared in for equal orders", () => {
    const registry = createRegistry(
      [
        plugin("first", { web: { path: "first", component: View, nav: { label: "First", to: "/first" } } }),
        plugin("second", { web: { path: "second", component: View, nav: { label: "Second", to: "/second" } } }),
      ],
      "web",
    )

    expect(registry.nav.map((item) => item.label)).toEqual(["First", "Second"])
  })

  it("resolves every guard independently", () => {
    const guards: Record<string, GuardView> = {
      web: { path: "products", component: View, nav: { label: "Catálogo", to: "/products" } },
      admin: { path: "products", component: OtherView, nav: { label: "Products", to: "/products" } },
    }

    expect(createRegistry([plugin("product", guards)], "admin").views[0]?.component).toBe(OtherView)
    expect(createRegistry([plugin("product", guards)], "admin").nav[0]?.label).toBe("Products")
  })
})
