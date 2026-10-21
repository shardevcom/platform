import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

import '../providers.dart';

class _Entry {
  const _Entry(this.label, this.to, this.icon, this.order);

  final String label;
  final String to;
  final IconData icon;
  final int order;
}

/// The authenticated shell: an app bar, a navigation drawer built from the
/// guard's plugin registry, and the routed child.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.location, required this.child});

  final String location;
  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final guard = ref.watch(guardProvider);
    final registry = createRegistry(ref.watch(pluginsProvider), guard);
    final user = ref.watch(authProvider).user;

    final entries = <_Entry>[
      const _Entry('Dashboard', '/dashboard', Icons.dashboard_outlined, 0),
      for (final item in registry.nav)
        _Entry(item.label, item.to, item.icon ?? Icons.extension_outlined, item.order),
      const _Entry('Preferencias', '/preferences', Icons.tune, 85),
      const _Entry('Perfil', '/profile', Icons.person_outline, 90),
      const _Entry('Notificaciones', '/notifications', Icons.notifications_outlined, 95),
    ]..sort((a, b) => a.order.compareTo(b.order));

    return Scaffold(
      appBar: AppBar(
        title: Text('Shardev · ${guard.label}'),
        actions: [
          IconButton(
            tooltip: 'Cerrar sesión',
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authProvider.notifier).logout(),
          ),
        ],
      ),
      drawer: Drawer(
        child: SafeArea(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              UserAccountsDrawerHeader(
                accountName: Text(user?.name ?? user?.email ?? 'Sesión'),
                accountEmail: Text(user?.email ?? ''),
                currentAccountPicture: const CircleAvatar(child: Icon(Icons.person)),
              ),
              for (final entry in entries)
                ListTile(
                  leading: Icon(entry.icon),
                  title: Text(entry.label),
                  selected: location == entry.to,
                  onTap: () {
                    Navigator.of(context).pop();
                    if (location != entry.to) {
                      context.go(entry.to);
                    }
                  },
                ),
            ],
          ),
        ),
      ),
      body: child,
    );
  }
}
