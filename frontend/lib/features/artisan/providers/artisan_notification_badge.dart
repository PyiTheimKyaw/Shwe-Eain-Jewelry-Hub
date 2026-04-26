import 'package:flutter/foundation.dart';
import 'package:jewelry_hub/features/artisan/models/artisan_notification.dart';

/// Unread count for artisan bottom-nav Alerts indicator.
class ArtisanNotificationBadge extends ChangeNotifier {
  int _unread = 0;

  int get unreadCount => _unread;

  bool get hasUnread => _unread > 0;

  void syncFromNotifications(List<ArtisanNotificationItem> items) {
    final n = items.where((e) => e.readAt == null).length;
    if (n == _unread) return;
    _unread = n;
    notifyListeners();
  }

  void clear() {
    if (_unread == 0) return;
    _unread = 0;
    notifyListeners();
  }
}
