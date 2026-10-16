/// Guard-aware plugin contract for the Shardev mobile client.
///
/// Mirrors the web `@shardev/plugin-kit`: a plugin declares, per guard, one
/// view (route + optional nav entry). The app resolves the registry for its
/// active guard and mounts the matching views. The mobile client ships a single
/// app with one flavor per guard, so `Guard` is the single source of truth for
/// both the API prefix and the plugin filtering.
library;

export 'src/guard.dart';
export 'src/plugin_definition.dart';
export 'src/registry.dart';
