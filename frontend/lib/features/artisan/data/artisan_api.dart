import 'dart:convert';
import 'dart:async';

import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:jewelry_hub/core/config/api_config.dart';
import 'package:jewelry_hub/features/artisan/models/artisan_notification.dart';

class ArtisanApiException implements Exception {
  ArtisanApiException(this.message);
  final String message;

  @override
  String toString() => message;
}

class ArtisanApi {
  ArtisanApi._();
  static const Duration _requestTimeout = Duration(seconds: 20);

  static Map<String, String> _authHeaders(String token) => {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
  };

  static Map<String, String> _jsonHeaders(String token) => {
    ..._authHeaders(token),
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

  static String _friendlyError(Object e) {
    if (e is TimeoutException) {
      return 'Request timed out. Please try again.';
    }
    return 'Network request failed. Please try again.';
  }

  static Future<List<Map<String, dynamic>>> fetchAssignments(
    String token,
  ) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/artisan/assignments');
    http.Response res;
    try {
      res = await http
          .get(uri, headers: _authHeaders(token))
          .timeout(_requestTimeout);
    } catch (e) {
      throw ArtisanApiException(_friendlyError(e));
    }
    if (res.statusCode != 200) {
      throw ArtisanApiException(
        'Assignments ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  static Future<List<ArtisanNotificationItem>> fetchNotifications(
    String token,
  ) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/artisan/notifications');
    http.Response res;
    try {
      res = await http
          .get(uri, headers: _authHeaders(token))
          .timeout(_requestTimeout);
    } catch (e) {
      throw ArtisanApiException(_friendlyError(e));
    }
    if (res.statusCode != 200) {
      throw ArtisanApiException(
        'Notifications ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    return list
        .map((e) => ArtisanNotificationItem.fromJson(
              Map<String, dynamic>.from(e as Map),
            ))
        .toList();
  }

  static Future<Map<String, int>> fetchStats(String token) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/artisan/stats');
    http.Response res;
    try {
      res = await http
          .get(uri, headers: _authHeaders(token))
          .timeout(_requestTimeout);
    } catch (e) {
      throw ArtisanApiException(_friendlyError(e));
    }
    if (res.statusCode != 200) {
      throw ArtisanApiException(
        'Stats ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final data = map['data'] as Map<String, dynamic>? ?? const {};
    int parseInt(dynamic v) {
      if (v is int) return v;
      if (v is num) return v.toInt();
      return int.tryParse('$v') ?? 0;
    }

    return {
      'current_active_load': parseInt(data['current_active_load']),
      'blocked_count': parseInt(data['blocked_count']),
      'total_completed_jobs': parseInt(data['total_completed_jobs']),
    };
  }

  static Future<Map<String, dynamic>> fetchAssignment(
    String token,
    int assignmentId,
  ) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/artisan/assignments/$assignmentId');
    http.Response res;
    try {
      res = await http
          .get(uri, headers: _authHeaders(token))
          .timeout(_requestTimeout);
    } catch (e) {
      throw ArtisanApiException(_friendlyError(e));
    }
    if (res.statusCode != 200) {
      throw ArtisanApiException(
        'Assignment ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final data = map['data'] as Map<String, dynamic>?;
    if (data == null) {
      throw ArtisanApiException('Invalid assignment response');
    }
    return data;
  }

  static Future<void> startJob({
    required String bearerToken,
    required int orderId,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/artisan/orders/$orderId/start');
    http.Response res;
    try {
      res = await http
          .post(uri, headers: _jsonHeaders(bearerToken))
          .timeout(_requestTimeout);
    } catch (e) {
      throw ArtisanApiException(_friendlyError(e));
    }
    if (res.statusCode != 200) {
      throw ArtisanApiException(
        'Start job ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
  }

  static Future<void> readyForReview({
    required String bearerToken,
    required int orderId,
  }) async {
    final uri =
        Uri.parse('${ApiConfig.apiV1}/artisan/orders/$orderId/ready-for-review');
    http.Response res;
    try {
      res = await http
          .post(uri, headers: _jsonHeaders(bearerToken))
          .timeout(_requestTimeout);
    } catch (e) {
      throw ArtisanApiException(_friendlyError(e));
    }
    if (res.statusCode != 200) {
      throw ArtisanApiException(
        'Ready for review ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
  }

  static Future<void> uploadProgress({
    required String bearerToken,
    required int orderId,
    required String stage,
    String? note,
    List<XFile> photos = const [],
  }) async {
    final uri =
        Uri.parse('${ApiConfig.apiV1}/artisan/orders/$orderId/progress');
    final request = http.MultipartRequest('POST', uri);
    request.headers.addAll(_authHeaders(bearerToken));
    request.fields['stage'] = stage;
    if (note != null && note.isNotEmpty) {
      request.fields['note'] = note;
    }
    for (var i = 0; i < photos.length; i++) {
      request.files.add(
        await http.MultipartFile.fromPath('photos[$i]', photos[i].path),
      );
    }
    http.StreamedResponse streamed;
    try {
      streamed = await request.send().timeout(_requestTimeout);
    } catch (e) {
      throw ArtisanApiException(_friendlyError(e));
    }
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode != 201) {
      throw ArtisanApiException(
        'Progress ${streamed.statusCode}: ${_errorBody(body)}',
      );
    }
  }

  static Future<void> markNotificationRead({
    required String bearerToken,
    required String notificationId,
  }) async {
    final uri = Uri.parse(
      '${ApiConfig.apiV1}/notifications/$notificationId/read',
    );
    http.Response res;
    try {
      res = await http
          .post(uri, headers: _jsonHeaders(bearerToken))
          .timeout(_requestTimeout);
    } catch (e) {
      throw ArtisanApiException(_friendlyError(e));
    }
    if (res.statusCode != 200) {
      throw ArtisanApiException(
        'Mark read ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
  }
}
