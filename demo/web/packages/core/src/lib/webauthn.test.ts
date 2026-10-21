import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"

import { createPasskey, getPasskey, isWebAuthnAvailable } from "./webauthn"

class FakeAttestationResponse {
  clientDataJSON = new Uint8Array([1, 2, 3]).buffer
  attestationObject = new Uint8Array([4, 5]).buffer

  getTransports() {
    return ["internal"]
  }
}

class FakeAssertionResponse {
  clientDataJSON = new Uint8Array([1, 2, 3]).buffer
  authenticatorData = new Uint8Array([6]).buffer
  signature = new Uint8Array([7, 8]).buffer
  userHandle = new Uint8Array([9]).buffer
}

class FakeCredential {
  id = "Y3JlZA"
  rawId = new Uint8Array([9, 9]).buffer
  type = "public-key"
  response: unknown

  constructor(response: unknown) {
    this.response = response
  }

  getClientExtensionResults() {
    return {}
  }
}

const baseOptions = {
  challenge: "Y2hhbGxlbmdl",
  rp: { id: "localhost", name: "Shardev" },
  user: { id: "dXNlcg", name: "ada@example.com", displayName: "Ada" },
  pubKeyCredParams: [{ type: "public-key" as const, alg: -7 }],
}

const originalCredential = navigator.credentials
const originalPublicKeyCredential = globalThis.PublicKeyCredential

beforeEach(() => {
  Object.defineProperty(globalThis, "PublicKeyCredential", {
    configurable: true,
    value: class {},
  })
  Object.defineProperty(globalThis, "AuthenticatorAttestationResponse", {
    configurable: true,
    value: FakeAttestationResponse,
  })
  Object.defineProperty(globalThis, "AuthenticatorAssertionResponse", {
    configurable: true,
    value: FakeAssertionResponse,
  })
})

afterEach(() => {
  Object.defineProperty(globalThis, "PublicKeyCredential", {
    configurable: true,
    value: originalPublicKeyCredential,
  })
  Object.defineProperty(navigator, "credentials", {
    configurable: true,
    value: originalCredential,
  })
  vi.restoreAllMocks()
})

describe("webauthn", () => {
  it("decodes the creation options and encodes the attestation response", async () => {
    const create = vi.fn().mockResolvedValue(new FakeCredential(new FakeAttestationResponse()))
    Object.defineProperty(navigator, "credentials", { configurable: true, value: { create } })

    const credential = await createPasskey(baseOptions)

    const args = create.mock.calls[0][0].publicKey
    expect(Array.from(new Uint8Array(args.challenge))).toEqual(Array.from(new TextEncoder().encode("challenge")))
    expect(Array.from(new Uint8Array(args.user.id))).toEqual(Array.from(new TextEncoder().encode("user")))

    expect(credential).toMatchObject({
      id: "Y3JlZA",
      rawId: "CQk=",
      type: "public-key",
      response: {
        clientDataJSON: "AQID",
        attestationObject: "BAU",
        transports: ["internal"],
      },
    })
  })

  it("decodes the allow list and encodes the assertion response", async () => {
    const get = vi.fn().mockResolvedValue(new FakeCredential(new FakeAssertionResponse()))
    Object.defineProperty(navigator, "credentials", { configurable: true, value: { get } })

    const credential = await getPasskey({
      challenge: "Y2hhbGxlbmdl",
      rpId: "localhost",
      allowCredentials: [{ type: "public-key", id: "Y3JlZA" }],
    })

    const args = get.mock.calls[0][0].publicKey
    expect(Array.from(new Uint8Array(args.allowCredentials[0].id))).toEqual(
      Array.from(new TextEncoder().encode("cred")),
    )

    expect(credential.response).toMatchObject({
      clientDataJSON: "AQID",
      authenticatorData: "Bg",
      signature: "Bwg",
      userHandle: "CQ",
    })
  })

  it("reports availability from the browser API", () => {
    expect(isWebAuthnAvailable()).toBe(true)
  })
})
