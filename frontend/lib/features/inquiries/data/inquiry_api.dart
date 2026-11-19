import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:jewelry_hub/core/cache/app_cache_store.dart';
import 'package:image_picker/image_picker.dart';
import 'package:jewelry_hub/core/config/api_config.dart';
import 'package:jewelry_hub/features/inquiries/models/inquiry_pipeline.dart';
import 'package:jewelry_hub/features/inquiries/models/inquiry_summary.dart';

class InquiryApiException implements Exception {
  InquiryApiException(this.message);
  final String message;

  @override
  String toString() => message;
}

class InquiryApi {
  InquiryApi._();
  static const _mineCacheKey = 'inquiries_mine';

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

  static Future<List<InquirySummary>> fetchMine(String token) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/inquiries/mine');
    final res = await http.get(uri, headers: _authHeaders(token));
    if (res.statusCode != 200) {
      throw InquiryApiException(
        'Inquiries ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    return list
        .map((e) => InquirySummary.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  static Future<InquiryMineResult> fetchMineCached(String token) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/inquiries/mine');
    try {
      final res = await http.get(uri, headers: _authHeaders(token));
      if (res.statusCode != 200) {
        throw InquiryApiException(
          'Inquiries ${res.statusCode}: ${_errorBody(res.body)}',
        );
      }
      final map = json.decode(res.body) as Map<String, dynamic>;
      final list = map['data'] as List<dynamic>? ?? [];
      await AppCacheStore.writeJson(key: _mineCacheKey, data: list);
      return InquiryMineResult(
        inquiries: list
            .map((e) => InquirySummary.fromJson(e as Map<String, dynamic>))
            .toList(),
        lastSyncedAt: DateTime.now(),
        fromCache: false,
      );
    } catch (_) {
      final cached = AppCacheStore.readJson(_mineCacheKey);
      if (cached != null && cached.data is List) {
        final list = cached.data as List<dynamic>;
        return InquiryMineResult(
          inquiries: list
              .whereType<Map>()
              .map((e) => InquirySummary.fromJson(Map<String, dynamic>.from(e)))
              .toList(growable: false),
          lastSyncedAt: cached.lastSyncedAt,
          fromCache: true,
        );
      }
      rethrow;
    }
  }

  static Future<InquirySummary> create({
    required String token,
    required String message,
    int? productId,
    List<XFile> attachments = const [],
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/inquiries');
    final request = http.MultipartRequest('POST', uri);
    request.headers.addAll(_authHeaders(token));
    request.fields['message'] = message;
    if (productId != null) {
      request.fields['product_id'] = '$productId';
    }
    for (final file in attachments) {
      request.files.add(
        await http.MultipartFile.fromPath('attachments[]', file.path),
      );
    }
    final streamed = await request.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode != 201) {
      throw InquiryApiException(
        'Create inquiry ${streamed.statusCode}: ${_errorBody(body)}',
      );
    }
    final map = json.decode(body) as Map<String, dynamic>;
    final data = map['data'] as Map<String, dynamic>?;
    if (data == null) {
      throw InquiryApiException('Invalid create inquiry response');
    }
    return InquirySummary.fromJson(data);
  }

  static Future<({String token, String threadId})> fetchFirebaseChatToken({
    required String bearerToken,
    required int inquiryId,
  }) async {
    final uri = Uri.parse(
      '${ApiConfig.apiV1}/conversations/$inquiryId/firebase-token',
    );
    final res = await http.post(uri, headers: _authHeaders(bearerToken));
    if (res.statusCode != 200) {
      throw InquiryApiException(
        'Firebase token ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final t = map['firebase_token'] as String?;
    final thread = map['firebase_thread_id'] as String?;
    if (t == null || thread == null) {
      throw InquiryApiException('Invalid firebase token response');
    }
    return (token: t, threadId: thread);
  }

  /// GET /inquiries/{id} — returns pipeline in `meta`.
  /// GET /quotes/{id} — full quote with line_items (for chat fallback when Firestore has no quote_card).
  static Future<Map<String, dynamic>> fetchQuote({
    required String bearerToken,
    required int quoteId,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/quotes/$quoteId');
    final res = await http.get(uri, headers: _authHeaders(bearerToken));
    if (res.statusCode != 200) {
      throw InquiryApiException(
        'Quote ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final data = map['data'] as Map<String, dynamic>?;
    if (data == null) {
      throw InquiryApiException('Invalid quote response');
    }
    return data;
  }

  static Future<InquiryPipeline?> fetchPipeline({
    required String bearerToken,
    required int inquiryId,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/inquiries/$inquiryId');
    final res = await http.get(uri, headers: _authHeaders(bearerToken));
    if (res.statusCode != 200) {
      throw InquiryApiException(
        'Inquiry ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final meta = map['meta'] as Map<String, dynamic>?;
    final p = meta?['pipeline'] as Map<String, dynamic>?;
    if (p == null) {
      return null;
    }
    return InquiryPipeline.fromJson(p);
  }

  static Future<({Map<String, dynamic> quote, Map<String, dynamic> order})>
      acceptQuote({
    required String bearerToken,
    required int quoteId,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/quotes/$quoteId/accept');
    final res = await http.post(uri, headers: _jsonHeaders(bearerToken));
    if (res.statusCode != 200) {
      throw InquiryApiException(
        'Accept quote ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final data = map['data'] as Map<String, dynamic>?;
    if (data == null) {
      throw InquiryApiException('Invalid accept quote response');
    }
    final quote = data['quote'] as Map<String, dynamic>?;
    final order = data['order'] as Map<String, dynamic>?;
    if (quote == null || order == null) {
      throw InquiryApiException('Invalid accept quote payload');
    }
    return (quote: quote, order: order);
  }

  static Future<void> declineQuote({
    required String bearerToken,
    required int quoteId,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/quotes/$quoteId/decline');
    final res = await http.post(uri, headers: _jsonHeaders(bearerToken));
    if (res.statusCode != 200) {
      throw InquiryApiException(
        'Decline quote ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
  }

  static Future<void> uploadDepositProof({
    required String bearerToken,
    required int orderId,
    required XFile proof,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/orders/$orderId/deposit-proof');
    final request = http.MultipartRequest('POST', uri);
    request.headers.addAll(_authHeaders(bearerToken));
    request.files.add(
      await http.MultipartFile.fromPath('proof', proof.path),
    );
    final streamed = await request.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode != 201) {
      throw InquiryApiException(
        'Deposit proof ${streamed.statusCode}: ${_errorBody(body)}',
      );
    }
  }

  static Future<String> uploadChatImage({
    required String bearerToken,
    required XFile file,
    required int inquiryId,
    String folder = 'inquiry-chat',
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/uploads');
    final request = http.MultipartRequest('POST', uri);
    request.headers.addAll(_authHeaders(bearerToken));
    request.fields['folder'] = folder;
    request.fields['inquiry_id'] = inquiryId.toString();
    request.files.add(await http.MultipartFile.fromPath('file', file.path));

    final streamed = await request.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode != 201) {
      throw InquiryApiException(
        'Upload image ${streamed.statusCode}: ${_errorBody(body)}',
      );
    }
    final map = json.decode(body) as Map<String, dynamic>;
    final rawUrl = (map['url'] ?? '').toString().trim();
    final storagePath = (map['storage_path'] ?? '').toString().trim();
    final base = ApiConfig.baseUrl.replaceAll(RegExp(r'/$'), '');

    if (rawUrl.startsWith('http://') || rawUrl.startsWith('https://')) {
      return rawUrl;
    }
    if (rawUrl.startsWith('/')) {
      return '$base$rawUrl';
    }
    if (storagePath.isNotEmpty) {
      return '$base/storage/$storagePath';
    }
    throw InquiryApiException('Upload image succeeded but URL is missing.');
  }
}

class InquiryMineResult {
  const InquiryMineResult({
    required this.inquiries,
    required this.lastSyncedAt,
    required this.fromCache,
  });

  final List<InquirySummary> inquiries;
  final DateTime lastSyncedAt;
  final bool fromCache;
}
