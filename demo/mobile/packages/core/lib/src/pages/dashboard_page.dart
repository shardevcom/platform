import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';

/// Landing screen: who is signed in and what this guard can do.
class DashboardPage extends ConsumerWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    final guard = ref.watch(guardProvider);
    final user = auth.user;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Bienvenido', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 8),
        Card(
          child: ListTile(
            leading: const Icon(Icons.badge_outlined),
            title: Text(user?.name ?? user?.email ?? 'Sesión'),
            subtitle: Text('Guard: ${user?.guard ?? guard.id}'),
          ),
        ),
        const SizedBox(height: 16),
        Text('Permisos', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        if (user == null)
          const Text('Sin sesión.')
        else
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final ability in user.abilities)
                Chip(label: Text(ability)),
            ],
          ),
      ],
    );
  }
}
