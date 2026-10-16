import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shardev_core/shardev_core.dart';

/// The app shell: wires the router and theme. Everything guard-specific comes
/// from the providers overridden in `main.dart`.
class ShardevApp extends ConsumerWidget {
  const ShardevApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: PlatformConfig.appName,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      routerConfig: router,
      debugShowCheckedModeBanner: false,
    );
  }
}
