import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

/// Build-time configuration, read from `--dart-define`.
///
/// The guard is fixed per build (one flavor per guard), so the app never asks
/// the user which platform to log into: a Passport token is only valid for the
/// guard that issued it.
class PlatformConfig {
  const PlatformConfig._();

  /// `--dart-define=GUARD=admin|employee|web` (defaults to `admin`).
  static const String guardId = String.fromEnvironment('GUARD', defaultValue: 'admin');

  /// `--dart-define=API_HOST=http://10.0.2.2:8000` for the Android emulator.
  static const String host = String.fromEnvironment('API_HOST', defaultValue: 'http://localhost:8000');

  static const String appName = String.fromEnvironment('APP_NAME', defaultValue: 'Shardev Mobile');

  static Guard get guard => Guard.fromId(guardId);
}
