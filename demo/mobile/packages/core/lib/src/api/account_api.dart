import '../social/social_provider.dart';
import '../social/social_service.dart';
import 'api_client.dart';

/// A registered passkey (`GET auth/webauthn/credentials`).
class Passkey {
  const Passkey({
    required this.id,
    this.name,
    this.credentialId,
    this.createdAt,
    this.lastUsedAt,
  });

  final String id;
  final String? name;
  final String? credentialId;
  final String? createdAt;
  final String? lastUsedAt;

  factory Passkey.fromJson(Map<String, dynamic> json) => Passkey(
        id: json['id']?.toString() ?? '',
        name: json['name']?.toString(),
        credentialId: json['credential_id']?.toString(),
        createdAt: json['created_at']?.toString(),
        lastUsedAt: json['last_used_at']?.toString(),
      );
}

/// Passkey endpoints used by the account screen and the passwordless login.
abstract interface class PasskeyApi {
  Future<Map<String, dynamic>> passkeyRegistrationOptions();

  Future<Map<String, dynamic>> passkeyLoginOptions({String? email});

  Future<List<Passkey>> passkeys();

  Future<Passkey> registerPasskey(Map<String, dynamic> credential, {String? name});

  Future<void> removePasskey(String id);
}

/// Account preferences returned complete by `GET auth/preferences`.
class Preferences {
  const Preferences({
    required this.theme,
    required this.language,
    required this.notifications,
  });

  final String theme;
  final String language;
  final Map<String, bool> notifications;

  factory Preferences.fromJson(Map<String, dynamic> json) => Preferences(
        theme: json['theme']?.toString() ?? 'system',
        language: json['language']?.toString() ?? 'es',
        notifications: (json['notifications'] as Map?)?.map(
              (key, value) => MapEntry(key.toString(), value == true),
            ) ??
            const {},
      );

  Map<String, Object?> toJson() => {
        'theme': theme,
        'language': language,
        'notifications': notifications,
      };

  Preferences copyWith({String? theme, String? language, Map<String, bool>? notifications}) => Preferences(
        theme: theme ?? this.theme,
        language: language ?? this.language,
        notifications: notifications ?? this.notifications,
      );
}

class AccountNotification {
  const AccountNotification({
    required this.id,
    required this.type,
    required this.data,
    this.readAt,
    this.createdAt,
  });

  final String id;
  final String type;
  final Map<String, dynamic> data;
  final String? readAt;
  final String? createdAt;

  bool get read => readAt != null;

  factory AccountNotification.fromJson(Map<String, dynamic> json) => AccountNotification(
        id: json['id']?.toString() ?? '',
        type: json['type']?.toString() ?? 'notification',
        data: (json['data'] as Map?)?.map((key, value) => MapEntry(key.toString(), value)) ?? const {},
        readAt: json['read_at']?.toString(),
        createdAt: json['created_at']?.toString(),
      );
}

class NotificationsFeed {
  const NotificationsFeed({required this.items, required this.unreadCount});

  final List<AccountNotification> items;
  final int unreadCount;

  factory NotificationsFeed.fromJson(Map<String, dynamic> json) => NotificationsFeed(
        items: (json['items'] as List?)
                ?.whereType<Map>()
                .map((item) => AccountNotification.fromJson(Map<String, dynamic>.from(item)))
                .toList() ??
            const [],
        unreadCount: int.tryParse(json['unread_count']?.toString() ?? '') ?? 0,
      );
}

class InvitationSummary {
  const InvitationSummary({required this.email, required this.guard});

  final String email;
  final String guard;

  factory InvitationSummary.fromJson(Map<String, dynamic> json) => InvitationSummary(
        email: json['email']?.toString() ?? '',
        guard: json['guard']?.toString() ?? '',
      );
}

/// Account endpoints that are not part of the sign-in flow.
class AccountApi implements PasskeyApi, SocialApi {
  const AccountApi(this._api);

  final ApiClient _api;

  @override
  Future<Map<String, dynamic>> passkeyRegistrationOptions() =>
      _api.data<Map<String, dynamic>>('auth/webauthn/register/options', method: 'POST');

