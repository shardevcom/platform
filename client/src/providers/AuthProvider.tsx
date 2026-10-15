"use client"

import { AuthProvider as ShardevAuthProvider } from "@shardev/common"
import { ReactNode } from "react"

export function AuthProvider({ children }: { children: React.ReactNode }) {
  return (
    <ShardevAuthProvider>
      {children}
    </ShardevAuthProvider>
  )
}
