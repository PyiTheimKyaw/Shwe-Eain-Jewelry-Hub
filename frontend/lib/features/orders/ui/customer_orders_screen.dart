import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_refresh.dart';
import 'package:jewelry_hub/core/ui/widgets/customer_tab_header.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/orders/widgets/order_status_badge.dart';
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
  final NumberFormat _moneyMmk = NumberFormat('#,##0');
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _orders = [];
  final Map<int, List<PaymentReceipt>> _receipts = {};
  DateTime? _lastSyncedAt;
  bool _showingCached = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load({bool silent = false}) async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    if (!silent) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final orderResult = await OrderApi.fetchOrdersCached(token);
      final orders = orderResult.orders;
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
        _lastSyncedAt = orderResult.lastSyncedAt;
        _showingCached = orderResult.fromCache;
        _loading = false;
        _error = null;
      });
    } on OrderApiException catch (e) {
      if (!mounted) return;
      if (silent) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message)),
        );
      } else {
        setState(() {
          _error = e.message;
          _loading = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      if (silent) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$e')),
        );
      } else {
        setState(() {
          _error = '$e';
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final syncFmt = DateFormat('MMM d, HH:mm');
    return Scaffold(
      appBar: CustomerTabHeader.appBar(title: 'Orders'),
      extendBodyBehindAppBar: true,
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: RefreshIndicator(
            color: AppRefresh.indicatorColor,
            strokeWidth: AppRefresh.strokeWidth,
            onRefresh: () => _load(silent: true),
            child: CustomScrollView(
              physics: AppRefresh.scrollPhysics,
              slivers: [
                if (_loading && _orders.isEmpty)
                  const SliverFillRemaining(
                    hasScrollBody: false,
                    child: Center(
                      child: CircularProgressIndicator(
                        color: AppColors.gold,
                        strokeWidth: 2,
                      ),
                    ),
                  )
                else if (_error != null && _orders.isEmpty)
                  SliverFillRemaining(
                    hasScrollBody: false,
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: AppGlassCard(
                        child: PremiumText.body(
                          _error!,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ),
                  )
                else if (_orders.isEmpty)
                  SliverFillRemaining(
                    hasScrollBody: false,
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: AppGlassCard(
                        child: PremiumText.body(
                          'No orders yet.',
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
                    sliver: SliverList(
                      delegate: SliverChildBuilderDelegate(
                        (context, index) {
                          if (_lastSyncedAt != null && index == 0) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: PremiumText.caption(
                                _showingCached
                                    ? 'Offline view · Last synced ${syncFmt.format(_lastSyncedAt!)}'
                                    : 'Last synced ${syncFmt.format(_lastSyncedAt!)}',
                                color: AppColors.textSecondary,
                              ),
                            );
                          }
                          final dataIndex =
                              _lastSyncedAt != null ? index - 1 : index;
                          final order = _orders[dataIndex];
                          final idRaw = order['id'];
                          final orderId = idRaw is num
                              ? idRaw.toInt()
                              : int.tryParse('$idRaw') ?? 0;
                          final status =
                              (order['status'] ?? '-').toString();
                          final orderNumber =
                              (order['order_number'] ?? '#$orderId')
                                  .toString();
                          final totalRaw = order['total_mmk'];
                          final total = totalRaw is num
                              ? totalRaw.toDouble()
                              : double.tryParse('$totalRaw') ?? 0;
                          final receipts = _receipts[orderId] ?? const [];
                          final quote = order['quote'] is Map
                              ? Map<String, dynamic>.from(
                                  order['quote'] as Map,
                                )
                              : const <String, dynamic>{};
                          final inquiryRaw = quote['inquiry_id'];
                          final inquiryId = inquiryRaw is num
                              ? inquiryRaw.toInt()
                              : int.tryParse('$inquiryRaw');

                          return Padding(
                            padding: EdgeInsets.only(
                              bottom: dataIndex < _orders.length - 1 ? 10 : 0,
                            ),
                            child: AppGlassCard(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  PremiumText.body('Order $orderNumber'),
                                  const SizedBox(height: 8),
                                  OrderStatusBadge(status: status),
                                  const SizedBox(height: 8),
                                  PremiumText.body(
                                    'Total: ${_moneyMmk.format(total)} MMK',
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
                                              builder: (_) =>
                                                  CustomerOrderDetailScreen(
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
                                        final isFinal =
                                            receipt.paymentType == 'final';
                                        return ActionChip(
                                          label: Text(
                                            isFinal
                                                ? '${receipt.receiptNumber} · Final'
                                                : '${receipt.receiptNumber} · Deposit',
                                          ),
                                          onPressed: () {
                                            Navigator.of(context).push<void>(
                                              MaterialPageRoute(
                                                builder: (_) =>
                                                    ReceiptDetailScreen(
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
                            ),
                          );
                        },
                        childCount: _orders.length + (_lastSyncedAt != null ? 1 : 0),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
