import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';

class ShweEainGoldPlaceholder extends StatelessWidget {
  const ShweEainGoldPlaceholder({
    super.key,
    this.iconSize = 32,
    this.compact = false,
  });

  final double iconSize;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.surface.withValues(alpha: 0.92),
            AppColors.surfaceElevated.withValues(alpha: 0.72),
          ],
        ),
      ),
      alignment: Alignment.center,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.diamond_rounded,
            size: iconSize,
            color: AppColors.gold.withValues(alpha: 0.9),
          ),
          const SizedBox(height: 6),
          PremiumText.caption(
            'S H W E   E A I N',
            color: AppColors.gold.withValues(alpha: 0.86),
          ),
          if (!compact) ...[
            const SizedBox(height: 4),
            PremiumText.caption(
              'J E W E L R Y   H U B',
              color: AppColors.goldMuted,
            ),
          ],
        ],
      ),
    );
  }
}
