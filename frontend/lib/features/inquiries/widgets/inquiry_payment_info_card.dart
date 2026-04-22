import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';

/// Firestore `payment_info_card` message (Dark Gold).
class InquiryPaymentInfoCard extends StatelessWidget {
  const InquiryPaymentInfoCard({
    super.key,
    required this.orderNumber,
    required this.amountMmk,
    required this.kpayId,
    required this.bankName,
    required this.bankAccountNumber,
    required this.bankAccountName,
    this.paymentMethodLabel,
    this.qrImageUrl,
    this.orderTotalMmk,
    this.creditedDepositMmk,
    this.balanceRemainingMmk,
  });

  final String orderNumber;
  final String amountMmk;
  final String kpayId;
  final String bankName;
  final String bankAccountNumber;
  final String bankAccountName;
  final String? paymentMethodLabel;
  final String? qrImageUrl;

  /// Optional breakdown when a prior deposit was recognized (API / Firestore).
  final String? orderTotalMmk;
  final String? creditedDepositMmk;
  final String? balanceRemainingMmk;

  bool get _showCreditBreakdown {
    final c = double.tryParse(creditedDepositMmk ?? '') ?? 0;
    final t = orderTotalMmk?.trim() ?? '';
    return c > 0 && t.isNotEmpty;
  }

  @override
  Widget build(BuildContext context) {
    return AppGlassCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.payments_outlined,
                  color: AppColors.gold.withValues(alpha: 0.9), size: 22),
              const SizedBox(width: 8),
              Expanded(
                child: PremiumText.headline(
                  'Payment $orderNumber',
                  maxLines: 1,
                ),
              ),
            ],
          ),
          if (paymentMethodLabel != null && paymentMethodLabel!.trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            PremiumText.body(
              paymentMethodLabel!.trim(),
              color: AppColors.goldMuted,
            ),
          ],
          if (_showCreditBreakdown) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(AppRadii.sm),
                border: Border.all(
                  color: AppColors.gold.withValues(alpha: 0.35),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _balanceRow('Order total', '${orderTotalMmk!.trim()} MMK'),
                  const SizedBox(height: 6),
                  _balanceRow('Recognized paid', '${creditedDepositMmk!.trim()} MMK'),
                  if (balanceRemainingMmk != null &&
                      balanceRemainingMmk!.trim().isNotEmpty) ...[
                    const SizedBox(height: 6),
                    _balanceRow(
                      'Balance on order',
                      '${balanceRemainingMmk!.trim()} MMK',
                      emphasize: true,
                    ),
                  ],
                ],
              ),
            ),
          ],
          const SizedBox(height: 10),
          PremiumText.caption('PAY NOW (DEPOSIT DUE)', color: AppColors.goldMuted),
          const SizedBox(height: 4),
          PremiumText.body(
            '$amountMmk MMK',
            color: AppColors.gold,
          ),
          const SizedBox(height: 12),
          PremiumText.caption('WALLET / KPAY', color: AppColors.goldMuted),
          PremiumText.body(kpayId.isEmpty ? '—' : kpayId),
          const SizedBox(height: 8),
          PremiumText.caption('BANK', color: AppColors.goldMuted),
          PremiumText.body(bankName.isEmpty ? '—' : bankName),
          const SizedBox(height: 8),
          PremiumText.caption('ACCOUNT', color: AppColors.goldMuted),
          PremiumText.body(
            bankAccountNumber.isEmpty && bankAccountName.isEmpty
                ? '—'
                : '$bankAccountNumber · $bankAccountName',
          ),
          if (qrImageUrl != null && qrImageUrl!.trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(AppRadii.sm),
              child: SizedBox(
                width: double.infinity,
                height: 200,
                child: CachedNetworkImage(
                  imageUrl: qrImageUrl!,
                  fit: BoxFit.contain,
                  placeholder: (context, url) => const Center(
                    child: CircularProgressIndicator(
                      color: AppColors.gold,
                      strokeWidth: 2,
                    ),
                  ),
                  errorWidget: (context, url, error) => Padding(
                    padding: const EdgeInsets.all(8),
                    child: PremiumText.caption(
                      'QR image could not be loaded',
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  static Widget _balanceRow(String label, String value, {bool emphasize = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: PremiumText.caption(
            label,
            color: AppColors.goldMuted,
          ),
        ),
        const SizedBox(width: 8),
        Flexible(
          child: PremiumText.body(
            value,
            color: emphasize ? AppColors.gold : AppColors.textPrimary,
          ),
        ),
      ],
    );
  }
}
