/** Only loopback HTTP callbacks are accepted, to avoid an open redirect. */
export function passkeyReturnTarget(raw: string | null): URL | null {
  if (!raw) return null

  try {
    const url = new URL(raw)
    const loopback = url.hostname === "127.0.0.1" || url.hostname === "localhost" || url.hostname === "[::1]"

    return url.protocol === "http:" && loopback ? url : null
  } catch {
    return null
  }
}

export function buildPasskeyReturnUrl(
  target: URL,
  state: string,
  values: Record<string, string>,
): string {
  const url = new URL(target.toString())
  url.searchParams.set("state", state)

  Object.entries(values).forEach(([key, value]) => url.searchParams.set(key, value))

  return url.toString()
}
