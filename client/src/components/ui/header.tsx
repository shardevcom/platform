"use client"

import * as React from "react"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { useAuth } from "@/hooks/useAuth"
import { LogOut, Bell, Settings, User } from "lucide-react"
import { useAuth } from "@/hooks/useAuth"

export function Header() {
  const { user, logout } = useAuth()

  return (
    <header className="sticky top-0 z-30 h-16 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="container flex h-16 items-center justify-between px-4">
        <div className="flex items-center gap-4">
          <h1 className="text-xl font-bold text-primary">Shardev Platform</h1>
        </div>

        <div className="flex items-center gap-4">
          <button className="relative p-2 rounded-full hover:bg-accent transition-colors">
            <span className="h-5 w-5" />
            <span className="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500" />
          </button>

          <div className="flex items-center gap-2">
            <div className="hidden md:flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-sm">
              <span className="text-muted-foreground">UTC</span>
            </div>

            <div className="relative">
              <button className="flex h-8 w-8 items-center justify-center rounded-full border hover:bg-accent transition-colors">
                <User className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>
  )
}
