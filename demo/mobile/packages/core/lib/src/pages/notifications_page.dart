import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/account_api.dart';
import '../api/envelope.dart';
import '../providers.dart';

/// The signed-in account's notification inbox.
class NotificationsPage extends ConsumerStatefulWidget {
  const NotificationsPage({super.key});

  @override
  ConsumerState<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends ConsumerState<NotificationsPage> {
  NotificationsFeed? _feed;
  String? _error;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);

    try {
      final feed = await ref.read(accountApiProvider).notifications();
      if (mounted) setState(() => _feed = feed);
    } catch (error) {
      if (mounted) setState(() => _error = error is ApiError ? error.message : 'No se pudieron cargar las notificaciones.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _act(Future<void> Function() action) async {
    try {
      await action();
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = error is ApiError ? error.message : 'No se pudo completar la acción.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final feed = _feed;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                feed == null ? 'Notificaciones' : 'Notificaciones (${feed.unreadCount})',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
            ),
            TextButton(
              onPressed: feed == null || feed.unreadCount == 0
                  ? null
                  : () => _act(ref.read(accountApiProvider).readAllNotifications),
              child: const Text('Marcar todas'),
            ),
          ],
        ),
        if (_loading) const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator())),
        if (_error != null) Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        if (feed != null && feed.items.isEmpty)
          const Padding(padding: EdgeInsets.all(24), child: Text('No tienes notificaciones.')),
        for (final notification in feed?.items ?? const <AccountNotification>[])
          Card(
            child: ListTile(
              leading: Icon(notification.read ? Icons.drafts_outlined : Icons.mark_email_unread_outlined),
              title: Text(notification.type),
              subtitle: Text(notification.createdAt ?? ''),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (!notification.read)
                    IconButton(
                      tooltip: 'Marcar leída',
                      icon: const Icon(Icons.done),
                      onPressed: () => _act(() => ref.read(accountApiProvider).readNotification(notification.id)),
                    ),
                  IconButton(
                    tooltip: 'Eliminar',
                    icon: const Icon(Icons.delete_outline),
                    onPressed: () => _act(() => ref.read(accountApiProvider).deleteNotification(notification.id)),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
