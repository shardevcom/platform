import 'package:flutter_test/flutter_test.dart';
import 'package:shardev_core/shardev_core.dart';

class _FakeApi implements PasskeyApi {
  String? loginEmail;
  String? registeredName;
  Map<String, dynamic>? registeredCredential;
  List<Passkey> items = const [];
  String? removedId;

  @override
  Future<Map<String, dynamic>> passkeyRegistrationOptions() async => {
        'publicKey': {
          'challenge': 'challenge',
          'rp': {'id': 'example.com', 'name': 'Shardev'},
          'user': {'id': 'handle', 'name': 'ada@example.com', 'displayName': 'Ada'},
        },
      };

  @override
  Future<Map<String, dynamic>> passkeyLoginOptions({String? email}) async {
    loginEmail = email;
    return {
      'publicKey': {
        'challenge': 'challenge',
        'rpId': 'example.com',
        'allowCredentials': const [],
      },
    };
  }

  @override
  Future<List<Passkey>> passkeys() async => items;

  @override
  Future<Passkey> registerPasskey(Map<String, dynamic> credential, {String? name}) async {
    registeredCredential = credential;
    registeredName = name;
    return const Passkey(id: 'p1', name: 'Mobile');
  }

  @override
  Future<void> removePasskey(String id) async {
    removedId = id;
  }
}

class _FakeClient implements PasskeyClient {
  Map<String, dynamic>? registeredWith;
  Map<String, dynamic>? authenticatedWith;

  @override
  Future<Map<String, dynamic>> register(Map<String, dynamic> options) async {
    registeredWith = options;
    return {'id': 'credential', 'rawId': 'credential', 'type': 'public-key'};
  }

  @override
  Future<Map<String, dynamic>> authenticate(Map<String, dynamic> options) async {
    authenticatedWith = options;
    return {'id': 'credential', 'rawId': 'credential', 'type': 'public-key'};
  }
}

void main() {
  test('Passkey decodes the credential listing', () {
    final passkey = Passkey.fromJson({
      'id': 7,
      'name': 'Laptop',
      'credential_id': 'abc',
      'created_at': '2026-10-20T10:00:00Z',
      'last_used_at': null,
    });

    expect(passkey.id, '7');
    expect(passkey.name, 'Laptop');
    expect(passkey.credentialId, 'abc');
    expect(passkey.lastUsedAt, isNull);
  });

  test('registration asks the API for options, the device for a credential and the API to verify it',
      () async {
    final api = _FakeApi();
    final client = _FakeClient();
    final service = PasskeyService(api, client);

    final passkey = await service.register(name: 'Mobile');

    expect(client.registeredWith?['publicKey'], isA<Map>());
    expect(api.registeredCredential?['id'], 'credential');
    expect(api.registeredName, 'Mobile');
    expect(passkey.id, 'p1');
  });

  test('login scopes the options to the email and returns the signed assertion', () async {
    final api = _FakeApi();
    final client = _FakeClient();
    final service = PasskeyService(api, client);

    final credential = await service.authenticate(email: 'ada@example.com');

    expect(api.loginEmail, 'ada@example.com');
    expect(client.authenticatedWith?['publicKey'], isA<Map>());
    expect(credential['id'], 'credential');
  });

  test('list and remove delegate to the account API', () async {
    final api = _FakeApi()
      ..items = const [Passkey(id: 'p1', name: 'Mobile')];
    final service = PasskeyService(api, _FakeClient());

    expect(await service.list(), hasLength(1));

    await service.remove('p1');
    expect(api.removedId, 'p1');
  });
}
