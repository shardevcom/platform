import {
  Bell,
  Code2,
  LayoutDashboard,
  LayoutGrid,
  Settings,
  Shield,
  SlidersHorizontal,
  User as UserIcon,
} from "lucide-react"

import type { NavItem } from "@shardev/plugin-kit"

/**
 * Navigation every app shares. Plugin-contributed entries (order 10 by default)
 * slot in between Dashboard (0) and these (50+).
 */
export const BASE_NAV: NavItem[] = [
  { label: "Dashboard", to: "/dashboard", icon: LayoutDashboard, order: 0 },
  { label: "Configuration", to: "/configuration", icon: Settings, order: 50 },
  { label: "Two-Factor", to: "/2fa", icon: Shield, order: 60 },
  { label: "Widgets", to: "/widgets", icon: LayoutGrid, order: 70 },
  { label: "GraphQL", to: "/graphql", icon: Code2, order: 80 },
  { label: "Preferencias", to: "/preferences", icon: SlidersHorizontal, order: 85 },
  { label: "Perfil", to: "/profile", icon: UserIcon, order: 90 },
  { label: "Notificaciones", to: "/notifications", icon: Bell, order: 95 },
]