  @override
  Future<Map<String, dynamic>> passkeyLoginOptions({String? email}) =>
      _api.data<Map<String, dynamic>>(
        'auth/webauthn/login/options',
        method: 'POST',
        body: {
          if (email != null && email.isNotEmpty) 'email': email,
        },
      );

  @override
  Future<List<SocialProvider>> socialProviders() async {
    final data = await _api.data<Map<String, dynamic>>('auth/social/providers');
    final items = data['providers'] as List? ?? const [];

    return items
        .whereType<Map>()
        .map((item) => SocialProvider.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  @override
  Future<String> socialRedirect(String provider, {required String returnUrl}) async {
    final data = await _api.data<Map<String, dynamic>>(
      'auth/social/${Uri.encodeComponent(provider)}',
      query: {'return': returnUrl},
    );

    return data['redirect_url']?.toString() ?? '';
  }

  @override
  Future<List<Passkey>> passkeys() async {
    final data = await _api.data<Map<String, dynamic>>('auth/webauthn/credentials');
    final items = data['items'] as List? ?? const [];

    return items
        .whereType<Map>()
        .map((item) => Passkey.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  @override
  Future<Passkey> registerPasskey(Map<String, dynamic> credential, {String? name}) async {
    final data = await _api.data<Map<String, dynamic>>(
      'auth/webauthn/register',
      method: 'POST',
      body: {
        'credential': credential,
        if (name != null && name.isNotEmpty) 'name': name,
      },
    );

    return Passkey.fromJson(data);
  }

  @override
  Future<void> removePasskey(String id) =>
      _api.data<Object?>('auth/webauthn/credentials/$id', method: 'DELETE');

  Future<void> updateProfile({String? name, String? email}) => _api.data<Object?>(
        'auth/profile',
        method: 'PATCH',
        body: {
          if (name != null) 'name': name,
          if (email != null) 'email': email,
        },
      );

  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) =>
      _api.data<Object?>(
        'auth/password',
        method: 'PUT',
        body: {
          'current_password': currentPassword,
          'password': password,
          'password_confirmation': passwordConfirmation,
        },
      );

  Future<Preferences> preferences() async {
    final data = await _api.data<Map<String, dynamic>>('auth/preferences');
    return Preferences.fromJson(data);
  }

  Future<Preferences> savePreferences(Preferences preferences) async {
    final data = await _api.data<Map<String, dynamic>>(
      'auth/preferences',
      method: 'PUT',
      body: preferences.toJson(),
    );
    return Preferences.fromJson(data);
  }

  Future<NotificationsFeed> notifications() async {
    final data = await _api.data<Map<String, dynamic>>('auth/notifications');
    return NotificationsFeed.fromJson(data);
  }

  Future<void> readNotification(String id) =>
      _api.data<Object?>('auth/notifications/$id/read', method: 'POST');

  Future<void> readAllNotifications() =>
      _api.data<Object?>('auth/notifications/read-all', method: 'POST');

  Future<void> deleteNotification(String id) =>
      _api.data<Object?>('auth/notifications/$id', method: 'DELETE');

  Future<void> disable({required String currentPassword, String? reason}) => _api.data<Object?>(
        'auth/account/disable',
        method: 'POST',
        body: {
          'current_password': currentPassword,
          if (reason != null && reason.isNotEmpty) 'reason': reason,
        },
      );

  Future<InvitationSummary> invitation(String token) async {
    final data = await _api.data<Map<String, dynamic>>('auth/invitations/${Uri.encodeComponent(token)}');
    return InvitationSummary.fromJson(data);
  }

  Future<void> requestOtp(String email) =>
      _api.data<Object?>('auth/otp/request', method: 'POST', body: {'email': email});

  Future<void> forgotPassword(String email) =>
      _api.data<Object?>('auth/forgot-password', method: 'POST', body: {'email': email});

  Future<void> resetPassword({
    required String token,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) =>
      _api.data<Object?>(
        'auth/reset-password',
        method: 'POST',
        body: {
          'token': token,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirmation,
        },
      );

  Future<void> resendVerification() =>
      _api.data<Object?>('auth/email/verify/resend', method: 'POST');
}
