import 'package:flutter_test/flutter_test.dart';
import 'package:jewelry_hub/features/artisan/models/artisan_notification.dart';
import 'package:jewelry_hub/features/artisan/providers/artisan_notification_badge.dart';

void main() {
  test('syncFromNotifications updates unread count', () {
    final badge = ArtisanNotificationBadge();
    final items = <ArtisanNotificationItem>[
      ArtisanNotificationItem(
        id: '1',
        type: 'artisan.assignment',
        data: const <String, dynamic>{},
        readAt: null,
      ),
      ArtisanNotificationItem(
        id: '2',
        type: 'artisan.assignment',
        data: const <String, dynamic>{},
        readAt: DateTime.now(),
      ),
      ArtisanNotificationItem(
        id: '3',
        type: 'artisan.assignment',
        data: const <String, dynamic>{},
        readAt: null,
      ),
    ];

    badge.syncFromNotifications(items);

    expect(badge.unreadCount, 2);
    expect(badge.hasUnread, isTrue);
  });

  test('clear resets unread count to zero', () {
    final badge = ArtisanNotificationBadge();
    badge.syncFromNotifications([
      const ArtisanNotificationItem(
        id: '1',
        type: 'artisan.assignment',
        data: <String, dynamic>{},
      ),
    ]);

    badge.clear();

    expect(badge.unreadCount, 0);
    expect(badge.hasUnread, isFalse);
  });
}
