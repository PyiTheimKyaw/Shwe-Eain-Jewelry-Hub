import 'package:flutter/foundation.dart';

enum CurrencyDisplay { usd, mmk }

enum WeightDisplay { grams, burmese }

/// Drives MMK vs USD and grams vs Kyat/Pae/Yway across the catalog UI.
/// Toggles rebuild dependents immediately (no API refetch).
class DisplayPreferences extends ChangeNotifier {
  CurrencyDisplay _currency = CurrencyDisplay.mmk;
  WeightDisplay _weight = WeightDisplay.grams;

  CurrencyDisplay get currency => _currency;
  WeightDisplay get weight => _weight;

  void setCurrency(CurrencyDisplay value) {
    if (_currency == value) return;
    _currency = value;
    notifyListeners();
  }

  void setWeight(WeightDisplay value) {
    if (_weight == value) return;
    _weight = value;
    notifyListeners();
  }
}
