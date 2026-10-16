import { StrictMode } from "react"
import { createRoot } from "react-dom/client"

import "@shardev/ui/styles.css"
import { setPlatform } from "@shardev/core"

import { App } from "./App"

setPlatform("admin")

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
