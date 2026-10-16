"use client"

import { useAuth } from "@/providers/AuthProvider"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { LogOut } from "lucide-react"

export function Header() {
  const { user, platform, logout } = useAuth()

  return (
    <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b bg-background/95 px-6 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="flex items-center gap-3">
        <h1 className="text-lg font-bold text-primary">Shardev Platform</h1>
        <Badge variant="outline" className="uppercase">
          {platform}
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
