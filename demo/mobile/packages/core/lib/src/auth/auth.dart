import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';
import '../providers.dart';

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
        abilities: (json['abilities'] as List?)?.map((e) => e.toString()).toList() ?? const [],
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
  const AuthState({this.loading = false, this.user});

  final bool loading;
  final AuthUser? user;

  bool get authenticated => user != null;
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
  }) =>
      _authenticate(
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
  }) =>
      _authenticate(
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

  Future<void> _authenticate(String path, {required Map<String, Object?> body}) async {
    final result = await _api.data<Map<String, dynamic>>(path, method: 'POST', body: body);

    final token = result['access_token']?.toString();
    if (token == null || token.isEmpty) {
      state = const AuthState(loading: false);
      return;
    }

    ref.read(tokenProvider.notifier).state = token;
    final preferences = await SharedPreferences.getInstance();
    await preferences.setString(tokenKey, token);

    final user = result['user'];
    state = AuthState(
      loading: false,
      user: user is Map ? AuthUser.fromJson(Map<String, dynamic>.from(user)) : null,
    );
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
