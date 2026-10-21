import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/account_api.dart';
import '../api/envelope.dart';
import '../providers.dart';

/// Interface and notification preferences stored per account.
class PreferencesPage extends ConsumerStatefulWidget {
  const PreferencesPage({super.key});

  @override
  ConsumerState<PreferencesPage> createState() => _PreferencesPageState();
}

class _PreferencesPageState extends ConsumerState<PreferencesPage> {
  final _language = TextEditingController();
  Preferences? _preferences;
  String? _error;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _language.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final preferences = await ref.read(accountApiProvider).preferences();
      if (!mounted) return;
      setState(() {
        _preferences = preferences;
        _language.text = preferences.language;
      });
    } catch (error) {
      if (mounted) setState(() => _error = error is ApiError ? error.message : 'No se pudieron cargar las preferencias.');
    }
  }

  Future<void> _save() async {
    final current = _preferences;
    if (current == null) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final saved = await ref.read(accountApiProvider).savePreferences(
            current.copyWith(language: _language.text.trim()),
          );
      if (!mounted) return;
      setState(() => _preferences = saved);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Preferencias guardadas.')));
    } catch (error) {
      if (mounted) setState(() => _error = error is ApiError ? error.message : 'No se pudieron guardar las preferencias.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final preferences = _preferences;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Preferencias', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 16),
        if (preferences == null)
          const Center(child: CircularProgressIndicator())
        else ...[
          DropdownButtonFormField<String>(
            initialValue: preferences.theme,
            decoration: const InputDecoration(labelText: 'Tema', border: OutlineInputBorder()),
            items: const [
              DropdownMenuItem(value: 'system', child: Text('Sistema')),
              DropdownMenuItem(value: 'light', child: Text('Claro')),
              DropdownMenuItem(value: 'dark', child: Text('Oscuro')),
            ],
            onChanged: (value) {
              if (value != null) setState(() => _preferences = preferences.copyWith(theme: value));
            },
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _language,
            decoration: const InputDecoration(labelText: 'Idioma', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 24),
          Text('Notificaciones', style: Theme.of(context).textTheme.titleMedium),
          for (final entry in preferences.notifications.entries)
            SwitchListTile(
              title: Text(entry.key),
              value: entry.value,
              onChanged: (value) => setState(
                () => _preferences = _preferences!.copyWith(
                  notifications: {..._preferences!.notifications, entry.key: value},
                ),
              ),
            ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          ],
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: Text(_saving ? 'Guardando…' : 'Guardar preferencias'),
          ),
        ],
      ],
    );
  }
}
