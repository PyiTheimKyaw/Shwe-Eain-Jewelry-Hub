import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:jewelry_hub/core/config/api_config.dart';
import 'package:jewelry_hub/core/notifications/models/app_notification_item.dart';

class NotificationApiException implements Exception {
  NotificationApiException(this.message);
  final String message;

  @override
  String toString() => message;
}

class NotificationApi {
  NotificationApi._();

  static Map<String, String> _authHeaders(String token) => {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  };

  static String _errorBody(String body) {
    try {
      final m = json.decode(body);
      if (m is Map && m['message'] is String) {
        return m['message'] as String;
      }
    } catch (_) {}
    return body;
  }

  static Future<void> registerDevice({
    required String token,
    required String fcmToken,
    required String platform,
    String? appVersion,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/push/devices');
    final payload = <String, dynamic>{
      'token': fcmToken,
      'platform': platform,
      'app_version': appVersion,
    }..removeWhere((_, value) => value == null);
    final res = await http.post(
      uri,
      headers: _authHeaders(token),
      body: json.encode(payload),
    );
    if (res.statusCode != 201) {
      throw NotificationApiException(
        'Push register ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
  }

  static Future<void> unregisterDevice({
    required String token,
    required String fcmToken,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/push/devices');
    final req = http.Request('DELETE', uri);
    req.headers.addAll(_authHeaders(token));
    req.body = json.encode({'token': fcmToken});
    final streamed = await req.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode != 200) {
      throw NotificationApiException(
        'Push unregister ${streamed.statusCode}: ${_errorBody(body)}',
      );
    }
  }

  static Future<List<AppNotificationItem>> fetchNotifications(
    String token,
  ) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/notifications');
    final res = await http.get(uri, headers: _authHeaders(token));
    if (res.statusCode != 200) {
      throw NotificationApiException(
        'Notifications ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    return list
        .map(
          (e) =>
              AppNotificationItem.fromJson(Map<String, dynamic>.from(e as Map)),
        )
        .toList();
  }

  static Future<void> markRead({
    required String token,
    required String notificationId,
  }) async {
    final uri = Uri.parse(
      '${ApiConfig.apiV1}/notifications/$notificationId/read',
    );
    final res = await http.post(uri, headers: _authHeaders(token));
    if (res.statusCode != 200) {
      throw NotificationApiException(
        'Mark read ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
  }
}
