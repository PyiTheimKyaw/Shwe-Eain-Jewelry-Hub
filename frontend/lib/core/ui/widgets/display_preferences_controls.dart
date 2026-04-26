import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/display_preferences_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:provider/provider.dart';

/// Shared currency + weight controls (sheet and full-screen settings).
class DisplayPreferencesControls extends StatelessWidget {
  const DisplayPreferencesControls({super.key});

  static ButtonStyle toggleButtonStyle(BuildContext context) {
    return ButtonStyle(
      visualDensity: VisualDensity.compact,
      side: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) {
          return BorderSide(color: AppColors.gold.withValues(alpha: 0.82));
        }
        return BorderSide(color: AppColors.glassBorder.withValues(alpha: 0.9));
      }),
      backgroundColor: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) {
          return AppColors.gold.withValues(alpha: 0.24);
        }
        return AppColors.surface.withValues(alpha: 0.38);
      }),
      foregroundColor: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) {
          return AppColors.gold;
        }
        return AppColors.textSecondary;
      }),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            const Icon(
              Icons.currency_exchange_rounded,
              size: 18,
              color: AppColors.gold,
            ),
            const SizedBox(width: 8),
            PremiumText.body('Default currency', color: AppColors.textSecondary),
          ],
        ),
        const SizedBox(height: 10),
        Consumer<DisplayPreferences>(
          builder: (context, prefs, _) {
            return SegmentedButton<CurrencyDisplay>(
              segments: const [
                ButtonSegment(value: CurrencyDisplay.mmk, label: Text('MMK')),
                ButtonSegment(value: CurrencyDisplay.usd, label: Text('USD')),
              ],
              selected: {prefs.currency},
              onSelectionChanged: (s) => prefs.setCurrency(s.first),
              style: toggleButtonStyle(context),
            );
          },
        ),
        const SizedBox(height: 18),
        Row(
          children: [
            const Icon(
              Icons.straighten_rounded,
              size: 18,
              color: AppColors.gold,
            ),
            const SizedBox(width: 8),
            PremiumText.body('Default weight unit', color: AppColors.textSecondary),
          ],
        ),
        const SizedBox(height: 10),
        Consumer<DisplayPreferences>(
          builder: (context, prefs, _) {
            return SegmentedButton<WeightDisplay>(
              segments: const [
                ButtonSegment(value: WeightDisplay.grams, label: Text('Grams')),
                ButtonSegment(
                  value: WeightDisplay.burmese,
                  label: Text('K·P·Y'),
                ),
              ],
              selected: {prefs.weight},
              onSelectionChanged: (s) => prefs.setWeight(s.first),
              style: toggleButtonStyle(context),
            );
          },
        ),
      ],
    );
  }
}
