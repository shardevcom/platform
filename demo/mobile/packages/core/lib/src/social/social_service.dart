import '../config.dart';
import 'social_auth_client.dart';
import 'social_provider.dart';

/// Social endpoints used by the login screen.
abstract interface class SocialApi {
  Future<List<SocialProvider>> socialProviders();

  Future<String> socialRedirect(String provider, {required String returnUrl});
}

/// A social login could not be completed (cancelled, denied or invalid).
class SocialAuthException implements Exception {
  const SocialAuthException(this.message);

  final String message;

  @override
  String toString() => message;
}

/// Runs the OAuth hand-off: the API builds the provider URL carrying our
/// callback, the system browser performs the login and the deep link returns
/// the Passport token.
class SocialService {
  const SocialService(this._api, this._client);

  final SocialApi _api;
  final SocialAuthClient _client;

  Future<List<SocialProvider>> providers() => _api.socialProviders();

  Future<String> login(String provider) async {
    if (provider.isEmpty) {
      throw const SocialAuthException('Proveedor social inválido.');
    }

    final callback = Uri.parse(PlatformConfig.socialCallbackUrl);
    final redirect = await _api.socialRedirect(provider, returnUrl: callback.toString());

    if (redirect.isEmpty) {
      throw const SocialAuthException('El proveedor no devolvió una URL de acceso.');
    }

    final result = await _client.authenticate(
      url: Uri.parse(redirect),
      callbackScheme: callback.scheme,
    );
    final values = <String, String>{
      ...result.queryParameters,
      ...Uri.splitQueryString(result.fragment),
    };
    final error = values['error'];

    if (error != null && error.isNotEmpty) {
      throw SocialAuthException(error);
    }

    final token = values['token'];

    if (token == null || token.isEmpty) {
      throw const SocialAuthException('El proveedor no devolvió un token de acceso.');
    }

    return token;
  }
}
