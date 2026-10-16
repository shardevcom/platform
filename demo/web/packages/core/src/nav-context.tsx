import { createContext, useContext, type ReactNode } from "react"

import type { NavItem } from "@shardev/plugin-kit"

const NavContext = createContext<NavItem[]>([])

/** Provides the merged (base + plugin) navigation to the shell. */
export function NavProvider({ items, children }: { items: NavItem[]; children: ReactNode }) {
  return <NavContext.Provider value={items}>{children}</NavContext.Provider>
}

export function useNav(): NavItem[] {
  return useContext(NavContext)
}
