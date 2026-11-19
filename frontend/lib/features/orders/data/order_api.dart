import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:jewelry_hub/core/cache/app_cache_store.dart';
import 'package:jewelry_hub/core/config/api_config.dart';
import 'package:jewelry_hub/features/orders/models/payment_receipt.dart';

class OrderApiException implements Exception {
  OrderApiException(this.message);
  final String message;
}

class OrderApi {
  OrderApi._();
  static const _ordersCacheKey = 'orders_mine';

  static Map<String, String> _authHeaders(String token) => {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
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

  static Future<List<Map<String, dynamic>>> fetchOrders(String token) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/orders/mine');
    final res = await http.get(uri, headers: _authHeaders(token));
    if (res.statusCode != 200) {
      throw OrderApiException(
        'Orders ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  static Future<OrderListResult> fetchOrdersCached(String token) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/orders/mine');
    try {
      final res = await http.get(uri, headers: _authHeaders(token));
      if (res.statusCode != 200) {
        throw OrderApiException(
          'Orders ${res.statusCode}: ${_errorBody(res.body)}',
        );
      }
      final map = json.decode(res.body) as Map<String, dynamic>;
      final list = map['data'] as List<dynamic>? ?? [];
      await AppCacheStore.writeJson(key: _ordersCacheKey, data: list);
      return OrderListResult(
        orders: list.map((e) => Map<String, dynamic>.from(e as Map)).toList(),
        lastSyncedAt: DateTime.now(),
        fromCache: false,
      );
    } catch (_) {
      final cached = AppCacheStore.readJson(_ordersCacheKey);
      if (cached != null && cached.data is List) {
        final list = cached.data as List<dynamic>;
        return OrderListResult(
          orders: list
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList(growable: false),
          lastSyncedAt: cached.lastSyncedAt,
          fromCache: true,
        );
      }
      rethrow;
    }
  }

  static Future<List<PaymentReceipt>> fetchReceipts({
    required String token,
    required int orderId,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/orders/$orderId/receipts');
    final res = await http.get(uri, headers: _authHeaders(token));
    if (res.statusCode != 200) {
      throw OrderApiException(
        'Receipts ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    return list
        .map(
          (e) => PaymentReceipt.fromJson(Map<String, dynamic>.from(e as Map)),
        )
        .toList();
  }

  static Future<List<Map<String, dynamic>>> fetchOrderProgress({
    required String token,
    required int orderId,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/orders/$orderId/progress');
    final res = await http.get(uri, headers: _authHeaders(token));
    if (res.statusCode != 200) {
      throw OrderApiException(
        'Progress ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  static Future<Map<String, dynamic>> fetchOrderDetail({
    required String token,
    required int orderId,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/orders/$orderId');
    final res = await http.get(uri, headers: _authHeaders(token));
    if (res.statusCode != 200) {
      throw OrderApiException(
        'Order detail ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final raw = map['data'];
    if (raw is! Map) {
      throw OrderApiException('Invalid order detail payload.');
    }
    return Map<String, dynamic>.from(raw);
  }

  static Future<Uint8List> downloadReceiptPdf({
    required String token,
    required int orderId,
    required int receiptId,
  }) async {
    final uri = Uri.parse(
      '${ApiConfig.apiV1}/orders/$orderId/receipts/$receiptId/download',
    );
    final res = await http.get(uri, headers: _authHeaders(token));
    if (res.statusCode != 200) {
      throw OrderApiException(
        'Download ${res.statusCode}: ${_errorBody(res.body)}',
      );
    }
    return res.bodyBytes;
  }
}

class OrderListResult {
  const OrderListResult({
    required this.orders,
    required this.lastSyncedAt,
    required this.fromCache,
  });

  final List<Map<String, dynamic>> orders;
  final DateTime lastSyncedAt;
  final bool fromCache;
}
