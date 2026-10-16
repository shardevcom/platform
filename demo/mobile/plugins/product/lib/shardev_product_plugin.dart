/// Product plugin for the Shardev mobile client.
///
/// One view per guard, mirroring the API package's `plugin.json` and the web
/// `productPlugin`: `web` is read-only, `employee` can write, `admin` is full.
library;

export 'src/product_models.dart';
export 'src/product_endpoints.dart';
export 'src/product_list.dart';
export 'src/product_plugin.dart';
