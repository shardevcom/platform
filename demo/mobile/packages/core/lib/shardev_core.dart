/// Shared shell, API client and auth for the Shardev mobile client.
///
/// `shardev_core` is app-agnostic: it receives the active [Guard] and the list
/// of plugins, and knows nothing about any concrete resource. A plugin (e.g.
/// `shardev_product_plugin`) depends on this package to call the API.
library;

export 'src/api/account_api.dart';
export 'src/api/api_client.dart';
export 'src/api/envelope.dart';
export 'src/auth/auth.dart';
export 'src/config.dart';
export 'src/passkeys/passkey_client.dart';
export 'src/passkeys/passkey_service.dart';
export 'src/providers.dart';
export 'src/router/app_router.dart';
export 'src/theme/app_theme.dart';
export 'src/widgets/app_shell.dart';
export 'src/pages/login_page.dart';
export 'src/pages/dashboard_page.dart';
export 'src/pages/profile_page.dart';
export 'src/pages/register_page.dart';
export 'src/pages/forgot_password_page.dart';
export 'src/pages/reset_password_page.dart';
export 'src/pages/verify_email_page.dart';
export 'src/pages/accept_invitation_page.dart';
export 'src/pages/preferences_page.dart';
export 'src/pages/notifications_page.dart';
