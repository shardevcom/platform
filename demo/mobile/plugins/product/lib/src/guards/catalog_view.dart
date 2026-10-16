import 'package:flutter/material.dart';

import '../product_list.dart';
import '../product_models.dart';

/// `web` guard: read-only catalog.
class ProductCatalogView extends StatelessWidget {
  const ProductCatalogView({super.key});

  @override
  Widget build(BuildContext context) => const ProductList(
        title: 'Catálogo',
        capabilities: ProductCapabilities.readOnly,
      );
}
