"use client"

import { LogOut } from "lucide-react"

import { Badge, Button } from "@shardev/ui"

import { useAuth } from "../providers/AuthProvider"

export function Header() {
  const { user, guard, logout } = useAuth()

  return (
    <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b bg-background/95 px-6 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="flex items-center gap-3">
        <h1 className="text-lg font-bold text-primary">Shardev Platform</h1>
        <Badge variant="outline" className="uppercase">
          {guard}
        </Badge>
      </div>

      <div className="flex items-center gap-3">
        <span className="hidden text-sm text-muted-foreground md:inline">{user?.email}</span>
        <Button variant="ghost" size="sm" onClick={() => void logout()}>
          <LogOut className="mr-2 h-4 w-4" />
          Salir
        </Button>
      </div>
    </header>
  )
}
