import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Locally persisted catalog favorites (product ids), newest first.
class SavedFavorites extends ChangeNotifier {
  SavedFavorites() {
    unawaited(_restore());
  }

  static const _key = 'jh_saved_product_ids';
  final List<int> _ids = [];

  List<int> get orderedIds => List.unmodifiable(_ids);

  bool isFavorite(int productId) => _ids.contains(productId);

  Future<void> _restore() async {
    final p = await SharedPreferences.getInstance();
    final raw = p.getString(_key);
    if (raw == null || raw.isEmpty) {
      notifyListeners();
      return;
    }
    try {
      final list = json.decode(raw) as List<dynamic>;
      _ids
        ..clear()
        ..addAll(
          list.map((e) => e is int ? e : int.tryParse('$e') ?? 0).where((e) => e > 0),
        );
    } catch (_) {
      _ids.clear();
    }
    notifyListeners();
  }

  Future<void> _persist() async {
    final p = await SharedPreferences.getInstance();
    await p.setString(_key, json.encode(_ids));
  }

  void toggle(int productId) {
    final i = _ids.indexOf(productId);
    if (i >= 0) {
      _ids.removeAt(i);
    } else {
      _ids.insert(0, productId);
    }
    notifyListeners();
    unawaited(_persist());
  }

  void remove(int productId) {
    if (!_ids.remove(productId)) return;
    notifyListeners();
    unawaited(_persist());
  }
}
