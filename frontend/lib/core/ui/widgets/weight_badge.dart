import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/display_preferences_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/units/myanmar_mass.dart';
import 'package:provider/provider.dart';

/// Grams vs Kyat · Pae · Yway with [AnimatedSwitcher].
class WeightBadge extends StatelessWidget {
  const WeightBadge({
    super.key,
    required this.grams,
    this.compact = true,
  });

  final double grams;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Consumer<DisplayPreferences>(
      builder: (context, prefs, _) {
        final burmese = grams.asBurmeseMass;
        final label = prefs.weight == WeightDisplay.grams
            ? '${grams.toStringAsFixed(grams >= 100 ? 1 : 2)} g'
            : burmese.toDisplayString(
                includeGrams: false,
                kyatLabel: 'K',
                paeLabel: 'P',
                ywayLabel: 'Y',
              );

        return AnimatedSwitcher(
          duration: const Duration(milliseconds: 240),
          switchInCurve: Curves.easeOut,
          transitionBuilder: (child, anim) =>
              FadeTransition(opacity: anim, child: child),
          child: Text(
            label,
            key: ValueKey<String>('${prefs.weight.name}_$label'),
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                  fontSize: compact ? 12 : 14,
                ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        );
      },
    );
  }
}
