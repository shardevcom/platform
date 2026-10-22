import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';
import '../providers.dart';
import 'two_factor.dart';

/// The authenticated identity returned by `auth/me` and `auth/login`.
class AuthUser {
  const AuthUser({
    required this.id,
    this.name,
    this.email,
    this.guard,
    this.emailVerified = false,
    this.abilities = const [],
  });

  final String id;
  final String? name;
  final String? email;
  final String? guard;
  final bool emailVerified;
  final List<String> abilities;

  factory AuthUser.fromJson(Map<String, dynamic> json) => AuthUser(
    id: json['id']?.toString() ?? '',
    name: json['name'] as String?,
    email: json['email'] as String?,
    guard: json['guard'] as String?,
    emailVerified: json['email_verified'] == true,
    abilities:
        (json['abilities'] as List?)?.map((e) => e.toString()).toList() ??
        const [],
  );

  /// True when the token's abilities grant [permission].
  ///
  /// Supports the exact ability, a `resource.*` wildcard and the global `*`.
  bool can(String permission) {
    if (abilities.contains('*') || abilities.contains(permission)) {
      return true;
    }
    final resource = permission.split('.').first;
    return abilities.contains('$resource.*');
  }
}

/// Immutable auth state for the shell and the router redirect.
class AuthState {
  const AuthState({this.loading = false, this.user, this.pendingTwoFactor});

  final bool loading;
  final AuthUser? user;
  final PendingTwoFactor? pendingTwoFactor;

  bool get authenticated => user != null;
  bool get awaitingTwoFactor => pendingTwoFactor != null;
}

/// Real auth against the build's guard.
///
/// The Passport token is persisted with `shared_preferences`; on cold start the
/// token is restored and validated with `auth/me` before the shell is shown.
class AuthNotifier extends Notifier<AuthState> {
  static const tokenKey = 'shardev.access_token';

  @override
  AuthState build() {
    Future.microtask(_restore);
    return const AuthState(loading: true);
  }

  ApiClient get _api => ref.read(apiClientProvider);

  Future<void> _restore() async {
    final preferences = await SharedPreferences.getInstance();
    final token = preferences.getString(tokenKey);

    if (token == null || token.isEmpty) {
      state = const AuthState(loading: false);
      return;
    }

    ref.read(tokenProvider.notifier).state = token;

    try {
      final me = await _api.data<Map<String, dynamic>>('auth/me');
      state = AuthState(loading: false, user: AuthUser.fromJson(me));
    } catch (_) {
      await preferences.remove(tokenKey);
      ref.read(tokenProvider.notifier).state = null;
      state = const AuthState(loading: false);
    }
  }

  Future<void> login(String email, String password) => _authenticate(
    'auth/login',
    body: {'email': email, 'password': password, 'device_name': 'mobile'},
  );

  /// Passwordless sign-in with a passkey. The email scopes the challenge to
  /// that account's credentials (the server rejects an unscoped ceremony).
  Future<void> loginWithPasskey({String? email}) async {
    final credential = await ref
        .read(passkeyServiceProvider)
        .authenticate(email: email);

    await _authenticate(
      'auth/webauthn/login',
      body: {'credential': credential, 'device_name': 'mobile'},
    );
  }

  /// OAuth sign-in through the system browser. The provider returns a Passport
  /// token to the app deep link, which is then validated with `auth/me`.
  Future<void> loginWithSocial(String provider) async {
    final token = await ref.read(socialServiceProvider).login(provider);

    await _persistToken(token);

    try {
      final me = await _api.data<Map<String, dynamic>>('auth/me');
      state = AuthState(loading: false, user: AuthUser.fromJson(me));
    } catch (_) {
      final preferences = await SharedPreferences.getInstance();
      await preferences.remove(tokenKey);
      ref.read(tokenProvider.notifier).state = null;
      state = const AuthState(loading: false);
      rethrow;
    }
  }

