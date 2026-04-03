import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/catalog_pricing_provider.dart';
import 'package:jewelry_hub/core/providers/display_preferences_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:provider/provider.dart';

/// Shows estimated line total in MMK or USD with a short fade when toggled.
class PriceTag extends StatelessWidget {
  const PriceTag({
    super.key,
    this.pricing,
    this.compact = true,
  });

  final Map<String, dynamic>? pricing;
  final bool compact;

  static final _usd = NumberFormat.currency(symbol: r'$', decimalDigits: 2);
  static final _mmk = NumberFormat.currency(symbol: 'K ', decimalDigits: 0);

  @override
  Widget build(BuildContext context) {
    return Consumer2<DisplayPreferences, CatalogPricing>(
      builder: (context, prefs, fx, _) {
        final mmkStr = pricing?['line_total_mmk']?.toString();
        final usdStr = pricing?['line_total_usd']?.toString();
        final mmkApi = mmkStr != null ? double.tryParse(mmkStr) : null;
        final usdApi = usdStr != null ? double.tryParse(usdStr) : null;

        final mmk = prefs.currency == CurrencyDisplay.mmk
            ? (mmkApi ?? fx.mmkFromUsd(usdApi))
            : null;
        final usd = prefs.currency == CurrencyDisplay.usd
            ? (usdApi ?? fx.usdFromMmk(mmkApi))
            : null;

        String label;
        if (pricing == null || (mmkApi == null && usdApi == null)) {
          label = 'Price on request';
        } else if (prefs.currency == CurrencyDisplay.mmk) {
          final m = mmk;
          label = m != null ? _mmk.format(m) : 'MMK —';
        } else {
          final u = usd;
          label = u != null ? _usd.format(u) : r'$ —';
        }

        return AnimatedSwitcher(
          duration: const Duration(milliseconds: 240),
          switchInCurve: Curves.easeOut,
          transitionBuilder: (child, anim) =>
              FadeTransition(opacity: anim, child: child),
          child: Text(
            label,
            key: ValueKey<String>('${prefs.currency.name}_$label'),
            style: compact
                ? Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: AppColors.gold,
                      fontSize: compact ? 16 : 20,
                      fontWeight: FontWeight.w700,
                    )
                : Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: AppColors.gold,
                    ),
          ),
        );
      },
    );
  }
}
