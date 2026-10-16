/// Product domain models, mirroring the REST adapter's rows.
class Brand {
  const Brand({required this.id, required this.name});

  final int id;
  final String name;

  factory Brand.fromJson(Map<String, dynamic> json) => Brand(
        id: _asInt(json['id']),
        name: json['name']?.toString() ?? '',
      );
}

class Product {
  const Product({
    required this.id,
    required this.sku,
    required this.name,
    this.ean,
    this.brandId,
    this.active = true,
    this.brand,
  });

  final int id;
  final String sku;
  final String name;
  final String? ean;
  final int? brandId;
  final bool active;
  final Brand? brand;

  factory Product.fromJson(Map<String, dynamic> json) => Product(
        id: _asInt(json['id']),
        sku: json['sku']?.toString() ?? '',
        name: json['name']?.toString() ?? '',
        ean: json['ean']?.toString(),
        brandId: json['brand_id'] == null ? null : _asInt(json['brand_id']),
        active: json['active'] == null ? true : json['active'] == true,
        brand: json['brand'] is Map ? Brand.fromJson(Map<String, dynamic>.from(json['brand'] as Map)) : null,
      );
}

/// Payload for create/update.
class ProductInput {
  const ProductInput({
    required this.sku,
    required this.name,
    this.ean,
    this.brandId,
    this.active = true,
  });

  final String sku;
  final String name;
  final String? ean;
  final int? brandId;
  final bool active;

  Map<String, Object?> toJson() => {
        'sku': sku,
        'name': name,
        'ean': ean,
        'brand_id': brandId,
        'active': active,
      };
}

/// What a guard's product view is allowed to do (mirrors the token abilities).
class ProductCapabilities {
  const ProductCapabilities({
    this.create = false,
    this.edit = false,
    this.remove = false,
    this.transfer = false,
  });

  final bool create;
  final bool edit;
  final bool remove;
  final bool transfer;

  static const readOnly = ProductCapabilities();
  static const manage = ProductCapabilities(create: true, edit: true);
  static const full = ProductCapabilities(create: true, edit: true, remove: true, transfer: true);
}

int _asInt(Object? value) => int.tryParse(value?.toString() ?? '') ?? 0;
