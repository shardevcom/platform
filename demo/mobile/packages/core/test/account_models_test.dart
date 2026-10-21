import 'package:flutter_test/flutter_test.dart';
import 'package:shardev_core/shardev_core.dart';

void main() {
  test('AuthUser reads the email verification flag', () {
    final user = AuthUser.fromJson({
      'id': '1',
      'name': 'Ada',
      'email': 'ada@example.com',
      'guard': 'web',
      'email_verified': true,
      'abilities': ['*'],
    });

    expect(user.emailVerified, isTrue);
    expect(user.can('anything'), isTrue);
  });

  test('Preferences decodes the API shape and round-trips to JSON', () {
    final preferences = Preferences.fromJson({
      'theme': 'dark',
      'language': 'en',
      'notifications': {'email': true, 'push': false},
    });

    expect(preferences.theme, 'dark');
    expect(preferences.notifications['email'], isTrue);
    expect(preferences.notifications['push'], isFalse);
    expect(preferences.toJson(), {
      'theme': 'dark',
      'language': 'en',
      'notifications': {'email': true, 'push': false},
    });
    expect(preferences.copyWith(theme: 'light').theme, 'light');
  });

  test('NotificationsFeed decodes items and unread count', () {
    final feed = NotificationsFeed.fromJson({
      'unread_count': 1,
      'items': [
        {
          'id': 'n1',
          'type': 'invitation',
          'data': {'email': 'ada@example.com'},
          'read_at': null,
          'created_at': '2026-10-20T10:00:00Z',
        },
      ],
    });

    expect(feed.unreadCount, 1);
    expect(feed.items, hasLength(1));
    expect(feed.items.first.read, isFalse);
    expect(feed.items.first.data['email'], 'ada@example.com');
  });

  test('InvitationSummary decodes the invitation lookup', () {
    final invitation = InvitationSummary.fromJson({'email': 'ada@example.com', 'guard': 'web'});

    expect(invitation.email, 'ada@example.com');
    expect(invitation.guard, 'web');
  });
}
