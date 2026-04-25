import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/orders/data/order_api.dart';
import 'package:jewelry_hub/features/orders/models/payment_receipt.dart';
import 'package:jewelry_hub/features/orders/ui/customer_order_detail_screen.dart';
import 'package:jewelry_hub/features/orders/ui/customer_order_timeline_screen.dart';
import 'package:jewelry_hub/features/orders/ui/receipt_detail_screen.dart';
import 'package:provider/provider.dart';

class CustomerOrdersScreen extends StatefulWidget {
  const CustomerOrdersScreen({super.key});

  @override
  State<CustomerOrdersScreen> createState() => _CustomerOrdersScreenState();
}

class _CustomerOrdersScreenState extends State<CustomerOrdersScreen> {
  final NumberFormat _money = NumberFormat('#,##0.00');
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _orders = [];
  final Map<int, List<PaymentReceipt>> _receipts = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final orders = await OrderApi.fetchOrders(token);
      final receiptMap = <int, List<PaymentReceipt>>{};
      for (final order in orders) {
        final idRaw = order['id'];
        final id = idRaw is num ? idRaw.toInt() : int.tryParse('$idRaw');
        if (id == null) continue;
        receiptMap[id] = await OrderApi.fetchReceipts(
          token: token,
          orderId: id,
        );
      }
      if (!mounted) return;
      setState(() {
        _orders = orders;
        _receipts
          ..clear()
          ..addAll(receiptMap);
        _loading = false;
      });
    } on OrderApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: PremiumText.headline('Orders & Receipts', maxLines: 1),
        actions: [
          IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded, color: AppColors.gold),
          ),
        ],
      ),
      extendBodyBehindAppBar: true,
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: _loading
              ? const Center(
                  child: CircularProgressIndicator(
                    color: AppColors.gold,
                    strokeWidth: 2,
                  ),
                )
              : _error != null
              ? Padding(
                  padding: const EdgeInsets.all(20),
                  child: AppGlassCard(
                    child: PremiumText.body(
                      _error!,
                      color: AppColors.textSecondary,
                    ),
                  ),
                )
              : _orders.isEmpty
              ? Padding(
                  padding: const EdgeInsets.all(20),
                  child: AppGlassCard(
                    child: PremiumText.body(
                      'No orders yet.',
                      color: AppColors.textSecondary,
                    ),
                  ),
                )
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
                  itemCount: _orders.length,
                  separatorBuilder: (_, i) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final order = _orders[index];
                    final idRaw = order['id'];
                    final orderId = idRaw is num
                        ? idRaw.toInt()
                        : int.tryParse('$idRaw') ?? 0;
                    final status = (order['status'] ?? '-').toString();
                    final orderNumber = (order['order_number'] ?? '#$orderId')
                        .toString();
                    final totalRaw = order['total_mmk'];
                    final total = totalRaw is num
                        ? totalRaw.toDouble()
                        : double.tryParse('$totalRaw') ?? 0;
                    final receipts = _receipts[orderId] ?? const [];
                    final quote = order['quote'] is Map
                        ? Map<String, dynamic>.from(order['quote'] as Map)
                        : const <String, dynamic>{};
                    final inquiryRaw = quote['inquiry_id'];
                    final inquiryId = inquiryRaw is num
                        ? inquiryRaw.toInt()
                        : int.tryParse('$inquiryRaw');

                    return AppGlassCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          PremiumText.body('Order $orderNumber'),
                          const SizedBox(height: 4),
                          PremiumText.caption(
                            status.replaceAll('_', ' '),
                            color: AppColors.goldMuted,
                          ),
                          const SizedBox(height: 8),
                          PremiumText.body(
                            'Total: ${_money.format(total)} MMK',
                            color: AppColors.textSecondary,
                          ),
                          const SizedBox(height: 10),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              ActionChip(
                                label: const Text('View details'),
                                onPressed: () {
                                  Navigator.of(context).push<void>(
                                    MaterialPageRoute(
                                      builder: (_) => CustomerOrderDetailScreen(
                                        orderId: orderId,
                                        orderNumber: orderNumber,
                                      ),
                                    ),
                                  );
                                },
                              ),
                              ActionChip(
                                label: const Text('View timeline'),
                                onPressed: () {
                                  Navigator.of(context).push<void>(
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          CustomerOrderTimelineScreen(
                                            orderId: orderId,
                                            orderNumber: orderNumber,
                                            orderStatus: status,
                                            inquiryId: inquiryId,
                                          ),
                                    ),
                                  );
                                },
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          if (receipts.isEmpty)
                            PremiumText.caption(
                              'No formal receipt issued yet.',
                              color: AppColors.textSecondary,
                            )
                          else
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: receipts.map((receipt) {
                                final isFinal = receipt.paymentType == 'final';
                                return ActionChip(
                                  label: Text(
                                    isFinal
                                        ? '${receipt.receiptNumber} · Final'
                                        : '${receipt.receiptNumber} · Deposit',
                                  ),
                                  onPressed: () {
                                    Navigator.of(context).push<void>(
                                      MaterialPageRoute(
                                        builder: (_) => ReceiptDetailScreen(
                                          receipt: receipt,
                                        ),
                                      ),
                                    );
                                  },
                                );
                              }).toList(),
                            ),
                        ],
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
