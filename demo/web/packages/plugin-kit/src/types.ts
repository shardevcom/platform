import type { ComponentType } from "react"

/** The authentication contexts a plugin can target, one app per guard. */
export type Guard = "web" | "admin" | "employee"

export interface NavItem {
  label: string
  to: string
  icon?: ComponentType<{ className?: string }>
  /** Lower first. Core items use 0/50+, plugins default to 10. */
  order?: number
}

/** A plugin's page for one guard, plus how (or whether) it shows in the menu. */
export interface GuardView {
  /** Route path relative to the app root, e.g. `products`. */
  path: string
  /** The page component rendered for this guard. */
  component: ComponentType
  /** Menu entry. Omit to keep the route reachable but hidden from the sidebar. */
  nav?: NavItem
}

/**
 * A plugin declares, per guard, which view to mount. The API package owns the
 * matching `plugin.json`; this is the web side of the same contract.
 */
export interface PluginDefinition {
  /** Stable id, matches the API package name (e.g. `product`). */
  name: string
  title: string
  /** Resource/permission prefix the views operate on (informational). */
  resource?: string
  guards: Partial<Record<Guard, GuardView>>
}
