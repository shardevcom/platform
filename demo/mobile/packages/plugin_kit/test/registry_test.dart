import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

PluginDefinition _plugin() => definePlugin(
      PluginDefinition(
        name: 'product',
        title: 'Products',
        resource: 'products',
        guards: {
          Guard.web: GuardView(
            path: 'products',
            builder: (_) => const SizedBox.shrink(),
            nav: const NavItem(label: 'Catálogo', to: '/products', order: 10),
          ),
          Guard.employee: GuardView(
            path: 'products',
            builder: (_) => const SizedBox.shrink(),
            nav: const NavItem(label: 'Productos', to: '/products', order: 10),
          ),
          Guard.admin: GuardView(
            path: 'products',
            builder: (_) => const SizedBox.shrink(),
            nav: const NavItem(label: 'Products', to: '/products', order: 10),
          ),
        },
      ),
    );

void main() {
  test('guard resolves from its id and falls back to admin', () {
    expect(Guard.fromId('web'), Guard.web);
    expect(Guard.fromId('employee'), Guard.employee);
    expect(Guard.fromId('unknown'), Guard.admin);
  });

  test('guard exposes the versioned api prefix', () {
    expect(Guard.web.apiBase, '/api/v1');
    expect(Guard.admin.apiBase, '/admin/api/v1');
    expect(Guard.employee.apiBase, '/employee/api/v1');
  });

  test('registry keeps only the views declared for the active guard', () {
    final registry = createRegistry([_plugin()], Guard.admin);

    expect(registry.views, hasLength(1));
    expect(registry.views.single.plugin, 'product');
    expect(registry.views.single.path, 'products');
  });

  test('registry exposes the guard nav entry', () {
    final registry = createRegistry([_plugin()], Guard.web);

    expect(registry.nav, hasLength(1));
    expect(registry.nav.single.label, 'Catálogo');
  });

  test('registry is empty for a guard the plugin does not support', () {
    final adminOnly = definePlugin(
      PluginDefinition(
        name: 'admin-only',
        title: 'Admin only',
        guards: {
          Guard.admin: GuardView(
            path: 'admin-only',
            builder: (_) => const SizedBox.shrink(),
          ),
        },
      ),
    );

    expect(createRegistry([adminOnly], Guard.web).views, isEmpty);
    expect(createRegistry([adminOnly], Guard.employee).views, isEmpty);
    expect(createRegistry([adminOnly], Guard.admin).views, hasLength(1));
  });

  test('registry sorts nav entries by order', () {
    final plugin = definePlugin(
      PluginDefinition(
        name: 'x',
        title: 'X',
        guards: {
          Guard.admin: GuardView(
            path: 'x',
            builder: (_) => const SizedBox.shrink(),
            nav: const NavItem(label: 'X', to: '/x', order: 50),
          ),
        },
      ),
    );
    final other = definePlugin(
      PluginDefinition(
        name: 'y',
        title: 'Y',
        guards: {
          Guard.admin: GuardView(
            path: 'y',
            builder: (_) => const SizedBox.shrink(),
            nav: const NavItem(label: 'Y', to: '/y', order: 5),
          ),
        },
      ),
    );

    final registry = createRegistry([plugin, other], Guard.admin);

    expect(registry.nav.map((n) => n.label), ['Y', 'X']);
  });
}
