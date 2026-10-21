import { ApiError } from "./api"

/** Human message for any thrown value, preferring the API's field details. */
export function errorMessage(error: unknown, fallback = "Algo salió mal."): string {
  if (error instanceof ApiError) {
    const details = error.details
    if (details) {
      const first = Object.values(details)[0]
      if (Array.isArray(first) && typeof first[0] === "string") return first[0]
    }
    return error.message || fallback
  }

  return error instanceof Error ? error.message : fallback
}
