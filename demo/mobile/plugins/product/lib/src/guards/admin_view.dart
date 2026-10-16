import 'package:flutter/material.dart';

import '../product_list.dart';
import '../product_models.dart';

/// `admin` guard: full CRUD (import/export stays on the web admin).
class ProductAdminView extends StatelessWidget {
  const ProductAdminView({super.key});

  @override
  Widget build(BuildContext context) => const ProductList(
        title: 'Products',
        capabilities: ProductCapabilities.full,
      );
}
