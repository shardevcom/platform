import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

import '../pages/dashboard_page.dart';
import '../pages/login_page.dart';
import '../pages/profile_page.dart';
import '../providers.dart';
import '../widgets/app_shell.dart';

/// Pings [GoRouter] whenever auth changes, so the redirect re-evaluates.
class RouterNotifier extends ChangeNotifier {
  RouterNotifier(Ref ref) {
    ref.listen(authProvider, (_, __) => notifyListeners());
  }
}

final routerNotifierProvider = Provider<RouterNotifier>(RouterNotifier.new);

/// The app router: a login route plus a shell holding the core routes and every
/// plugin view declared for the active guard.
final routerProvider = Provider<GoRouter>((ref) {
  final notifier = ref.watch(routerNotifierProvider);
  final guard = ref.watch(guardProvider);
  final registry = createRegistry(ref.watch(pluginsProvider), guard);

  return GoRouter(
    refreshListenable: notifier,
    initialLocation: '/login',
    redirect: (context, state) {
      final auth = ref.read(authProvider);
      if (auth.loading) {
        return null;
      }

      final loggingIn = state.matchedLocation == '/login';

      if (!auth.authenticated) {
        return loggingIn ? null : '/login';
      }

      return loggingIn ? '/dashboard' : null;
    },
    routes: [
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginPage(),
      ),
      ShellRoute(
        builder: (context, state, child) => AppShell(location: state.matchedLocation, child: child),
        routes: [
          GoRoute(
            path: '/dashboard',
            builder: (context, state) => const DashboardPage(),
          ),
          GoRoute(
            path: '/profile',
            builder: (context, state) => const ProfilePage(),
          ),
          for (final view in registry.views)
            GoRoute(
              path: '/${view.path}',
              builder: (context, state) => view.builder(context),
            ),
        ],
      ),
    ],
  );
});
