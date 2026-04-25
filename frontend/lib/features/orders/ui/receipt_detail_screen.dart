import 'dart:io';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_gold_button.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/orders/data/order_api.dart';
import 'package:jewelry_hub/features/orders/models/payment_receipt.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:provider/provider.dart';

class ReceiptDetailScreen extends StatefulWidget {
  const ReceiptDetailScreen({super.key, required this.receipt});

  final PaymentReceipt receipt;

  @override
  State<ReceiptDetailScreen> createState() => _ReceiptDetailScreenState();
}

class _ReceiptDetailScreenState extends State<ReceiptDetailScreen> {
  bool _downloading = false;
  final NumberFormat _money = NumberFormat('#,##0.00');

  Future<void> _download() async {
    final token = context.read<AuthProvider>().token;
    if (token == null || _downloading) {
      return;
    }

    setState(() => _downloading = true);
    try {
      final bytes = await OrderApi.downloadReceiptPdf(
        token: token,
        orderId: widget.receipt.orderId,
        receiptId: widget.receipt.id,
      );
      final dir = await getApplicationDocumentsDirectory();
      final file = File('${dir.path}/${widget.receipt.receiptNumber}.pdf');
      await file.writeAsBytes(bytes, flush: true);
      await OpenFilex.open(file.path);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Receipt saved to ${file.path}')));
    } on OrderApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) {
        setState(() => _downloading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final issued = widget.receipt.issuedAt;
    return Scaffold(
      appBar: AppBar(
        title: PremiumText.headline('Payment receipt', maxLines: 1),
      ),
      extendBodyBehindAppBar: true,
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: AppGlassCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  PremiumText.display('Shwe Eain'),
                  const SizedBox(height: 8),
                  PremiumText.caption(
                    'Formal payment receipt',
                    color: AppColors.goldMuted,
                  ),
                  const SizedBox(height: 4),
                  PremiumText.caption(
                    widget.receipt.paymentType == 'final'
                        ? 'Final settlement'
                        : 'Deposit payment',
                    color: AppColors.textSecondary,
                  ),
                  const SizedBox(height: 20),
                  PremiumText.body('Receipt #${widget.receipt.receiptNumber}'),
                  const SizedBox(height: 6),
                  PremiumText.body('Order #${widget.receipt.orderId}'),
                  const SizedBox(height: 6),
                  PremiumText.body(
                    'Issued: ${issued != null ? DateFormat('y-MM-dd HH:mm').format(issued.toLocal()) : '-'}',
                  ),
                  const SizedBox(height: 20),
                  PremiumText.caption(
                    'Amount paid',
                    color: AppColors.goldMuted,
                  ),
                  PremiumText.headline(
                    '${_money.format(widget.receipt.amountMmk)} ${widget.receipt.currencyCode}',
                    maxLines: 1,
                  ),
                  const Spacer(),
                  AppGoldButton(
                    label: _downloading ? 'Downloading...' : 'Download PDF',
                    icon: Icons.download_rounded,
                    onPressed: _downloading ? null : _download,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
