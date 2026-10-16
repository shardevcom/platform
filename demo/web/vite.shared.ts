import path from "node:path"
import { defineConfig } from "vite"
import react from "@vitejs/plugin-react"

const webRoot = import.meta.dirname
const backend = process.env.PLATFORM_BACKEND ?? "http://localhost:8000"

/**
 * Shared Vite config for every guard app. Each app only supplies its directory
 * and dev port; aliases point at the shared packages/plugins so no build step
 * is needed between them.
 */
export function createAppConfig(appDir: string, port: number) {
  return defineConfig({
    root: appDir,
    publicDir: path.join(webRoot, "public"),
    plugins: [react()],
    resolve: {
      alias: [
        // More specific first: the CSS subpath must win over the bare package.
        { find: "@shardev/ui/styles.css", replacement: path.join(webRoot, "packages/ui/src/styles.css") },
        { find: "@shardev/ui", replacement: path.join(webRoot, "packages/ui/src") },
        { find: "@shardev/core", replacement: path.join(webRoot, "packages/core/src") },
        { find: "@shardev/plugin-kit", replacement: path.join(webRoot, "packages/plugin-kit/src") },
        { find: "@plugins/product", replacement: path.join(webRoot, "plugins/product/src") },
      ],
      dedupe: ["react", "react-dom"],
    },
    server: {
      port,
      proxy: {
        "/api": { target: backend, changeOrigin: true },
        "/admin": { target: backend, changeOrigin: true },
        "/employee": { target: backend, changeOrigin: true },
        "/graphql": { target: backend, changeOrigin: true },
        "/oauth": { target: backend, changeOrigin: true },
      },
    },
  })
}
