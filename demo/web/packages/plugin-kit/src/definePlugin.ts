import type { PluginDefinition } from "./types"

/** Identity helper: gives editors type-checking while keeping the object literal. */
export function definePlugin(definition: PluginDefinition): PluginDefinition {
  return definition
}