  /// Passwordless sign-in with the code emailed to [email].
  Future<void> loginWithOtp(String email, String code) => _authenticate(
    'auth/otp/verify',
    body: {'email': email, 'code': code, 'device_name': 'mobile'},
  );

  /// Self-registration; only offered when the guard is public.
  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) => _authenticate(
    'auth/register',
    body: {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'device_name': 'mobile',
    },
  );

  /// Accepts an invitation and leaves the new account signed in.
  Future<void> acceptInvitation({
    required String token,
    required String name,
    required String password,
    required String passwordConfirmation,
  }) => _authenticate(
    'auth/invitations/accept',
    body: {
      'token': token,
      'name': name,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'device_name': 'mobile',
    },
  );

  /// Re-reads `auth/me`, e.g. after the profile or verification state changed.
  Future<void> refreshUser() async {
    final me = await _api.data<Map<String, dynamic>>('auth/me');
    state = AuthState(loading: false, user: AuthUser.fromJson(me));
  }

  /// Completes the second-factor step for the pending login.
  Future<void> verifyTwoFactor(String code, {String type = 'totp'}) async {
    final pending = state.pendingTwoFactor;

    if (pending == null) {
      throw StateError(
        'No hay un inicio de sesión pendiente de segundo factor.',
      );
    }

    final result = await ref
        .read(accountApiProvider)
        .twoFactorChallenge(token: pending.token, code: code, type: type);

    await _consumeAuthResult(result);
  }

  /// Confirms TOTP enrollment and adopts the issued token.
  Future<void> confirmTwoFactorSetup(String code) async {
    final pending = state.pendingTwoFactor;

    if (pending == null || !pending.enrollment) {
      throw StateError('No hay un enrolamiento de segundo factor pendiente.');
    }

    final result = await ref
        .read(accountApiProvider)
        .confirmTwoFactorSetup(enrollmentToken: pending.token, code: code);

    await _consumeAuthResult(result);
  }

  void cancelTwoFactor() {
    state = const AuthState(loading: false);
  }

  Future<void> _authenticate(
    String path, {
    required Map<String, Object?> body,
  }) async {
    final result = await _api.data<Map<String, dynamic>>(
      path,
      method: 'POST',
      body: body,
    );

    await _consumeAuthResult(result);
  }

  Future<void> _consumeAuthResult(Map<String, dynamic> result) async {
    final token = result['access_token']?.toString();
    if (token == null || token.isEmpty) {
      if (result['two_factor_enrollment_required'] == true) {
        final enrollmentToken = result['enrollment_token']?.toString() ?? '';
        final setup = await ref
            .read(accountApiProvider)
            .startTwoFactorSetup(enrollmentToken);
        state = AuthState(
          loading: false,
          pendingTwoFactor: PendingTwoFactor(
            token: enrollmentToken,
            enrollment: true,
            setup: setup,
          ),
        );
        return;
      }

      if (result['two_factor_required'] == true) {
        state = AuthState(
          loading: false,
          pendingTwoFactor: PendingTwoFactor(
            token: result['challenge_token']?.toString() ?? '',
            enrollment: false,
          ),
        );
        return;
      }

      state = const AuthState(loading: false);
      return;
    }

    final user = result['user'];

    await _adoptToken(
      token,
      user: user is Map
          ? AuthUser.fromJson(Map<String, dynamic>.from(user))
          : null,
    );
  }

  Future<void> _adoptToken(String token, {AuthUser? user}) async {
    await _persistToken(token);
    state = AuthState(loading: false, user: user);
  }

  Future<void> _persistToken(String token) async {
    ref.read(tokenProvider.notifier).state = token;
    final preferences = await SharedPreferences.getInstance();
    await preferences.setString(tokenKey, token);
  }

  Future<void> logout() async {
    try {
      await _api.data<Object?>('auth/logout', method: 'POST');
    } catch (_) {
      // A revoked or expired token must still be dropped locally.
    }

    final preferences = await SharedPreferences.getInstance();
    await preferences.remove(tokenKey);
    ref.read(tokenProvider.notifier).state = null;
    state = const AuthState(loading: false);
  }
}
