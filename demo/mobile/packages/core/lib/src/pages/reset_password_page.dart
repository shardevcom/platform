import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../api/envelope.dart';
import '../providers.dart';

/// Chooses a new password from the link's `token`/`email` query.
class ResetPasswordPage extends ConsumerStatefulWidget {
  const ResetPasswordPage({super.key, required this.token, this.email = ''});

  final String token;
  final String email;

  @override
  ConsumerState<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends ConsumerState<ResetPasswordPage> {
  late final TextEditingController _email;
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _email = TextEditingController(text: widget.email);
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_password.text != _confirmation.text) {
      _showError('Las contraseñas no coinciden.');
      return;
    }

    setState(() => _submitting = true);

    try {
      await ref.read(accountApiProvider).resetPassword(
            token: widget.token,
            email: _email.text.trim(),
            password: _password.text,
            passwordConfirmation: _confirmation.text,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Contraseña actualizada.')),
      );
      context.go('/login');
    } catch (error) {
      _showError(error is ApiError ? error.message : 'El enlace no es válido o caducó.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Nueva contraseña')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            if (widget.token.isEmpty)
              const Text('Falta el token de recuperación.')
            else ...[
              TextField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _password,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Contraseña', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _confirmation,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Repetir contraseña', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: Text(_submitting ? 'Guardando…' : 'Guardar contraseña'),
              ),
            ],
            const SizedBox(height: 8),
            TextButton(
              onPressed: () => context.go('/login'),
              child: const Text('Volver al login'),
            ),
          ],
        ),
      ),
    );
  }
}
