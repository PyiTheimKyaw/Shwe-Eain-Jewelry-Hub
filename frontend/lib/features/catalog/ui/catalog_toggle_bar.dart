import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/display_preferences_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:provider/provider.dart';

/// Sticky-style currency + weight toggles (provider-driven instant UI updates).
class CatalogToggleBar extends StatelessWidget {
  const CatalogToggleBar({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
      child: Row(
        children: [
          Expanded(
            child: Consumer<DisplayPreferences>(
              builder: (context, prefs, _) {
                return SegmentedButton<CurrencyDisplay>(
                  segments: const [
                    ButtonSegment(
                      value: CurrencyDisplay.mmk,
                      label: Text('MMK'),
                    ),
                    ButtonSegment(
                      value: CurrencyDisplay.usd,
                      label: Text('USD'),
                    ),
                  ],
                  selected: {prefs.currency},
                  onSelectionChanged: (s) => prefs.setCurrency(s.first),
                  style: ButtonStyle(
                    visualDensity: VisualDensity.compact,
                    foregroundColor: WidgetStateProperty.resolveWith((states) {
                      if (states.contains(WidgetState.selected)) {
                        return AppColors.background;
                      }
                      return AppColors.gold;
                    }),
                  ),
                );
              },
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Consumer<DisplayPreferences>(
              builder: (context, prefs, _) {
                return SegmentedButton<WeightDisplay>(
                  segments: const [
                    ButtonSegment(
                      value: WeightDisplay.grams,
                      label: Text('g'),
                    ),
                    ButtonSegment(
                      value: WeightDisplay.burmese,
                      label: Text('K·P·Y'),
                    ),
                  ],
                  selected: {prefs.weight},
                  onSelectionChanged: (s) => prefs.setWeight(s.first),
                  style: ButtonStyle(
                    visualDensity: VisualDensity.compact,
                    foregroundColor: WidgetStateProperty.resolveWith((states) {
                      if (states.contains(WidgetState.selected)) {
                        return AppColors.background;
                      }
                      return AppColors.gold;
                    }),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
