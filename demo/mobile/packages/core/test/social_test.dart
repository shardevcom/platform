import 'package:flutter_test/flutter_test.dart';
import 'package:shardev_core/shardev_core.dart';

class _FakeApi implements SocialApi {
  String? provider;
  String? returnUrl;

  @override
  Future<List<SocialProvider>> socialProviders() async => const [
        SocialProvider(key: 'google', name: 'Google'),
        SocialProvider(key: 'microsoft', name: 'Microsoft'),
      ];

  @override
  Future<String> socialRedirect(String provider, {required String returnUrl}) async {
    this.provider = provider;
    this.returnUrl = returnUrl;

    return 'https://provider.example/oauth';
  }
}

class _FakeClient implements SocialAuthClient {
  Uri? url;
  String? scheme;
  Uri result = Uri.parse('shardev://auth/callback#token=token-1');

  @override
  Future<Uri> authenticate({required Uri url, required String callbackScheme}) async {
    this.url = url;
    scheme = callbackScheme;

    return result;
  }
}

void main() {
  test('provider listing comes from the API', () async {
    final service = SocialService(_FakeApi(), _FakeClient());

    final providers = await service.providers();

    expect(providers.map((provider) => provider.key), ['google', 'microsoft']);
  });

  test('login passes the app callback and returns the token from the fragment', () async {
    final api = _FakeApi();
    final client = _FakeClient();
    final service = SocialService(api, client);

    final token = await service.login('google');

    expect(api.provider, 'google');
    expect(api.returnUrl, PlatformConfig.socialCallbackUrl);
    expect(client.url, Uri.parse('https://provider.example/oauth'));
    expect(client.scheme, 'shardev');
    expect(token, 'token-1');
  });

  test('login reads a token returned in the query', () async {
    final client = _FakeClient()
      ..result = Uri.parse('shardev://auth/callback?token=query-token');
    final service = SocialService(_FakeApi(), client);

    expect(await service.login('microsoft'), 'query-token');
  });

  test('provider errors are surfaced', () async {
    final client = _FakeClient()
      ..result = Uri.parse('shardev://auth/callback#error=Acceso%20cancelado');
    final service = SocialService(_FakeApi(), client);

    await expectLater(
      service.login('google'),
      throwsA(isA<SocialAuthException>().having((error) => error.message, 'message', 'Acceso cancelado')),
    );
  });

  test('a missing token is rejected', () async {
    final client = _FakeClient()..result = Uri.parse('shardev://auth/callback');
    final service = SocialService(_FakeApi(), client);

    await expectLater(service.login('google'), throwsA(isA<SocialAuthException>()));
  });
}
