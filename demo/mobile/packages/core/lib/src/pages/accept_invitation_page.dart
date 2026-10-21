import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../api/account_api.dart';
import '../api/envelope.dart';
import '../providers.dart';

/// Accepts an invitation: the guest picks a name and password and is signed in.
class AcceptInvitationPage extends ConsumerStatefulWidget {
  const AcceptInvitationPage({super.key, this.token = ''});

  final String token;

  @override
  ConsumerState<AcceptInvitationPage> createState() => _AcceptInvitationPageState();
}

class _AcceptInvitationPageState extends ConsumerState<AcceptInvitationPage> {
  final _name = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  InvitationSummary? _invitation;
  String? _error;
  bool _loading = false;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    if (widget.token.isNotEmpty) {
      _load();
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);

    try {
      final invitation = await ref.read(accountApiProvider).invitation(widget.token);
      if (mounted) setState(() => _invitation = invitation);
    } catch (error) {
      if (mounted) {
        setState(() => _error = error is ApiError ? error.message : 'La invitación no es válida o caducó.');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    if (_password.text != _confirmation.text) {
      setState(() => _error = 'Las contraseñas no coinciden.');
      return;
    }

    setState(() => _submitting = true);

    try {
      await ref.read(authProvider.notifier).acceptInvitation(
            token: widget.token,
            name: _name.text.trim(),
            password: _password.text,
            passwordConfirmation: _confirmation.text,
          );
    } catch (error) {
      if (mounted) {
        setState(() => _error = error is ApiError ? error.message : 'No se pudo aceptar la invitación.');
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Aceptar invitación')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            if (widget.token.isEmpty)
              const Text('Falta el token de la invitación.')
            else if (_loading)
              const Center(child: CircularProgressIndicator())
            else if (_invitation == null)
              Text(_error ?? 'La invitación no es válida o caducó.')
            else ...[
              Text('Te unes como ${_invitation!.email}'),
              const SizedBox(height: 16),
              TextField(
                controller: _name,
                decoration: const InputDecoration(labelText: 'Nombre', border: OutlineInputBorder()),
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
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ],
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: Text(_submitting ? 'Activando…' : 'Crear cuenta y entrar'),
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
