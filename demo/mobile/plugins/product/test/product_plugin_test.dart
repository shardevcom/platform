import 'package:flutter_test/flutter_test.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';
import 'package:shardev_product_plugin/shardev_product_plugin.dart';

void main() {
  test('the plugin declares one view per guard', () {
    expect(productPlugin.name, 'product');
    expect(productPlugin.resource, 'products');
    expect(productPlugin.guards.keys, containsAll([Guard.web, Guard.employee, Guard.admin]));
  });

  test('every guard view uses the products path (matches plugin.json)', () {
    for (final view in productPlugin.guards.values) {
      expect(view.path, 'products');
      expect(view.nav, isNotNull);
    }
  });

  test('the registry resolves the plugin for each guard', () {
    for (final guard in Guard.values) {
      final registry = createRegistry([productPlugin], guard);
      expect(registry.views, hasLength(1));
      expect(registry.views.single.plugin, 'product');
    }
  });

  test('Product.fromJson maps the row', () {
    final product = Product.fromJson({
      'id': '7',
      'sku': 'SKU-7',
      'name': 'Anvil',
      'ean': '123',
      'brand_id': 3,
      'active': true,
      'brand': {'id': 3, 'name': 'ACME'},
    });

    expect(product.id, 7);
    expect(product.sku, 'SKU-7');
    expect(product.brandId, 3);
    expect(product.brand?.name, 'ACME');
    expect(product.active, isTrue);
  });

  test('ProductInput serializes to the REST shape', () {
    const input = ProductInput(sku: 'A', name: 'B', active: false);

    expect(input.toJson(), {'sku': 'A', 'name': 'B', 'ean': null, 'brand_id': null, 'active': false});
  });
}
