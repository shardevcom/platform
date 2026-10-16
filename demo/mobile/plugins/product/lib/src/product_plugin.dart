import 'package:flutter/material.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

import 'guards/admin_view.dart';
import 'guards/catalog_view.dart';
import 'guards/manage_view.dart';

/// Product plugin (mobile side). One view per guard; the matching API package
/// declares the same contract in `plugin.json`.
final productPlugin = definePlugin(
  PluginDefinition(
    name: 'product',
    title: 'Products',
    resource: 'products',
    guards: {
      Guard.web: GuardView(
        path: 'products',
        builder: (_) => const ProductCatalogView(),
        nav: const NavItem(
          label: 'Catálogo',
          to: '/products',
          icon: Icons.inventory_2_outlined,
          order: 10,
        ),
      ),
      Guard.employee: GuardView(
        path: 'products',
        builder: (_) => const ProductManageView(),
        nav: const NavItem(
          label: 'Productos',
          to: '/products',
          icon: Icons.inventory_2_outlined,
          order: 10,
        ),
      ),
      Guard.admin: GuardView(
        path: 'products',
        builder: (_) => const ProductAdminView(),
        nav: const NavItem(
          label: 'Products',
          to: '/products',
          icon: Icons.inventory_2_outlined,
          order: 10,
        ),
      ),
    },
  ),
);
