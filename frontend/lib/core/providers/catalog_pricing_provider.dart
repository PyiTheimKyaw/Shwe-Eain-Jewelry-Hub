import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:jewelry_hub/core/config/api_config.dart';

/// Cached public FX (MMK per 1 USD) for cross-currency display on the client.
class CatalogPricing extends ChangeNotifier {
  double? _mmkPerUsd;
  bool _loaded = false;
  String? _error;

  /// MMK equivalent of 1 USD (same numeric value as legacy `usd_to_mmk`).
  double? get mmkPerUsd => _mmkPerUsd;

  /// Alias for readability where “USD→MMK” is described.
  double? get usdToMmk => _mmkPerUsd;

  bool get isLoaded => _loaded;
  String? get error => _error;

  Future<void> refresh() async {
    _error = null;
    try {
      final uri = Uri.parse('${ApiConfig.apiV1}/pricing/fx/current');
      final res = await http.get(uri);
      if (res.statusCode != 200) {
        _error = 'FX ${res.statusCode}';
        _mmkPerUsd = null;
      } else {
        final map = json.decode(res.body) as Map<String, dynamic>;
        final data = map['data'] as Map<String, dynamic>?;
        final raw = data?['mmk_per_usd'] ?? data?['usd_to_mmk'];
        if (data != null && raw != null) {
          _mmkPerUsd = double.tryParse(raw.toString());
        } else {
          _mmkPerUsd = null;
        }
      }
    } catch (e) {
      _error = e.toString();
      _mmkPerUsd = null;
    } finally {
      _loaded = true;
      notifyListeners();
    }
  }

  /// MMK for a USD total when only USD is available.
  double? mmkFromUsd(double? usd) {
    if (usd == null || _mmkPerUsd == null) return null;
    return usd * _mmkPerUsd!;
  }

  /// USD for an MMK total when only MMK is available.
  double? usdFromMmk(double? mmk) {
    if (mmk == null || _mmkPerUsd == null || _mmkPerUsd == 0) return null;
    return mmk / _mmkPerUsd!;
  }
}
