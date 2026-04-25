import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/display_preferences_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:provider/provider.dart';

Future<void> showDisplayPreferencesSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: Colors.transparent,
    isScrollControlled: true,
    builder: (context) {
      return const _DisplayPreferencesSheetBody();
    },
  );
}

class _DisplayPreferencesSheetBody extends StatelessWidget {
  const _DisplayPreferencesSheetBody();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
      child: AppGlassCard(
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 14),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.55),
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
            ),
            const SizedBox(height: 12),
            PremiumText.caption(
              'DISPLAY PREFERENCES',
              color: AppColors.goldMuted,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                const Icon(
                  Icons.currency_exchange_rounded,
                  size: 16,
                  color: AppColors.gold,
                ),
                const SizedBox(width: 6),
                PremiumText.body('Currency', color: AppColors.textSecondary),
              ],
            ),
            const SizedBox(height: 8),
            Consumer<DisplayPreferences>(
              builder: (context, prefs, _) {
                return SegmentedButton<CurrencyDisplay>(
                  segments: const [
                    ButtonSegment(value: CurrencyDisplay.mmk, label: Text('MMK')),
                    ButtonSegment(value: CurrencyDisplay.usd, label: Text('USD')),
                  ],
                  selected: {prefs.currency},
                  onSelectionChanged: (s) => prefs.setCurrency(s.first),
                  style: _toggleStyle(),
                );
              },
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                const Icon(
                  Icons.straighten_rounded,
                  size: 16,
                  color: AppColors.gold,
                ),
                const SizedBox(width: 6),
                PremiumText.body('Weight Unit', color: AppColors.textSecondary),
              ],
            ),
            const SizedBox(height: 8),
            Consumer<DisplayPreferences>(
              builder: (context, prefs, _) {
                return SegmentedButton<WeightDisplay>(
                  segments: const [
                    ButtonSegment(value: WeightDisplay.grams, label: Text('g')),
                    ButtonSegment(
                      value: WeightDisplay.burmese,
                      label: Text('K·P·Y'),
                    ),
                  ],
                  selected: {prefs.weight},
                  onSelectionChanged: (s) => prefs.setWeight(s.first),
                  style: _toggleStyle(),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  ButtonStyle _toggleStyle() {
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
}
