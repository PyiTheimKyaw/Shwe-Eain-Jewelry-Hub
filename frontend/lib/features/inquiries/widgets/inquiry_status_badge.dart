import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';

/// Maps API statuses to customer-facing labels and colors.
class InquiryStatusBadge extends StatelessWidget {
  const InquiryStatusBadge({super.key, required this.status});

  final String status;

  static String labelFor(String status) {
    switch (status) {
      case 'open':
      case 'reviewing':
        return 'Pending';
      case 'quoted':
        return 'Quoted';
      case 'closed':
        return 'Closed';
      default:
        return status;
    }
  }

  static Color colorFor(String status) {
    switch (status) {
      case 'open':
      case 'reviewing':
        return AppColors.goldMuted;
      case 'quoted':
        return const Color(0xFF34D399);
      case 'closed':
        return AppColors.textSecondary;
      default:
        return AppColors.textSecondary;
    }
  }

  @override
  Widget build(BuildContext context) {
    final label = labelFor(status);
    final c = colorFor(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: c.withValues(alpha: 0.6)),
        color: c.withValues(alpha: 0.12),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: c,
              fontSize: 11,
              letterSpacing: 0.6,
            ),
      ),
    );
  }
}
