import { PlatformApp } from "@shardev/core"
import { productPlugin } from "@plugins/product"

export function App() {
  return <PlatformApp guard="admin" appName="Shardev Admin" plugins={[productPlugin]} />
}
