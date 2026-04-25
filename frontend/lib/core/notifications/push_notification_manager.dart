import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/notifications/data/notification_api.dart';
import 'package:jewelry_hub/core/notifications/models/app_notification_item.dart';
import 'package:jewelry_hub/features/artisan/ui/artisan_notifications_screen.dart';
import 'package:jewelry_hub/features/inquiries/ui/inquiry_list_screen.dart';
import 'package:jewelry_hub/features/orders/ui/customer_order_detail_screen.dart';
import 'package:jewelry_hub/features/orders/ui/customer_orders_screen.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {
    // Ignore; app may already be initialized in background isolate.
  }
}

class PushNotificationManager {
  PushNotificationManager._();
  static final PushNotificationManager instance = PushNotificationManager._();

  bool _initialized = false;
  String? _lastFcmToken;

  Future<void> initialize(
    GlobalKey<NavigatorState> navigatorKey,
    GlobalKey<ScaffoldMessengerState> scaffoldMessengerKey,
  ) async {
    if (_initialized) return;
    _initialized = true;

    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

    try {
      await FirebaseMessaging.instance.requestPermission();
    } catch (_) {
      // Best-effort only.
    }

    FirebaseMessaging.onMessage.listen((message) {
      final title = message.notification?.title ?? 'Notification';
      final body = message.notification?.body ?? 'You have a new update.';
      scaffoldMessengerKey.currentState?.showSnackBar(
        SnackBar(content: Text('$title — $body')),
      );
    });

    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      _handleMessageNavigation(message, navigatorKey);
    });

    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) {
      _handleMessageNavigation(initial, navigatorKey);
    }
  }

  Future<void> registerForSession({
    required String bearerToken,
    required String role,
  }) async {
    final token = await FirebaseMessaging.instance.getToken();
    if (token == null || token.isEmpty) return;
    _lastFcmToken = token;

    await NotificationApi.registerDevice(
      token: bearerToken,
      fcmToken: token,
      platform: role,
    );
  }

  Future<void> unregisterForSession({required String bearerToken}) async {
    final token = _lastFcmToken ?? await FirebaseMessaging.instance.getToken();
    if (token == null || token.isEmpty) return;
    await NotificationApi.unregisterDevice(token: bearerToken, fcmToken: token);
  }

  void openNotification(
    AppNotificationItem item,
    GlobalKey<NavigatorState> navigatorKey,
    String role,
  ) {
    if (role == 'artisan') {
      navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (_) =>
              ArtisanNotificationsScreen(initialOrderId: item.orderId),
        ),
      );
      return;
    }

    if (item.orderId != null) {
      navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (_) => CustomerOrderDetailScreen(
            orderId: item.orderId!,
            orderNumber: item.orderNumber ?? '#${item.orderId}',
          ),
        ),
      );
      return;
    }

    if (item.inquiryId != null) {
      navigatorKey.currentState?.push(
        MaterialPageRoute(builder: (_) => const InquiryListScreen()),
      );
      return;
    }

    navigatorKey.currentState?.push(
      MaterialPageRoute(builder: (_) => const CustomerOrdersScreen()),
    );
  }

  void _handleMessageNavigation(
    RemoteMessage message,
    GlobalKey<NavigatorState> navigatorKey,
  ) {
    final data = Map<String, dynamic>.from(message.data);
    final item = AppNotificationItem(
      id: data['notification_id']?.toString() ?? 'push',
      type: data['category']?.toString() ?? 'push',
      data: data,
    );

    // Default fallback: customer-style deep links.
    openNotification(item, navigatorKey, 'customer');
  }
}
