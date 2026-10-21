import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../config.dart';

/// Confirms the signed `verify_url` embedded in the verification email.
class VerifyEmailPage extends StatefulWidget {
  const VerifyEmailPage({super.key, this.verifyUrl = ''});

  final String verifyUrl;

  @override
  State<VerifyEmailPage> createState() => _VerifyEmailPageState();
}

class _VerifyEmailPageState extends State<VerifyEmailPage> {
  String? _error;
  bool _done = false;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    if (widget.verifyUrl.isNotEmpty) {
      _verify();
    }
  }

  Future<void> _verify() async {
    setState(() => _loading = true);

    final uri = Uri.tryParse(widget.verifyUrl);
    final target = uri == null || !uri.hasScheme
        ? '${PlatformConfig.host}$verifyPath'
        : uri.toString();

    try {
      await Dio().get<Object?>(
        target,
        options: Options(headers: {'Accept': 'application/json'}),
      );
      if (mounted) setState(() => _done = true);
    } on DioException catch (error) {
      final data = error.response?.data;
      final message = data is Map ? data['message']?.toString() : null;
      if (mounted) {
        setState(() => _error = message ?? 'El enlace no es válido o caducó.');
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'El enlace no es válido o caducó.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Path of [verifyUrl] without host, for the relative fallback.
  String get verifyPath {
    final uri = Uri.tryParse(widget.verifyUrl);
    if (uri == null) return widget.verifyUrl;
    return '${uri.path}${uri.hasQuery ? '?${uri.query}' : ''}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Verificación de email')),
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (widget.verifyUrl.isEmpty)
                  const Text('Falta el enlace de verificación.')
                else if (_loading)
                  const CircularProgressIndicator()
                else if (_done)
                  const Text('Tu email quedó verificado.')
                else
                  Text(_error ?? 'No se pudo verificar el email.'),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: () => context.go('/login'),
                  child: const Text('Ir al login'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
