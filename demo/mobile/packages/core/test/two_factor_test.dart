import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shardev_core/shardev_core.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

class _FakeApi extends ApiClient {
  _FakeApi() : super(guard: Guard.admin, host: 'http://127.0.0.1:1');

  @override
  Future<T> data<T>(
    String path, {
    String method = 'GET',
    Map<String, Object?>? query,
    Object? body,
  }) async {
    final payload = switch (path) {
      'auth/login' => <String, dynamic>{
        'two_factor_enrollment_required': true,
        'enrollment_token': 'enroll-token',
        'methods': ['totp'],
        'expires_in': 600,
      },
      'auth/2fa/setup' => <String, dynamic>{
        'secret': 'JBSWY3DPEHPK3PXP',
        'otpauth_url': 'otpauth://totp/test',
        'qr_code': '<svg/>',
        'recovery_codes': ['CODE-ONE', 'CODE-TWO'],
      },
      'auth/2fa/setup/confirm' => <String, dynamic>{
        'token_type': 'Bearer',
        'access_token': 'mfa-token',
        'expires_in': 3600,
        'abilities': ['*'],
        'user': {
          'id': '1',
          'name': 'Ada',
          'email': 'admin@example.com',
          'guard': 'admin',
          'email_verified': true,
          'abilities': ['*'],
        },
      },
      _ => throw StateError('Unexpected path $path'),
    };

    return payload as T;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test(
    'privileged login starts enrollment and confirms into a session',
    () async {
      SharedPreferences.setMockInitialValues({});
      final api = _FakeApi();
      final container = ProviderContainer(
        overrides: [
          guardProvider.overrideWithValue(Guard.admin),
          apiClientProvider.overrideWithValue(api),
          accountApiProvider.overrideWithValue(AccountApi(api)),
        ],
      );
      addTearDown(container.dispose);

      await Future<void>.delayed(Duration.zero);
      await container
          .read(authProvider.notifier)
          .login('admin@example.com', 'password');

      final pending = container.read(authProvider).pendingTwoFactor;
      expect(pending, isNotNull);
      expect(pending!.enrollment, isTrue);
      expect(pending.token, 'enroll-token');
      expect(pending.setup?.secret, 'JBSWY3DPEHPK3PXP');
      expect(pending.setup?.recoveryCodes, ['CODE-ONE', 'CODE-TWO']);

      await container
          .read(authProvider.notifier)
          .confirmTwoFactorSetup('123456');

      final state = container.read(authProvider);
      expect(state.authenticated, isTrue);
      expect(state.user?.email, 'admin@example.com');
      expect(container.read(tokenProvider), 'mfa-token');
    },
  );

  test('challenge response exposes recovery-code metadata', () async {
    final setup = TwoFactorSetup.fromJson({
      'secret': 'JBSWY3DPEHPK3PXP',
      'otpauth_url': 'otpauth://totp/test',
      'recovery_codes': ['ONE'],
      'instructions': 'scan',
    });

    expect(setup.otpauthUrl, 'otpauth://totp/test');
    expect(setup.recoveryCodes, ['ONE']);
    expect(setup.instructions, 'scan');
  });
}
