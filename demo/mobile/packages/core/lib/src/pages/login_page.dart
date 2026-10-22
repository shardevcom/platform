import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

import '../api/envelope.dart';
import '../auth/two_factor.dart';
import '../config.dart';
import '../providers.dart';
import '../social/social_provider.dart';
import '../social/social_service.dart';

/// Sign-in mode on the login screen.
enum LoginMode { password, code }

/// Login against the build's guard. The token issued here is only valid for
/// this platform, so the screen never asks which platform to use.
class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  late final TextEditingController _email;
  final _password = TextEditingController();
  final _code = TextEditingController();
  final _twoFactorCode = TextEditingController();
  LoginMode _mode = LoginMode.password;
  bool _useRecoveryCode = false;
  bool _twoFactorBusy = false;
  bool _submitting = false;
  bool _sendingCode = false;
  bool _passkeyBusy = false;
  String? _socialBusy;
  List<SocialProvider> _providers = const [];

  @override
  void initState() {
    super.initState();
    _email = TextEditingController(text: _demoEmail(ref.read(guardProvider)));
    _password.text = 'password';
    Future.microtask(_loadSocialProviders);
  }

  Future<void> _loadSocialProviders() async {
    if (ref.read(guardProvider) != Guard.web) {
      return;
    }

    try {
      final providers = await ref.read(socialServiceProvider).providers();
      if (mounted) {
        setState(() => _providers = providers);
      }
    } catch (_) {
      // Social login is optional: a disabled provider must not block login.
    }
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _code.dispose();
    _twoFactorCode.dispose();
    super.dispose();
  }

  static String _demoEmail(Guard guard) {
    switch (guard) {
      case Guard.web:
        return 'user@example.com';
      case Guard.admin:
        return 'admin@example.com';
      case Guard.employee:
        return 'employee@example.com';
    }
  }

  void _showError(Object error) {
    final message = switch (error) {
      ApiError() => error.message,
      SocialAuthException() => error.message,
      _ => 'No se pudo completar la operación: $error',
    };
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);

    try {
      if (_mode == LoginMode.password) {
        await ref
            .read(authProvider.notifier)
            .login(_email.text.trim(), _password.text);
      } else {
        await ref
            .read(authProvider.notifier)
            .loginWithOtp(_email.text.trim(), _code.text.trim());
      }
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  Future<void> _passkeyLogin() async {
    setState(() => _passkeyBusy = true);

    try {
      await ref
          .read(authProvider.notifier)
          .loginWithPasskey(email: _email.text.trim());
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) {
        setState(() => _passkeyBusy = false);
      }
    }
  }

  Future<void> _socialLogin(SocialProvider provider) async {
    setState(() => _socialBusy = provider.key);

    try {
      await ref.read(authProvider.notifier).loginWithSocial(provider.key);
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) {
        setState(() => _socialBusy = null);
      }
    }
  }

  Future<void> _sendCode() async {
    setState(() => _sendingCode = true);

    try {
      await ref.read(accountApiProvider).requestOtp(_email.text.trim());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Si la cuenta existe, te enviamos un código de acceso.',
          ),
        ),
      );
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) {
        setState(() => _sendingCode = false);
      }
    }
  }

  Future<void> _submitTwoFactor(PendingTwoFactor pending) async {
    final code = _twoFactorCode.text.trim();

    if (code.isEmpty) {
      return;
    }

    setState(() => _twoFactorBusy = true);

    try {
      final auth = ref.read(authProvider.notifier);
      if (pending.enrollment) {
        await auth.confirmTwoFactorSetup(code);
      } else {
        await auth.verifyTwoFactor(
          code,
          type: _useRecoveryCode ? 'recovery_code' : 'totp',
        );
      }
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) {
        setState(() => _twoFactorBusy = false);
      }
    }
  }

  Widget _twoFactorPanel(PendingTwoFactor pending) {
    final setup = pending.setup;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          pending.enrollment
              ? 'Configura tu segundo factor'
              : 'Verificación en dos pasos',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 8),
        Text(
          pending.enrollment
              ? 'Añade esta clave a tu app de autenticación y guarda los recovery codes.'
              : 'Introduce el código de tu app de autenticación.',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        if (pending.enrollment && setup != null) ...[
          const SizedBox(height: 16),
          SelectableText(
            setup.secret,
            textAlign: TextAlign.center,
            style: const TextStyle(fontFamily: 'monospace', fontSize: 16),
          ),
          const SizedBox(height: 12),
          Text('Recovery codes', style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: 4),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              for (final code in setup.recoveryCodes)
                Chip(
                  label: Text(
                    code,
                    style: const TextStyle(fontFamily: 'monospace'),
                  ),
                ),
            ],
          ),
        ],
        const SizedBox(height: 20),
        TextField(
          controller: _twoFactorCode,
          keyboardType: _useRecoveryCode
              ? TextInputType.text
              : TextInputType.number,
          autofillHints: const [AutofillHints.oneTimeCode],
          onSubmitted: (_) => _twoFactorBusy ? null : _submitTwoFactor(pending),
          decoration: InputDecoration(
            labelText: _useRecoveryCode ? 'Recovery code' : 'Código TOTP',
            border: const OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 16),
        FilledButton(
          onPressed: _twoFactorBusy ? null : () => _submitTwoFactor(pending),
          child: _twoFactorBusy
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(
                  pending.enrollment
                      ? 'Activar y entrar'
                      : 'Verificar y entrar',
                ),
        ),
        if (!pending.enrollment)
          TextButton(
            onPressed: () => setState(() {
              _useRecoveryCode = !_useRecoveryCode;
              _twoFactorCode.clear();
            }),
            child: Text(
              _useRecoveryCode ? 'Usar código TOTP' : 'Usar un recovery code',
            ),
          ),
        TextButton(
          onPressed: () {
            _twoFactorCode.clear();
            setState(() => _useRecoveryCode = false);
            ref.read(authProvider.notifier).cancelTwoFactor();
          },
          child: const Text('Cancelar'),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final guard = ref.watch(guardProvider);
    final pending = ref.watch(authProvider).pendingTwoFactor;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Icon(
                    Icons.phone_android,
                    size: 56,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(height: 16),
                  Text(
                    PlatformConfig.appName,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  Text(
                    'Plataforma ${guard.label}',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 24),
                  if (pending != null) ...[
                    _twoFactorPanel(pending),
                  ] else ...[
                    SegmentedButton<LoginMode>(
                      segments: const [
                        ButtonSegment(
                          value: LoginMode.password,
                          label: Text('Contraseña'),
                        ),
                        ButtonSegment(
                          value: LoginMode.code,
                          label: Text('Código'),
                        ),
                      ],
                      selected: {_mode},
                      onSelectionChanged: (selection) =>
                          setState(() => _mode = selection.first),
                    ),
                    const SizedBox(height: 24),
                    TextField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.email],
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (_mode == LoginMode.password)
                      TextField(
                        controller: _password,
                        obscureText: true,
                        autofillHints: const [AutofillHints.password],
                        onSubmitted: (_) => _submitting ? null : _submit(),
                        decoration: const InputDecoration(
                          labelText: 'Contraseña',
                          border: OutlineInputBorder(),
                        ),
                      )
                    else
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: TextField(
                              controller: _code,
                              keyboardType: TextInputType.number,
                              autofillHints: const [AutofillHints.oneTimeCode],
                              decoration: const InputDecoration(
                                labelText: 'Código',
                                border: OutlineInputBorder(),
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: OutlinedButton(
                              onPressed: _sendingCode ? null : _sendCode,
                              child: Text(_sendingCode ? '...' : 'Enviar'),
                            ),
                          ),
                        ],
                      ),
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _submitting ? null : _submit,
                      child: _submitting
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(
                              _mode == LoginMode.password
                                  ? 'Entrar'
                                  : 'Entrar con código',
                            ),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: _passkeyBusy ? null : _passkeyLogin,
                      icon: const Icon(Icons.fingerprint),
                      label: Text(
                        _passkeyBusy
                            ? 'Esperando passkey…'
                            : 'Entrar con passkey',
                      ),
                    ),
                    if (_providers.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      for (final provider in _providers) ...[
                        OutlinedButton.icon(
                          onPressed: _socialBusy == null
                              ? () => _socialLogin(provider)
                              : null,
                          icon: const Icon(Icons.login),
                          label: Text(
                            _socialBusy == provider.key
                                ? 'Esperando al proveedor…'
                                : 'Continuar con ${provider.name}',
                          ),
                        ),
                        const SizedBox(height: 8),
                      ],
                    ],
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: () => context.go('/forgot-password'),
                      child: const Text('¿Olvidaste tu contraseña?'),
                    ),
                    if (PlatformConfig.isPublic)
                      TextButton(
                        onPressed: () => context.go('/register'),
                        child: const Text('Crear una cuenta'),
                      ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
