import 'package:flutter_web_auth_2/flutter_web_auth_2.dart';

/// Opens the provider's OAuth page and waits for the app callback.
abstract interface class SocialAuthClient {
  Future<Uri> authenticate({required Uri url, required String callbackScheme});
}

/// System browser (Custom Tabs / ASWebAuthenticationSession) implementation.
class PlatformSocialAuthClient implements SocialAuthClient {
  @override
  Future<Uri> authenticate({required Uri url, required String callbackScheme}) async {
    final result = await FlutterWebAuth2.authenticate(
      url: url.toString(),
      callbackUrlScheme: callbackScheme,
    );

    return Uri.parse(result);
  }
}
