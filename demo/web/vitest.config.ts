import path from "node:path"
import { defineConfig } from "vitest/config"
import react from "@vitejs/plugin-react"

const webRoot = import.meta.dirname

/**
 * Vitest shares the same aliases as the apps so the tests import the source
 * modules directly (no build step). jsdom backs the component tests.
 */
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: [
      { find: "@shardev/ui/styles.css", replacement: path.join(webRoot, "packages/ui/src/styles.css") },
      { find: "@shardev/ui", replacement: path.join(webRoot, "packages/ui/src") },
      { find: "@shardev/core", replacement: path.join(webRoot, "packages/core/src") },
      { find: "@shardev/plugin-kit", replacement: path.join(webRoot, "packages/plugin-kit/src") },
      { find: "@plugins/product", replacement: path.join(webRoot, "plugins/product/src") },
    ],
    dedupe: ["react", "react-dom"],
  },
  test: {
    environment: "jsdom",
    globals: true,
    setupFiles: [path.join(webRoot, "test/setup.ts")],
    include: ["packages/**/*.{test,spec}.{ts,tsx}", "plugins/**/*.{test,spec}.{ts,tsx}"],
    css: false,
  },
})
