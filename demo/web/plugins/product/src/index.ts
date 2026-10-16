import { Boxes } from "lucide-react"
import { definePlugin } from "@shardev/plugin-kit"

import { ProductAdminView } from "./guards/admin/ProductAdminView"
import { ProductManageView } from "./guards/employee/ProductManageView"
import { ProductCatalogView } from "./guards/web/ProductCatalogView"

/**
 * Product plugin (web side). One view per guard; the matching API package
 * declares the same contract in `plugin.json`.
 */
export const productPlugin = definePlugin({
  name: "product",
  title: "Products",
  resource: "products",
  guards: {
    web: {
      path: "products",
      component: ProductCatalogView,
      nav: { label: "Catálogo", to: "/products", icon: Boxes, order: 10 },
    },
    employee: {
      path: "products",
      component: ProductManageView,
      nav: { label: "Productos", to: "/products", icon: Boxes, order: 10 },
    },
    admin: {
      path: "products",
      component: ProductAdminView,
      nav: { label: "Products", to: "/products", icon: Boxes, order: 10 },
    },
  },
})

export { products } from "./endpoints"
export type { Brand, Product, ProductCapabilities, ProductInput, ProductQuery } from "./types"
