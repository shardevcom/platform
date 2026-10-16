/// Shared shell, API client and auth for the Shardev mobile client.
///
/// `shardev_core` is app-agnostic: it receives the active [Guard] and the list
/// of plugins, and knows nothing about any concrete resource. A plugin (e.g.
/// `shardev_product_plugin`) depends on this package to call the API.
library;

export 'src/api/api_client.dart';
export 'src/api/envelope.dart';
export 'src/auth/auth.dart';
export 'src/config.dart';
export 'src/providers.dart';
export 'src/router/app_router.dart';
export 'src/theme/app_theme.dart';
export 'src/widgets/app_shell.dart';
export 'src/pages/login_page.dart';
export 'src/pages/dashboard_page.dart';
export 'src/pages/profile_page.dart';
