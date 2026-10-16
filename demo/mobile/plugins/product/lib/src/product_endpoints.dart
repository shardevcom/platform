import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shardev_core/shardev_core.dart';

import 'product_models.dart';

/// Product REST endpoints. They live with the plugin (not in core) because the
/// resource belongs to the plugin; core only provides the transport.
class ProductApi {
  const ProductApi(this._client);

  final ApiClient _client;

  Future<List<Product>> list({Map<String, Object?>? query}) async {
    final rows = await _client.data<List<dynamic>>('products', query: query);
    return rows
        .map((row) => Product.fromJson(Map<String, dynamic>.from(row as Map)))
        .toList();
  }

  Future<Product> get(int id) async {
    final row = await _client.data<Map<String, dynamic>>('products/$id');
    return Product.fromJson(row);
  }

  Future<Product> create(ProductInput input) async {
    final row = await _client.data<Map<String, dynamic>>(
      'products',
      method: 'POST',
      body: input.toJson(),
    );
    return Product.fromJson(row);
  }

  Future<Product> update(int id, ProductInput input) async {
    final row = await _client.data<Map<String, dynamic>>(
      'products/$id',
      method: 'PUT',
      body: input.toJson(),
    );
    return Product.fromJson(row);
  }

  Future<void> remove(int id) async {
    await _client.data<Object?>('products/$id', method: 'DELETE');
  }
}

final productApiProvider = Provider<ProductApi>(
  (ref) => ProductApi(ref.watch(apiClientProvider)),
);

/// The current product list. Invalidate it after any write.
final productsProvider = FutureProvider.autoDispose<List<Product>>(
  (ref) => ref.watch(productApiProvider).list(),
);
