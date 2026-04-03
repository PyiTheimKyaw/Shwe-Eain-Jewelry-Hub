import 'dart:math' as math;

/// Traditional Burmese **mass** units for gold (kyat / pae / yway).
///
/// Not to be confused with Myanmar **currency** kyat (MMK). Per project rules:
/// - [gramsPerBurmeseKyatMass] g = 1 mass kyat
/// - [paePerKyat] pae = 1 mass kyat
/// - [ywayPerPae] yway = 1 pae
class MyanmarMassStandards {
  MyanmarMassStandards._();

  static const double gramsPerBurmeseKyatMass = 16.606;
  static const int paePerKyat = 16;
  static const int ywayPerPae = 8;
}

/// Whole + fractional breakdown of a mass in Burmese units from a gram value.
class MyanmarMassBreakdown {
  const MyanmarMassBreakdown({
    required this.kyat,
    required this.pae,
    required this.yway,
    required this.grams,
  });

  /// Builds from a weight stored in **grams** (API base unit).
  factory MyanmarMassBreakdown.fromGrams(double grams) {
    final g = grams < 0 ? 0.0 : grams;
    final gpk = MyanmarMassStandards.gramsPerBurmeseKyatMass;
    final paePerKyat = MyanmarMassStandards.paePerKyat;
    final ywayPerPae = MyanmarMassStandards.ywayPerPae;

    var totalKyat = g / gpk;
    var kyat = totalKyat.floor();
    var remKyat = totalKyat - kyat;

    var paePrecise = remKyat * paePerKyat;
    var pae = paePrecise.floor();
    var remPae = paePrecise - pae;

    var yway = remPae * ywayPerPae;

    // Float safety: normalize overflows
    while (yway >= ywayPerPae) {
      yway -= ywayPerPae;
      pae++;
    }
    while (pae >= paePerKyat) {
      pae -= paePerKyat;
      kyat++;
    }

    return MyanmarMassBreakdown(
      kyat: kyat,
      pae: pae,
      yway: yway,
      grams: g,
    );
  }

  final int kyat;
  final int pae;
  final double yway;
  final double grams;

  /// Renders for UI, e.g. `1 Kyat 2 Pae 0.5 Yway (16.61 g)`.
  String toDisplayString({
    String kyatLabel = 'Kyat',
    String paeLabel = 'Pae',
    String ywayLabel = 'Yway',
    bool includeGrams = true,
    int ywayDecimals = 2,
  }) {
    final yStr = _formatYway(yway, ywayDecimals);
    final parts = <String>[
      '$kyat $kyatLabel',
      '$pae $paeLabel',
      '$yStr $ywayLabel',
    ];
    final core = parts.join(' · ');
    if (!includeGrams) return core;
    final gStr = grams.toStringAsFixed(grams >= 100 ? 1 : 2);
    return '$core ($gStr g)';
  }

  static String _formatYway(double y, int decimals) {
    if (decimals <= 0) {
      return y.round().toString();
    }
    final factor = math.pow(10, decimals);
    final rounded = (y * factor).round() / factor;
    if (rounded == rounded.roundToDouble()) {
      return rounded.toInt().toString();
    }
    return rounded.toStringAsFixed(decimals);
  }
}

/// Use on numeric gram values from the API or forms.
extension MyanmarMassGramsExtension on num {
  MyanmarMassBreakdown get asBurmeseMass =>
      MyanmarMassBreakdown.fromGrams(toDouble());
}

/// Optional: mix into [State] or a small widget helper for consistent labels.
mixin MyanmarMassDisplay {
  String formatBurmeseMass(
    double grams, {
    bool includeGrams = true,
  }) =>
      grams.asBurmeseMass.toDisplayString(includeGrams: includeGrams);
}
