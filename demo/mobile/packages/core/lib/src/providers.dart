import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

import 'api/account_api.dart';
import 'api/api_client.dart';
import 'auth/auth.dart';
import 'config.dart';
import 'passkeys/passkey_client.dart';
import 'passkeys/passkey_service.dart';

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

/// Account endpoints (profile, password, preferences, notifications).
final accountApiProvider = Provider<AccountApi>((ref) => AccountApi(ref.watch(apiClientProvider)));

/// Platform WebAuthn authenticator (Android Credential Manager / iOS ASAuthorization).
final passkeyClientProvider = Provider<PasskeyClient>((ref) => PlatformPasskeyClient());

/// Passkey ceremonies shared by login and the account screen.
final passkeyServiceProvider = Provider<PasskeyService>(
  (ref) => PasskeyService(ref.watch(accountApiProvider), ref.watch(passkeyClientProvider)),
);

/// Auth state for the whole app.
final authProvider = NotifierProvider<AuthNotifier, AuthState>(AuthNotifier.new);
