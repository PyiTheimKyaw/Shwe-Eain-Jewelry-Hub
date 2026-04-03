import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/inquiries/models/inquiry_pipeline.dart';

/// Six-step concierge journey (mirrors backend pipeline steps).
class ConciergePipelineStepper extends StatelessWidget {
  const ConciergePipelineStepper({
    super.key,
    required this.pipeline,
  });

  final InquiryPipeline pipeline;

  static const _labels = [
    'Consulting',
    'Quoted',
    'Awaiting deposit',
    'In production',
    'Quality review',
    'Ready',
  ];

  int _indexForStep(String step) {
    switch (step) {
      case 'consulting':
        return 0;
      case 'quoted':
        return 1;
      case 'awaiting_deposit':
      case 'pre_production':
        return 2;
      case 'in_production':
        return 3;
      case 'pending_qc':
        return 4;
      case 'ready':
        return 5;
      default:
        return 0;
    }
  }

  @override
  Widget build(BuildContext context) {
    final active = _indexForStep(pipeline.step);
    return AppGlassCard(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          PremiumText.caption(
            'YOUR JOURNEY',
            color: AppColors.goldMuted,
          ),
          const SizedBox(height: 10),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (int i = 0; i < 6; i++) ...[
                Expanded(
                  child: _StepDot(
                    label: _labels[i],
                    index: i,
                    activeIndex: active,
                  ),
                ),
                if (i < 5)
                  Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: _Connector(complete: i < active),
                  ),
              ],
            ],
          ),
          if (pipeline.orderNumber != null) ...[
            const SizedBox(height: 8),
            PremiumText.caption(
              'Order ${pipeline.orderNumber}',
              color: AppColors.textSecondary,
            ),
          ],
        ],
      ),
    );
  }
}

class _Connector extends StatelessWidget {
  const _Connector({required this.complete});

  final bool complete;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 4,
      height: 2,
      margin: const EdgeInsets.symmetric(horizontal: 1),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(1),
        color: complete
            ? AppColors.gold.withValues(alpha: 0.7)
            : AppColors.glassBorder,
      ),
    );
  }
}

class _StepDot extends StatelessWidget {
  const _StepDot({
    required this.label,
    required this.index,
    required this.activeIndex,
  });

  final String label;
  final int index;
  final int activeIndex;

  @override
  Widget build(BuildContext context) {
    final done = index < activeIndex;
    final current = index == activeIndex;
    final gold = done || current;
    return Column(
      children: [
        AnimatedContainer(
          duration: const Duration(milliseconds: 240),
          curve: Curves.easeOut,
          width: 20,
          height: 20,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
              color: gold ? AppColors.gold : AppColors.glassBorder,
              width: current ? 2 : 1,
            ),
            color: done
                ? AppColors.gold.withValues(alpha: 0.25)
                : current
                    ? AppColors.gold.withValues(alpha: 0.15)
                    : AppColors.surface.withValues(alpha: 0.4),
          ),
          child: done
              ? const Icon(
                  Icons.check_rounded,
                  size: 12,
                  color: AppColors.gold,
                )
              : current
                  ? Center(
                      child: Container(
                        width: 7,
                        height: 7,
                        decoration: const BoxDecoration(
                          shape: BoxShape.circle,
                          color: AppColors.gold,
                        ),
                      ),
                    )
                  : null,
        ),
        const SizedBox(height: 4),
        Text(
          label,
          textAlign: TextAlign.center,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                fontSize: 8,
                height: 1.1,
                color: current ? AppColors.gold : AppColors.textSecondary,
                fontWeight: current ? FontWeight.w700 : FontWeight.w500,
              ),
        ),
      ],
    );
  }
}
