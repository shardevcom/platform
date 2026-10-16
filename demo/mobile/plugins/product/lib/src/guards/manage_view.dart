import 'package:flutter/material.dart';

import '../product_list.dart';
import '../product_models.dart';

/// `employee` guard: create and edit, no removal.
class ProductManageView extends StatelessWidget {
  const ProductManageView({super.key});

  @override
  Widget build(BuildContext context) => const ProductList(
        title: 'Productos',
        capabilities: ProductCapabilities.manage,
      );
}
