import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/account_api.dart';
import '../api/envelope.dart';
import '../providers.dart';

/// Account screen: profile edition, email verification, password and disable.
class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({super.key});

  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _currentPassword = TextEditingController();
  final _newPassword = TextEditingController();
  final _confirmation = TextEditingController();
  bool _savingProfile = false;
  bool _savingPassword = false;
  bool _loadingPasskeys = true;
  bool _passkeyBusy = false;
  List<Passkey> _passkeys = const [];

  @override
  void initState() {
    super.initState();
    final user = ref.read(authProvider).user;
    _name.text = user?.name ?? '';
    _email.text = user?.email ?? '';
    _loadPasskeys();
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _currentPassword.dispose();
    _newPassword.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  void _show(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _saveProfile() async {
    setState(() => _savingProfile = true);

    try {
      await ref.read(accountApiProvider).updateProfile(name: _name.text.trim(), email: _email.text.trim());
      await ref.read(authProvider.notifier).refreshUser();
      _show('Perfil actualizado.');
    } catch (error) {
      _show(error is ApiError ? error.message : 'No se pudo actualizar el perfil.');
    } finally {
      if (mounted) setState(() => _savingProfile = false);
    }
  }

  Future<void> _savePassword() async {
    if (_newPassword.text != _confirmation.text) {
      _show('Las contraseñas no coinciden.');
      return;
    }

    setState(() => _savingPassword = true);

    try {
      await ref.read(accountApiProvider).changePassword(
            currentPassword: _currentPassword.text,
            password: _newPassword.text,
            passwordConfirmation: _confirmation.text,
          );
      _currentPassword.clear();
      _newPassword.clear();
      _confirmation.clear();
      _show('Contraseña actualizada.');
    } catch (error) {
      _show(error is ApiError ? error.message : 'No se pudo cambiar la contraseña.');
    } finally {
      if (mounted) setState(() => _savingPassword = false);
    }
  }

  Future<void> _loadPasskeys() async {
    try {
      final passkeys = await ref.read(passkeyServiceProvider).list();
      if (mounted) setState(() => _passkeys = passkeys);
    } catch (error) {
      _show(error is ApiError ? error.message : 'No se pudieron cargar las passkeys.');
    } finally {
      if (mounted) setState(() => _loadingPasskeys = false);
    }
  }

  Future<void> _addPasskey() async {
    setState(() => _passkeyBusy = true);

    try {
      await ref.read(passkeyServiceProvider).register(name: 'Mobile');
      await _loadPasskeys();
      _show('Passkey añadida.');
    } catch (error) {
      _show(error is ApiError ? error.message : 'No se pudo registrar la passkey.');
    } finally {
      if (mounted) setState(() => _passkeyBusy = false);
    }
  }

  Future<void> _removePasskey(Passkey passkey) async {
    try {
      await ref.read(passkeyServiceProvider).remove(passkey.id);
      await _loadPasskeys();
      _show('Passkey eliminada.');
    } catch (error) {
      _show(error is ApiError ? error.message : 'No se pudo eliminar la passkey.');
    }
  }

  Future<void> _resendVerification() async {
    try {
      await ref.read(accountApiProvider).resendVerification();
      _show('Te enviamos un nuevo enlace de verificación.');
    } catch (error) {
      _show(error is ApiError ? error.message : 'No se pudo enviar el enlace.');
    }
  }

  Future<void> _disable() async {
    final password = await _askPassword();
    if (password == null || password.isEmpty) return;

    try {
      await ref.read(accountApiProvider).disable(currentPassword: password);
      await ref.read(authProvider.notifier).logout();
    } catch (error) {
      _show(error is ApiError ? error.message : 'No se pudo deshabilitar la cuenta.');
    }
  }

  Future<String?> _askPassword() {
    final controller = TextEditingController();

    return showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Deshabilitar cuenta'),
        content: TextField(
          controller: controller,
          obscureText: true,
          autofocus: true,
          decoration: const InputDecoration(labelText: 'Contraseña actual'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancelar')),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text),
            child: const Text('Deshabilitar'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final user = auth.user;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Center(child: CircleAvatar(radius: 36, child: Icon(Icons.person, size: 36))),
        const SizedBox(height: 16),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(user?.email ?? '—'),
            const SizedBox(width: 8),
            Chip(
              label: Text(user?.emailVerified == true ? 'Verificado' : 'Sin verificar'),
              visualDensity: VisualDensity.compact,
            ),
          ],
        ),
        if (user?.emailVerified != true)
          TextButton(
            onPressed: _resendVerification,
            child: const Text('Reenviar verificación'),
          ),
        const SizedBox(height: 16),
        Text('Cuenta', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        TextField(
          controller: _name,
          decoration: const InputDecoration(labelText: 'Nombre', border: OutlineInputBorder()),
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder()),
        ),
        const SizedBox(height: 12),
        FilledButton(
          onPressed: _savingProfile ? null : _saveProfile,
          child: Text(_savingProfile ? 'Guardando…' : 'Guardar perfil'),
        ),
        const Divider(height: 40),
        Text('Contraseña', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        TextField(
          controller: _currentPassword,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'Contraseña actual', border: OutlineInputBorder()),
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _newPassword,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'Nueva contraseña', border: OutlineInputBorder()),
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _confirmation,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'Repetir contraseña', border: OutlineInputBorder()),
        ),
        const SizedBox(height: 12),
        FilledButton.tonal(
          onPressed: _savingPassword ? null : _savePassword,
          child: Text(_savingPassword ? 'Guardando…' : 'Cambiar contraseña'),
        ),
        const Divider(height: 40),
        Text('Passkeys', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        if (_loadingPasskeys)
          const Center(child: Padding(padding: EdgeInsets.all(8), child: CircularProgressIndicator()))
        else if (_passkeys.isEmpty)
          const Text('No hay passkeys registradas en este dispositivo.')
        else
          for (final passkey in _passkeys)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.fingerprint),
              title: Text(passkey.name ?? 'Passkey'),
              subtitle: passkey.createdAt == null ? null : Text(passkey.createdAt!),
              trailing: IconButton(
                tooltip: 'Eliminar',
                onPressed: () => _removePasskey(passkey),
                icon: const Icon(Icons.delete_outline),
              ),
            ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: _passkeyBusy ? null : _addPasskey,
          icon: const Icon(Icons.add),
          label: Text(_passkeyBusy ? 'Esperando passkey…' : 'Añadir passkey'),
        ),
        const Divider(height: 40),
        _Row(label: 'Guard', value: user?.guard ?? '—'),
        _Row(label: 'ID', value: user?.id ?? '—'),
        const SizedBox(height: 24),
        OutlinedButton.icon(
          onPressed: () => ref.read(authProvider.notifier).logout(),
          icon: const Icon(Icons.logout),
          label: const Text('Cerrar sesión'),
        ),
        const SizedBox(height: 8),
        TextButton(
          onPressed: _disable,
          style: TextButton.styleFrom(foregroundColor: Theme.of(context).colorScheme.error),
          child: const Text('Deshabilitar cuenta'),
        ),
      ],
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 96,
            child: Text(label, style: Theme.of(context).textTheme.labelLarge),
          ),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }
}
