import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

import 'api/api_client.dart';
import 'auth/auth.dart';
import 'config.dart';

/// The guard this build targets. Overridden in `main.dart` from `--dart-define`.
final guardProvider = Provider<Guard>((ref) => PlatformConfig.guard);

/// Plugins registered for this build. Overridden in `main.dart`.
final pluginsProvider = Provider<List<PluginDefinition>>((ref) => const []);

/// The active Passport token. Kept separate so the API interceptor can read it
/// without depending on the auth notifier.
final tokenProvider = StateProvider<String?>((ref) => null);

/// REST adapter bound to the active guard and token.
final apiClientProvider = Provider<ApiClient>((ref) {
  final guard = ref.watch(guardProvider);
  return ApiClient(
    guard: guard,
    host: PlatformConfig.host,
    token: () => ref.read(tokenProvider),
  );
});

/// Auth state for the whole app.
final authProvider = NotifierProvider<AuthNotifier, AuthState>(AuthNotifier.new);
