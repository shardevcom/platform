"use client"

import { useState } from "react"
import { NavLink } from "react-router-dom"
import { LogOut, Menu, X } from "lucide-react"

import { cn, Badge } from "@shardev/ui"

import { useAuth } from "../providers/AuthProvider"
import { useNav } from "../nav-context"

export function Sidebar() {
  const [collapsed, setCollapsed] = useState(false)
  const { user, guard, logout } = useAuth()
  const navigation = useNav()

  return (
    <aside
      className={cn(
        "z-40 flex h-screen shrink-0 flex-col border-r bg-card transition-all duration-300",
        collapsed ? "w-16" : "w-64",
      )}
    >
      <div className={cn("flex h-16 items-center justify-between border-b px-4", collapsed && "justify-center")}>
        {!collapsed && <span className="text-xl font-bold text-primary">Shardev</span>}
        <button
          onClick={() => setCollapsed(!collapsed)}
          className="h-8 w-8 rounded-md p-2 transition-colors hover:bg-accent"
          aria-label={collapsed ? "Expandir menú" : "Colapsar menú"}
        >
          {collapsed ? <Menu className="h-4 w-4" /> : <X className="h-4 w-4" />}
        </button>
      </div>

      <nav className="flex-1 space-y-1 overflow-y-auto p-2" role="navigation" aria-label="Navegación principal">
        {navigation.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) =>
              cn(
                "flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors",
                isActive ? "bg-primary/10 text-primary" : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
                collapsed && "justify-center",
              )
            }
          >
            {item.icon && <item.icon className="h-5 w-5 flex-shrink-0" aria-hidden="true" />}
            {!collapsed && <span className="font-medium">{item.label}</span>}
          </NavLink>
        ))}
      </nav>

      <div className="border-t p-4">
        <div className="flex items-center gap-3">
          {!collapsed && (
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium">{user?.name ?? "Invitado"}</p>
              <p className="truncate text-xs text-muted-foreground">{user?.email}</p>
              <Badge variant="secondary" className="mt-1 text-[10px] uppercase">
                {guard}
              </Badge>
            </div>
          )}
          <button
            onClick={() => void logout()}
            className="h-8 w-8 rounded-md p-2 transition-colors hover:bg-accent"
            aria-label="Cerrar sesión"
          >
            <LogOut className="h-4 w-4" />
          </button>
        </div>
      </div>
    </aside>
  )
}
