import 'package:flutter/widgets.dart';

import 'guard.dart';

/// A navigation entry contributed by a plugin for the active guard.
///
/// Lower [order] comes first. Core items use 0/50+; plugins default to 10 so a
/// plugin sits right after the dashboard but before the account section.
class NavItem {
  const NavItem({
    required this.label,
    required this.to,
    this.icon,
    this.order = 10,
  });

  final String label;

  /// Absolute route path, e.g. `/products`.
  final String to;

  final IconData? icon;

  final int order;
}

/// The view a plugin exposes for exactly one guard.
class GuardView {
  const GuardView({
    required this.path,
    required this.builder,
    this.nav,
  });

  /// Route path relative to the app root, e.g. `products`.
  ///
  /// Must match the API `plugin.json` `guards.<guard>.path` by convention.
  final String path;

  /// Builds the screen. Kept as a plain [WidgetBuilder] so the plugin contract
  /// does not depend on the routing library.
  final WidgetBuilder builder;

  /// Optional nav entry; omit to keep the route reachable but hidden.
  final NavItem? nav;
}

/// A self-contained feature contributed to the mobile client.
class PluginDefinition {
  const PluginDefinition({
    required this.name,
    required this.title,
    this.resource,
    required this.guards,
  });

  /// Matches the API package name, e.g. `product`.
  final String name;

  final String title;

  /// Permission prefix (informational), e.g. `products`.
  final String? resource;

  /// One view per guard. Guards absent from the map have no screen.
  final Map<Guard, GuardView> guards;
}

/// Identity helper for type-checking a plugin definition at compile time.
///
/// Dart has no runtime module loading: a plugin is a package added as a path
/// dependency and registered in `main.dart`. `definePlugin` is the single
/// place that shapes it, mirroring the web `definePlugin`.
PluginDefinition definePlugin(PluginDefinition definition) => definition;
