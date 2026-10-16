import { PlatformApp } from "@shardev/core"
import { productPlugin } from "@plugins/product"

export function App() {
  return <PlatformApp guard="web" appName="Shardev Web" plugins={[productPlugin]} />
}
