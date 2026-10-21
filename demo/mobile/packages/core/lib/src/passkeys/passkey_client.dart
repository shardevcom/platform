import 'package:passkeys/authenticator.dart';
import 'package:passkeys/types.dart';

/// Platform authenticator seam. The native implementation talks to Android
/// Credential Manager / iOS Authentication Services through the `passkeys`
/// plugin; tests inject a fake so the ceremony can be exercised headless.
abstract interface class PasskeyClient {
  Future<Map<String, dynamic>> register(Map<String, dynamic> options);

  Future<Map<String, dynamic>> authenticate(Map<String, dynamic> options);
}

/// WebAuthn options arrive under `publicKey` (W3C shape); the plugin receives
/// the inner object and returns a serialized credential ready for the API.
class PlatformPasskeyClient implements PasskeyClient {
  PlatformPasskeyClient({PasskeyAuthenticator? authenticator})
      : _authenticator = authenticator ?? PasskeyAuthenticator();

  final PasskeyAuthenticator _authenticator;

  @override
  Future<Map<String, dynamic>> register(Map<String, dynamic> options) async {
    final result = await _authenticator.register(
      RegisterRequestType.fromJson(_publicKey(options)),
    );

    return result.toJson();
  }

  @override
  Future<Map<String, dynamic>> authenticate(Map<String, dynamic> options) async {
    final result = await _authenticator.authenticate(
      AuthenticateRequestType.fromJson(
        _publicKey(options),
        preferImmediatelyAvailableCredentials: false,
      ),
    );

    return result.toJson();
  }

  static Map<String, dynamic> _publicKey(Map<String, dynamic> options) {
    final publicKey = options['publicKey'];

    return publicKey is Map
        ? Map<String, dynamic>.from(publicKey)
        : Map<String, dynamic>.from(options);
  }
}
