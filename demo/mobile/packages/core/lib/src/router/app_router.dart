import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

import '../config.dart';
import '../pages/accept_invitation_page.dart';
import '../pages/dashboard_page.dart';
import '../pages/forgot_password_page.dart';
import '../pages/login_page.dart';
import '../pages/notifications_page.dart';
import '../pages/preferences_page.dart';
import '../pages/profile_page.dart';
import '../pages/register_page.dart';
import '../pages/reset_password_page.dart';
import '../pages/verify_email_page.dart';
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

  const publicRoutes = {
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/verify-email',
    '/accept-invitation',
  };

  return GoRouter(
    refreshListenable: notifier,
    initialLocation: '/login',
    redirect: (context, state) {
      final auth = ref.read(authProvider);
      if (auth.loading) {
        return null;
      }

      final location = state.matchedLocation;

      if (!auth.authenticated) {
        if (location == '/register' && !PlatformConfig.isPublic) {
          return '/login';
        }

        return publicRoutes.contains(location) ? null : '/login';
      }

      return publicRoutes.contains(location) ? '/dashboard' : null;
    },
    routes: [
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginPage(),
      ),
      GoRoute(
        path: '/register',
        builder: (context, state) => const RegisterPage(),
      ),
      GoRoute(
        path: '/forgot-password',
        builder: (context, state) => const ForgotPasswordPage(),
      ),
      GoRoute(
        path: '/reset-password',
        builder: (context, state) => ResetPasswordPage(
          token: state.uri.queryParameters['token'] ?? '',
          email: state.uri.queryParameters['email'] ?? '',
        ),
      ),
      GoRoute(
        path: '/verify-email',
        builder: (context, state) => VerifyEmailPage(
          verifyUrl: state.uri.queryParameters['verify_url'] ?? '',
        ),
      ),
      GoRoute(
        path: '/accept-invitation',
        builder: (context, state) => AcceptInvitationPage(
          token: state.uri.queryParameters['token'] ?? '',
        ),
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
          GoRoute(
            path: '/preferences',
            builder: (context, state) => const PreferencesPage(),
          ),
          GoRoute(
            path: '/notifications',
            builder: (context, state) => const NotificationsPage(),
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
