import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shardev_core/shardev_core.dart';

import 'product_endpoints.dart';
import 'product_models.dart';

/// Shared product list, gated by [ProductCapabilities].
///
/// The three guard views differ only in the capabilities they pass, exactly
/// like the web client: the resource logic lives here, once.
class ProductList extends ConsumerWidget {
  const ProductList({super.key, required this.capabilities, this.title = 'Productos'});

  final ProductCapabilities capabilities;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final products = ref.watch(productsProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Row(
            children: [
              Expanded(
                child: Text(title, style: Theme.of(context).textTheme.titleLarge),
              ),
              if (capabilities.create)
                FilledButton.icon(
                  onPressed: () => _openForm(context, ref, null),
                  icon: const Icon(Icons.add),
                  label: const Text('Nuevo'),
                ),
            ],
          ),
        ),
        Expanded(
          child: products.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, _) => _ErrorView(
              error: error,
              onRetry: () => ref.invalidate(productsProvider),
            ),
            data: (rows) {
              if (rows.isEmpty) {
                return const Center(child: Text('Sin productos.'));
              }

              return RefreshIndicator(
                onRefresh: () async => ref.invalidate(productsProvider),
                child: ListView.separated(
                  itemCount: rows.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final product = rows[index];
                    return ListTile(
                      leading: Icon(
                        product.active ? Icons.inventory_2_outlined : Icons.inventory_2,
                      ),
                      title: Text(product.name),
                      subtitle: Text(product.sku),
                      trailing: capabilities.remove
                          ? IconButton(
                              tooltip: 'Eliminar',
                              icon: const Icon(Icons.delete_outline),
                              onPressed: () => _confirmDelete(context, ref, product),
                            )
                          : null,
                      onTap: capabilities.edit ? () => _openForm(context, ref, product) : null,
                    );
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Future<void> _openForm(BuildContext context, WidgetRef ref, Product? existing) async {
    final input = await showDialog<ProductInput>(
      context: context,
      builder: (_) => _ProductFormDialog(product: existing),
    );

    if (input == null) {
      return;
    }

    try {
      final api = ref.read(productApiProvider);
      if (existing == null) {
        await api.create(input);
      } else {
        await api.update(existing.id, input);
      }
      ref.invalidate(productsProvider);
    } on ApiError catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _confirmDelete(BuildContext context, WidgetRef ref, Product product) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Eliminar producto'),
        content: Text('¿Eliminar «${product.name}»?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Eliminar'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await ref.read(productApiProvider).remove(product.id);
      ref.invalidate(productsProvider);
    } on ApiError catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }
}

class _ProductFormDialog extends StatefulWidget {
  const _ProductFormDialog({this.product});

  final Product? product;

  @override
  State<_ProductFormDialog> createState() => _ProductFormDialogState();
}

class _ProductFormDialogState extends State<_ProductFormDialog> {
  late final TextEditingController _sku;
  late final TextEditingController _name;
  late final TextEditingController _ean;
  late bool _active;

  @override
  void initState() {
    super.initState();
    _sku = TextEditingController(text: widget.product?.sku ?? '');
    _name = TextEditingController(text: widget.product?.name ?? '');
    _ean = TextEditingController(text: widget.product?.ean ?? '');
    _active = widget.product?.active ?? true;
  }

  @override
  void dispose() {
    _sku.dispose();
    _name.dispose();
    _ean.dispose();
    super.dispose();
  }

  void _submit() {
    Navigator.of(context).pop(
      ProductInput(
        sku: _sku.text.trim(),
        name: _name.text.trim(),
        ean: _ean.text.trim().isEmpty ? null : _ean.text.trim(),
        active: _active,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final editing = widget.product != null;

    return AlertDialog(
      title: Text(editing ? 'Editar producto' : 'Nuevo producto'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _sku,
              decoration: const InputDecoration(labelText: 'SKU'),
            ),
            TextField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'Nombre'),
            ),
            TextField(
              controller: _ean,
              decoration: const InputDecoration(labelText: 'EAN'),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Activo'),
              value: _active,
              onChanged: (value) => setState(() => _active = value),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancelar'),
        ),
        FilledButton(
          onPressed: _submit,
          child: Text(editing ? 'Guardar' : 'Crear'),
        ),
      ],
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.error, required this.onRetry});

  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final message = error is ApiError ? (error as ApiError).message : '$error';

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 40),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            OutlinedButton(onPressed: onRetry, child: const Text('Reintentar')),
          ],
        ),
      ),
    );
  }
}
