import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:jewelry_hub/core/config/api_config.dart';

class ArtisanApiException implements Exception {
  ArtisanApiException(this.message);
  final String message;

  @override
  String toString() => message;
}

class ArtisanApi {
  ArtisanApi._();

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

  static Future<List<Map<String, dynamic>>> fetchAssignments(
    String token,
  ) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/artisan/assignments');
    final res = await http.get(uri, headers: _authHeaders(token));
    if (res.statusCode != 200) {
      throw ArtisanApiException(
        'Assignments ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  static Future<Map<String, dynamic>> fetchAssignment(
    String token,
    int assignmentId,
  ) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/artisan/assignments/$assignmentId');
    final res = await http.get(uri, headers: _authHeaders(token));
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
    final res = await http.post(uri, headers: _jsonHeaders(bearerToken));
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
    final res = await http.post(uri, headers: _jsonHeaders(bearerToken));
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
    final streamed = await request.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode != 201) {
      throw ArtisanApiException(
        'Progress ${streamed.statusCode}: ${_errorBody(body)}',
      );
    }
  }
}
