import 'package:flutter/widgets.dart';

import 'guard.dart';
import 'plugin_definition.dart';

/// A plugin view resolved for the active guard.
class ResolvedView {
  const ResolvedView({
    required this.plugin,
    required this.title,
    required this.path,
    required this.builder,
    this.nav,
  });

  final String plugin;
  final String title;
  final String path;
  final WidgetBuilder builder;
  final NavItem? nav;
}

/// The registry the app mounts: the views and nav entries a guard can see.
class PluginRegistry {
  const PluginRegistry({required this.views, required this.nav});

  final List<ResolvedView> views;
  final List<NavItem> nav;
}

/// Resolves [plugins] for [guard]: keeps only the views declared for that
/// guard and sorts their nav entries by [NavItem.order].
///
/// This is the single place mapping a plugin to a guard — neither the app nor
/// the plugin needs to know about the other's routing.
PluginRegistry createRegistry(List<PluginDefinition> plugins, Guard guard) {
  final views = <ResolvedView>[];

  for (final plugin in plugins) {
    final view = plugin.guards[guard];
    if (view == null) {
      continue;
    }

    views.add(
      ResolvedView(
        plugin: plugin.name,
        title: plugin.title,
        path: view.path,
        builder: view.builder,
        nav: view.nav,
      ),
    );
  }

  final nav = views
      .map((view) => view.nav)
      .whereType<NavItem>()
      .toList()
    ..sort((a, b) => a.order.compareTo(b.order));

  return PluginRegistry(views: views, nav: nav);
}
