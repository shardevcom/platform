import { describe, expect, it } from "vitest"

import { createRegistry } from "@shardev/plugin-kit"

import { productPlugin } from "./index"

describe("productPlugin", () => {
  it("declares the product resource", () => {
    expect(productPlugin.name).toBe("product")
    expect(productPlugin.resource).toBe("products")
  })

  it.each(["web", "employee", "admin"] as const)("mounts a view for the %s guard", (guard) => {
    const registry = createRegistry([productPlugin], guard)

    expect(registry.views).toHaveLength(1)
    expect(registry.views[0]?.path).toBe("products")
    expect(registry.views[0]?.nav?.to).toBe("/products")
  })
})
