import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/display_preferences_controls.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';

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
            const DisplayPreferencesControls(),
          ],
        ),
      ),
    );
  }
}
