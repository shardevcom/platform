import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shardev_core/shardev_core.dart';
import 'package:shardev_product_plugin/shardev_product_plugin.dart';

import 'app.dart';

/// Entry point. The active guard comes from `--dart-define=GUARD=...` (one
/// flavor per guard); plugins are registered here — adding a plugin is a path
/// dependency plus one entry in this list.
void main() {
  runApp(
    ProviderScope(
      overrides: [
        pluginsProvider.overrideWithValue([productPlugin]),
      ],
      child: const ShardevApp(),
    ),
  );
}
