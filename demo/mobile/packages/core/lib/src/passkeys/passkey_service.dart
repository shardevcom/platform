import '../api/account_api.dart';
import 'passkey_client.dart';

/// Orchestrates the WebAuthn ceremonies: the API issues the options, the
/// platform authenticator produces the credential and the API verifies it.
class PasskeyService {
  const PasskeyService(this._api, this._client);

  final PasskeyApi _api;
  final PasskeyClient _client;

  Future<List<Passkey>> list() => _api.passkeys();

  Future<Passkey> register({String? name}) async {
    final options = await _api.passkeyRegistrationOptions();
    final credential = await _client.register(options);

    return _api.registerPasskey(credential, name: name);
  }

  Future<void> remove(String id) => _api.removePasskey(id);

  /// Returns the signed assertion ready for `auth/webauthn/login`.
  Future<Map<String, dynamic>> authenticate({String? email}) async {
    final options = await _api.passkeyLoginOptions(email: email);

    return _client.authenticate(options);
  }
}
