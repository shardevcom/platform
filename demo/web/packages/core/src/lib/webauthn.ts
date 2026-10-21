import type {
  PublicKeyCredentialCreationOptionsJSON,
  PublicKeyCredentialRequestOptionsJSON,
} from "../types"
import type { CredentialPayload } from "./endpoints"

function fromBase64Url(value: string): ArrayBuffer {
  const padded = value.replace(/-/g, "+").replace(/_/g, "/")
  const binary = atob(padded.padEnd(Math.ceil(padded.length / 4) * 4, "="))
  const bytes = new Uint8Array(binary.length)
  for (let i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i)
  return bytes.buffer
}

function toBase64Url(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer)
  let binary = ""
  for (const byte of bytes) binary += String.fromCharCode(byte)
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "")
}

function toBase64(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer)
  let binary = ""
  for (const byte of bytes) binary += String.fromCharCode(byte)
  return btoa(binary)
}

export function isWebAuthnAvailable(): boolean {
  return typeof window !== "undefined" && typeof window.PublicKeyCredential !== "undefined"
}

function encodeCredential(credential: PublicKeyCredential): CredentialPayload {
  const response = credential.response
  const payload: Record<string, unknown> = {
    clientDataJSON: toBase64Url(response.clientDataJSON),
  }

  if (response instanceof AuthenticatorAttestationResponse) {
    payload.attestationObject = toBase64Url(response.attestationObject)
    payload.transports = response.getTransports?.() ?? []
  }

  if (response instanceof AuthenticatorAssertionResponse) {
    payload.authenticatorData = toBase64Url(response.authenticatorData)
    payload.signature = toBase64Url(response.signature)
    payload.userHandle = response.userHandle ? toBase64Url(response.userHandle) : null
  }

  return {
    id: credential.id,
    rawId: toBase64(credential.rawId),
    type: credential.type,
    response: payload,
    clientExtensionResults: credential.getClientExtensionResults() as Record<string, unknown>,
  }
}

/** Run the browser's registration ceremony for a passkey. */
export async function createPasskey(
  options: PublicKeyCredentialCreationOptionsJSON,
): Promise<CredentialPayload> {
  if (!isWebAuthnAvailable()) {
    throw new Error("Este navegador no soporta passkeys.")
  }

  const credential = (await navigator.credentials.create({
    publicKey: {
      ...options,
      challenge: fromBase64Url(options.challenge),
      user: { ...options.user, id: fromBase64Url(options.user.id) },
      excludeCredentials: (options.excludeCredentials ?? []).map((descriptor) => ({
        ...descriptor,
        id: fromBase64Url(descriptor.id),
      })),
    },
  })) as PublicKeyCredential | null

  if (!credential) throw new Error("No se completó el registro de la passkey.")

  return encodeCredential(credential)
}

/** Run the browser's assertion ceremony to sign in with a passkey. */
export async function getPasskey(
  options: PublicKeyCredentialRequestOptionsJSON,
): Promise<CredentialPayload> {
  if (!isWebAuthnAvailable()) {
    throw new Error("Este navegador no soporta passkeys.")
  }

  const credential = (await navigator.credentials.get({
    publicKey: {
      ...options,
      challenge: fromBase64Url(options.challenge),
      allowCredentials: (options.allowCredentials ?? []).map((descriptor) => ({
        ...descriptor,
        id: fromBase64Url(descriptor.id),
      })),
    },
  })) as PublicKeyCredential | null

  if (!credential) throw new Error("No se completó el inicio de sesión con passkey.")

  return encodeCredential(credential)
}
