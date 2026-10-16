import type { ComponentType } from "react"

import type { Guard, NavItem, PluginDefinition } from "./types"

export interface ResolvedView {
  plugin: string
  title: string
  path: string
  component: ComponentType
  nav?: NavItem
}

export interface PluginRegistry {
  /** Routes to mount for the active guard. */
  views: ResolvedView[]
  /** Menu entries contributed by plugins, already sorted. */
  nav: NavItem[]
}

/**
 * Resolve which plugin views a given guard app should mount. This is the only
 * place that knows how a plugin maps to a guard, so apps stay declarative.
 */
export function createRegistry(plugins: PluginDefinition[], guard: Guard): PluginRegistry {
  const views: ResolvedView[] = []

  for (const plugin of plugins) {
    const view = plugin.guards[guard]

    if (!view) continue

    views.push({
      plugin: plugin.name,
      title: plugin.title,
      path: view.path,
      component: view.component,
      nav: view.nav,
    })
  }

  const nav = views
    .map((view) => view.nav)
    .filter((item): item is NavItem => item !== undefined)
    .sort((a, b) => (a.order ?? 10) - (b.order ?? 10))

  return { views, nav }
}
