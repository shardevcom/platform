"use client"

import * as React from "react"
import { Link, useLocation, NavLink } from "react-router-dom"
import { cn } from "@/lib/utils"
import {
  LayoutDashboard,
  Users,
  Key,
  Shield,
  Mail,
  Settings,
  LogOut,
  Menu,
  X,
} from "lucide-react"
import { useState } from "react"
import { cn } from "@/lib/utils"
import { useAuth } from "@/hooks/useAuth"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"

const navigation = [
  { name: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { name: "Passkeys", href: "/passkeys", icon: Key },
  { name: "2FA", href: "/2fa", icon: Shield },
  { name: "OTP", href: "/otp", icon: Mail },
  { name: "Configuración", href: "/settings", icon: Settings },
]

export function Sidebar() {
  const [collapsed, setCollapsed] = useState(false)
  const location = useLocation()
  const { user, logout } = useAuth()

  return (
    <aside
      className={cn(
        "fixed left-0 top-0 z-40 h-screen w-64 border-r bg-card transition-all duration-300",
        collapsed && "w-16"
      )}
    >
      <div className="flex h-full flex-col">
        {/* Logo */}
        <div className={cn("flex h-16 items-center justify-between px-4 border-b", collapsed && "justify-center")}>
          {!collapsed && (
            <Link to="/dashboard" className="flex items-center gap-2">
              <span className="text-xl font-bold text-primary">Shardev</span>
            </Link>
          )}
          <button
            onClick={() => setCollapsed(!collapsed)}
            className="h-8 w-8 p-2 rounded-md hover:bg-accent transition-colors"
            aria-label={collapsed ? "Expandir menú" : "Colapsar menú"}
          >
            {collapsed ? <Menu className="h-4 w-4" /> : <X className="h-4 w-4" />}
          </button>
        </div>

        {/* Navegación */}
        <nav className="flex-1 space-y-1 p-2 overflow-y-auto" role="navigation" aria-label="Navegación principal">
          {navigation.map((item) => {
            const isActive = location.pathname === item.href
            return (
              <NavLink
                key={item.name}
                to={item.href}
                className={({ isActive }) =>
                  cn(
                    "flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors",
                    isActive
                      ? "bg-primary/10 text-primary"
                      : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
                    !collapsed ? "" : "justify-center"
                  )}
                aria-current={location.pathname === item.href ? "page" : undefined}
              >
                <item.icon className="h-5 w-5 flex-shrink-0" aria-hidden="true" />
                {!collapsed && <span className="font-medium">{item.name}</span>}
              </NavLink>
            )
          })}
        </nav>

        {/* Footer */}
        <div className="border-t p-4">
          <div className="flex items-center gap-3">
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium truncate">Usuario</p>
              <p className="text-xs text-muted-foreground truncate">usuario@ejemplo.com</p>
            </div>
            <button
              className="h-8 w-8 p-2 rounded-md hover:bg-accent transition-colors"
              aria-label="Cerrar sesión"
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </aside>
  )
}

export function MobileSidebarToggle() {
  const [sidebarOpen, setSidebarOpen] = useState(false)

  return (
    <button
      type="button"
      className="lg:hidden p-2"
      onClick={() => setSidebarOpen(!sidebarOpen)}
      aria-label="Toggle menu"
    >
      <Menu className="h-6 w-6" />
    </button>
  )
}
