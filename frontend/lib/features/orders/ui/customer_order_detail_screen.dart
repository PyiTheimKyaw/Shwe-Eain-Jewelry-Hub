import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/storage/public_storage_url.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/orders/data/order_api.dart';
import 'package:jewelry_hub/features/orders/models/payment_receipt.dart';
import 'package:jewelry_hub/features/orders/ui/receipt_detail_screen.dart';
import 'package:provider/provider.dart';

class CustomerOrderDetailScreen extends StatefulWidget {
  const CustomerOrderDetailScreen({
    super.key,
    required this.orderId,
    required this.orderNumber,
  });

  final int orderId;
  final String orderNumber;

  @override
  State<CustomerOrderDetailScreen> createState() =>
      _CustomerOrderDetailScreenState();
}

class _CustomerOrderDetailScreenState extends State<CustomerOrderDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _order;

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
      final order = await OrderApi.fetchOrderDetail(
        token: token,
        orderId: widget.orderId,
      );
      if (!mounted) return;
      setState(() {
        _order = order;
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
    final itemsRaw = _order?['items'];
    final items = (itemsRaw is List ? itemsRaw : const <dynamic>[])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList(growable: false);
    final assignmentRaw = _order?['assignments'];
    final assignments =
        (assignmentRaw is List ? assignmentRaw : const <dynamic>[])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList(growable: false);
    final latestInstructions = assignments.isEmpty
        ? ''
        : ((assignments.first['instructions'] ?? '') as String).trim();
    final quoteRaw = _order?['quote'];
    final quote = quoteRaw is Map
        ? Map<String, dynamic>.from(quoteRaw)
        : const <String, dynamic>{};
    final inquiryRaw = quote['inquiry'];
    final inquiry = inquiryRaw is Map
        ? Map<String, dynamic>.from(inquiryRaw)
        : const <String, dynamic>{};
    final initialAttachmentUrls =
        ((inquiry['attachments'] as List<dynamic>?) ?? const [])
            .whereType<Map>()
            .map((e) => (e['url'] ?? e['storage_path'] ?? '').toString().trim())
            .where((path) => path.isNotEmpty)
            .map(publicStorageUrl)
            .toList(growable: false);
    final receiptsRaw = _order?['receipts'];
    final receipts = (receiptsRaw is List ? receiptsRaw : const <dynamic>[])
        .whereType<Map>()
        .map((e) => PaymentReceipt.fromJson(Map<String, dynamic>.from(e)))
        .toList(growable: false);

    return Scaffold(
      appBar: AppBar(
        title: PremiumText.headline('Order ${widget.orderNumber}', maxLines: 1),
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
              : ListView(
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
                  children: [
                    AppGlassCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          PremiumText.caption(
                            'ORDER STATUS',
                            color: AppColors.goldMuted,
                          ),
                          const SizedBox(height: 6),
                          PremiumText.body(
                            ((_order?['status'] ?? '-') as String).replaceAll(
                              '_',
                              ' ',
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    AppGlassCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          PremiumText.caption(
                            'OFFICIAL RECEIPTS',
                            color: AppColors.goldMuted,
                          ),
                          const SizedBox(height: 8),
                          if (receipts.isEmpty)
                            PremiumText.body(
                              'No receipts issued yet.',
                              color: AppColors.textSecondary,
                            )
                          else
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: receipts
                                  .map((receipt) {
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
                                            builder: (_) => ReceiptDetailScreen(
                                              receipt: receipt,
                                            ),
                                          ),
                                        );
                                      },
                                    );
                                  })
                                  .toList(growable: false),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    if (latestInstructions.isNotEmpty)
                      AppGlassCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            PremiumText.caption(
                              'ADMIN INSTRUCTIONS',
                              color: AppColors.goldMuted,
                            ),
                            const SizedBox(height: 6),
                            PremiumText.body(
                              latestInstructions,
                              color: AppColors.textSecondary,
                            ),
                          ],
                        ),
                      ),
                    if (latestInstructions.isNotEmpty)
                      const SizedBox(height: 12),
                    if (initialAttachmentUrls.isNotEmpty)
                      AppGlassCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            PremiumText.caption(
                              'INITIAL INQUIRY PHOTOS',
                              color: AppColors.goldMuted,
                            ),
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: initialAttachmentUrls
                                  .map(
                                    (url) => ClipRRect(
                                      borderRadius: BorderRadius.circular(
                                        AppRadii.sm,
                                      ),
                                      child: CachedNetworkImage(
                                        imageUrl: url,
                                        width: 88,
                                        height: 88,
                                        fit: BoxFit.cover,
                                      ),
                                    ),
                                  )
                                  .toList(growable: false),
                            ),
                          ],
                        ),
                      ),
                    if (initialAttachmentUrls.isNotEmpty)
                      const SizedBox(height: 12),
                    ...items.map((item) {
                      final title = (item['title_snapshot'] ?? 'Order item')
                          .toString();
                      final weight = (item['weight_grams'] ?? '').toString();
                      final metal = (item['metal_type_snapshot'] ?? '')
                          .toString()
                          .trim();
                      final stone = (item['stone_details_snapshot'] ?? '')
                          .toString()
                          .trim();
                      final product = item['product'];
                      final productMap = product is Map
                          ? Map<String, dynamic>.from(product)
                          : const <String, dynamic>{};
                      final imagesRaw = productMap['images'];
                      final imageUrls =
                          (imagesRaw is List ? imagesRaw : const <dynamic>[])
                              .whereType<Map>()
                              .map(
                                (img) => (img['storage_path'] ?? '').toString(),
                              )
                              .where((path) => path.trim().isNotEmpty)
                              .map(publicStorageUrl)
                              .toList(growable: false);
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: AppGlassCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              PremiumText.body(title),
                              const SizedBox(height: 10),
                              PremiumText.caption(
                                'Weight',
                                color: AppColors.textSecondary,
                              ),
                              PremiumText.body(
                                weight.isEmpty ? '-' : '$weight g',
                                color: AppColors.textSecondary,
                              ),
                              const SizedBox(height: 8),
                              PremiumText.caption(
                                'Metal type',
                                color: AppColors.textSecondary,
                              ),
                              PremiumText.body(
                                metal.isEmpty ? '-' : metal,
                                color: AppColors.textSecondary,
                              ),
                              const SizedBox(height: 8),
                              PremiumText.caption(
                                'Stone details',
                                color: AppColors.textSecondary,
                              ),
                              PremiumText.body(
                                stone.isEmpty ? '-' : stone,
                                color: AppColors.textSecondary,
                              ),
                              const SizedBox(height: 10),
                              PremiumText.caption(
                                'DESIGN PHOTOS',
                                color: AppColors.goldMuted,
                              ),
                              const SizedBox(height: 8),
                              if (imageUrls.isEmpty)
                                PremiumText.body(
                                  'No design photos attached.',
                                  color: AppColors.textSecondary,
                                )
                              else
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: imageUrls
                                      .map(
                                        (url) => ClipRRect(
                                          borderRadius: BorderRadius.circular(
                                            AppRadii.sm,
                                          ),
                                          child: CachedNetworkImage(
                                            imageUrl: url,
                                            width: 88,
                                            height: 88,
                                            fit: BoxFit.cover,
                                          ),
                                        ),
                                      )
                                      .toList(growable: false),
                                ),
                            ],
                          ),
                        ),
                      );
                    }),
                    if (items.isEmpty)
                      AppGlassCard(
                        child: PremiumText.body(
                          'No item details available yet.',
                          color: AppColors.textSecondary,
                        ),
                      ),
                  ],
                ),
        ),
      ),
    );
  }
}
