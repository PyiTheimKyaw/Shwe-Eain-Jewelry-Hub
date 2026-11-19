import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

enum CurrencyDisplay { usd, mmk }

enum WeightDisplay { grams, burmese }

/// Drives MMK vs USD and grams vs Kyat/Pae/Yway across the catalog UI.
/// Persists to [SharedPreferences] so defaults survive restarts.
class DisplayPreferences extends ChangeNotifier {
  DisplayPreferences() {
    unawaited(_restore());
  }

  static const _kCurrency = 'jh_display_currency';
  static const _kWeight = 'jh_display_weight';

  CurrencyDisplay _currency = CurrencyDisplay.mmk;
  WeightDisplay _weight = WeightDisplay.grams;

  CurrencyDisplay get currency => _currency;
  WeightDisplay get weight => _weight;

  Future<void> _restore() async {
    final p = await SharedPreferences.getInstance();
    final c = p.getString(_kCurrency);
    final w = p.getString(_kWeight);
    if (c == CurrencyDisplay.usd.name) {
      _currency = CurrencyDisplay.usd;
    } else if (c == CurrencyDisplay.mmk.name) {
      _currency = CurrencyDisplay.mmk;
    }
    if (w == WeightDisplay.burmese.name) {
      _weight = WeightDisplay.burmese;
    } else if (w == WeightDisplay.grams.name) {
      _weight = WeightDisplay.grams;
    }
    notifyListeners();
  }

  Future<void> _persist() async {
    final p = await SharedPreferences.getInstance();
    await p.setString(_kCurrency, _currency.name);
    await p.setString(_kWeight, _weight.name);
  }

  void setCurrency(CurrencyDisplay value) {
    if (_currency == value) return;
    _currency = value;
    notifyListeners();
    unawaited(_persist());
  }

  void setWeight(WeightDisplay value) {
    if (_weight == value) return;
    _weight = value;
    notifyListeners();
    unawaited(_persist());
  }
}
