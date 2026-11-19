import 'package:flutter/foundation.dart';
import 'package:jewelry_hub/core/notifications/models/app_notification_item.dart';

/// Unread count for customer bottom-nav Alerts indicator.
class CustomerNotificationBadge extends ChangeNotifier {
  int _unread = 0;

  int get unreadCount => _unread;

  bool get hasUnread => _unread > 0;

  void syncFromNotifications(List<AppNotificationItem> items) {
    final n = items.where((e) => !e.isRead).length;
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
