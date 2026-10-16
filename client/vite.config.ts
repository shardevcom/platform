import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

const backend = 'http://localhost:8000'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  server: {
    proxy: {
      // REST surface of every platform plus the GraphQL and OAuth endpoints.
      '/api': { target: backend, changeOrigin: true },
      '/admin': { target: backend, changeOrigin: true },
      '/employee': { target: backend, changeOrigin: true },
      '/graphql': { target: backend, changeOrigin: true },
      '/oauth': { target: backend, changeOrigin: true },
    },
  },
})
