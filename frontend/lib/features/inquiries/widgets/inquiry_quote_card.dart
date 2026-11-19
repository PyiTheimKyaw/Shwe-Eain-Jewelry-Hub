import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_gold_button.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';

/// Visual quote breakdown (Firestore quote_card + Dark Gold styling).
class InquiryQuoteCard extends StatelessWidget {
  const InquiryQuoteCard({
    super.key,
    required this.quoteNumber,
    required this.totalMmk,
    required this.lines,
    this.onAccept,
    this.onDecline,
    this.actionsEnabled = true,
    this.busy = false,
  });

  final String quoteNumber;
  final String totalMmk;
  final List<Map<String, dynamic>> lines;
  final VoidCallback? onAccept;
  final VoidCallback? onDecline;
  final bool actionsEnabled;
  final bool busy;

  static final _mmk = NumberFormat.currency(symbol: 'K ', decimalDigits: 0);

  String _fmt(String raw) {
    final v = double.tryParse(raw);
    if (v == null) {
      return raw;
    }
    return _mmk.format(v);
  }

  @override
  Widget build(BuildContext context) {
    return AppGlassCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.diamond_outlined, color: AppColors.gold.withValues(alpha: 0.9), size: 22),
              const SizedBox(width: 8),
              Expanded(
                child: PremiumText.headline(
                  'Quote $quoteNumber',
                  maxLines: 1,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          PremiumText.caption(
            'ESTIMATE (MMK)',
            color: AppColors.goldMuted,
          ),
          const SizedBox(height: 8),
          for (final line in lines) ...[
            _LineRow(
              title: line['title'] as String? ?? 'Item',
              metal: _fmt((line['metal_mmk'] ?? '0').toString()),
              labor: _fmt((line['labor_mmk'] ?? '0').toString()),
              gems: _fmt((line['gems_mmk'] ?? '0').toString()),
              lineTotal: _fmt((line['line_total_mmk'] ?? '0').toString()),
            ),
            const SizedBox(height: 8),
          ],
          const Divider(color: AppColors.glassBorder, height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              PremiumText.body('Total', color: AppColors.textSecondary),
              PremiumText.headline(
                _fmt(totalMmk),
                maxLines: 1,
              ),
            ],
          ),
          if (onAccept != null && onDecline != null) ...[
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: actionsEnabled && !busy ? onDecline : null,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.textSecondary,
                      side: const BorderSide(color: AppColors.glassBorder),
                    ),
                    child: Text(
                      'Decline',
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: busy
                      ? const Center(
                          child: SizedBox(
                            width: 28,
                            height: 28,
                            child: CircularProgressIndicator(
                              color: AppColors.gold,
                              strokeWidth: 2,
                            ),
                          ),
                        )
                      : AppGoldButton(
                          label: 'Accept',
                          icon: Icons.check_rounded,
                          onPressed: actionsEnabled ? onAccept : null,
                        ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _LineRow extends StatelessWidget {
  const _LineRow({
    required this.title,
    required this.metal,
    required this.labor,
    required this.gems,
    required this.lineTotal,
  });

  final String title;
  final String metal;
  final String labor;
  final String gems;
  final String lineTotal;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        PremiumText.body(title, maxLines: 2),
        const SizedBox(height: 4),
        Wrap(
          spacing: 10,
          runSpacing: 4,
          children: [
            _MiniChip(label: 'Gold', value: metal),
            _MiniChip(label: 'Labor', value: labor),
            _MiniChip(label: 'Gems', value: gems),
          ],
        ),
        const SizedBox(height: 4),
        Align(
          alignment: Alignment.centerRight,
          child: PremiumText.caption(
            'Line $lineTotal',
            color: AppColors.goldMuted,
          ),
        ),
      ],
    );
  }
}

class _MiniChip extends StatelessWidget {
  const _MiniChip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadii.sm),
        border: Border.all(color: AppColors.glassBorder),
        color: AppColors.surface.withValues(alpha: 0.35),
      ),
      child: Text(
        '$label $value',
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textSecondary,
              fontSize: 11,
            ),
      ),
    );
  }
}
