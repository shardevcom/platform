import { PlatformApp } from "@shardev/core"
import { productPlugin } from "@plugins/product"

export function App() {
  return <PlatformApp guard="employee" appName="Shardev Employee" plugins={[productPlugin]} />
}
