"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Badge } from "@/components/ui/badge"
import { Trash2, Key, CheckCircle, Loader2 } from "lucide-react"
import { useAuth } from "@/hooks/useAuth"
import { cn } from "@/lib/utils"

interface Passkey {
  id: string
  name: string
  createdAt: string
  lastUsed?: string
}

export function PasskeyPage() {
  const { user } = useAuth()
  const [passkeys, setPasskeys] = useState<Passkey[]>([])
  const [loading, setLoading] = useState(false)
  const [registering, setRegistering] = useState(false)
  const [name, setName] = useState("")

  const fetchPasskeys = async () => {
    try {
      const res = await fetch("/api/passkeys", {
        headers: { Authorization: `Bearer ${localStorage.getItem("access_token")}` }
      })
      if (res.ok) {
        const data = await res.json()
        setPasskeys(data)
      }
    } catch (err) {
      console.error("Error loading passkeys:", err)
    }
  }

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault()
    setRegistering(true)
    try {
      const res = await fetch("/api/passkeys/register", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("access_token")}`
        },
        body: JSON.stringify({ name })
      })
      if (res.ok) {
        setName("")
        // Would need to implement the WebAuthn registration flow here
      }
    } catch (err) {
      console.error("Error registering passkey:", err)
    } finally {
      setRegistering(false)
    }
  }

  const handleDelete = async (id: string) => {
    if (!confirm("¿Eliminar esta passkey?")) return
    try {
      await fetch(`/api/passkeys/${id}`, {
        method: "DELETE",
        headers: { Authorization: `Bearer ${localStorage.getItem("access_token")}` }
      })
      setPasskeys(prev => prev.filter(p => p.id !== id))
    } catch (err) {
      console.error("Error deleting passkey:", err)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Passkeys</h1>
        <p className="text-muted-foreground">
          Gestiona tus claves de acceso (Passkeys/WebAuthn)
        </p>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Registrar nueva Passkey</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleRegister} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="name">Nombre de la passkey</Label>
              <Input
                id="name"
                placeholder="Ej: Mi iPhone, Llave USB, etc."
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </div>
            <Button type="submit" disabled={!name.trim()}>
              Registrar Passkey
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Tus Passkeys</CardTitle>
        </CardHeader>
        <CardContent>
          {passkeys.length === 0 ? (
            <p className="text-muted-foreground text-center py-8">
              No tienes passkeys registradas. Registra una arriba.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Nombre</TableHead>
                  <TableHead>Creado</TableHead>
                  <TableHead>Último uso</TableHead>
                  <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {passkeys.map((pk) => (
                  <TableRow key={pk.id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <span className="font-medium">{pk.name}</span>
                      </div>
                    </TableCell>
                    <TableCell>{new Date(pk.createdAt).toLocaleDateString()}</TableCell>
                    <TableCell>{pk.lastUsed ? new Date(pk.lastUsed).toLocaleDateString() : "Nunca"}</TableCell>
                    <TableCell className="text-right">
                      <button
                        onClick={() => handleDelete(pk.id)}
                        className="text-destructive hover:underline text-sm"
                      >
                        Eliminar
                      </button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
