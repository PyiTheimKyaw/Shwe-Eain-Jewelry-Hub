import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/storage/public_storage_url.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/inquiries/data/inquiry_api.dart';
import 'package:jewelry_hub/features/orders/data/order_api.dart';
import 'package:jewelry_hub/features/orders/models/order_progress_event.dart';
import 'package:jewelry_hub/features/orders/models/order_timeline_item.dart';
import 'package:jewelry_hub/features/orders/ui/customer_order_detail_screen.dart';
import 'package:provider/provider.dart';

class CustomerOrderTimelineScreen extends StatefulWidget {
  const CustomerOrderTimelineScreen({
    super.key,
    required this.orderId,
    required this.orderNumber,
    required this.orderStatus,
    this.inquiryId,
  });

  final int orderId;
  final String orderNumber;
  final String orderStatus;
  final int? inquiryId;

  @override
  State<CustomerOrderTimelineScreen> createState() =>
      _CustomerOrderTimelineScreenState();
}

class _CustomerOrderTimelineScreenState
    extends State<CustomerOrderTimelineScreen> {
  final DateFormat _dateFmt = DateFormat('MMM d, y · h:mm a');
  bool _loading = true;
  String? _error;
  List<OrderTimelineItem> _items = const [];

  bool get _isCompleted => widget.orderStatus.trim().toLowerCase() == 'completed';

  String get _statusLabel {
    final normalized = widget.orderStatus.trim().toLowerCase();
    if (normalized == 'completed') {
      return 'Order Completed';
    }
    return widget.orderStatus.replaceAll('_', ' ');
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) {
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final progressRaw = await OrderApi.fetchOrderProgress(
        token: token,
        orderId: widget.orderId,
      );
      final progress = progressRaw
          .map(
            (e) => OrderProgressEvent.fromJson(
              e,
              storageUrlBuilder: publicStorageUrl,
            ),
          )
          .toList(growable: false);

      final timeline = <OrderTimelineItem>[];
      final inquiryId = widget.inquiryId;
      if (inquiryId != null) {
        final pipeline = await InquiryApi.fetchPipeline(
          bearerToken: token,
          inquiryId: inquiryId,
        );
        final rawTimeline = pipeline?.timelineLogs ?? const [];
        for (final item in rawTimeline) {
          final text = (item['message'] ?? '').toString().trim();
          if (text.isEmpty) {
            continue;
          }
          final at = DateTime.tryParse((item['occurred_at'] ?? '').toString());
          timeline.add(
            OrderTimelineItem.milestone(
              title: text,
              caption: 'Workflow milestone',
              at: at,
            ),
          );
        }
      } else {
        timeline.add(
          OrderTimelineItem.milestone(
            title: 'Order created from accepted quote',
            caption: 'Workflow milestone',
            at: null,
          ),
        );
      }

      for (final event in progress) {
        timeline.add(OrderTimelineItem.artisanUpdate(event));
      }

      timeline.sort((a, b) {
        final aAt = a.at;
        final bAt = b.at;
        if (aAt == null && bAt == null) {
          return 0;
        }
        if (aAt == null) {
          return -1;
        }
        if (bAt == null) {
          return 1;
        }
        return aAt.compareTo(bAt);
      });

      if (!mounted) {
        return;
      }
      setState(() {
        _items = timeline;
        _loading = false;
      });
    } on InquiryApiException catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } on OrderApiException catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }
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
        title: PremiumText.headline(
          'Timeline ${widget.orderNumber}',
          maxLines: 1,
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: AppColors.gold),
            onPressed: _load,
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
                          if (_isCompleted)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(AppRadii.sm),
                                border: Border.all(
                                  color: AppColors.gold.withValues(alpha: 0.8),
                                ),
                                gradient: const LinearGradient(
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                  colors: [Color(0x44D4AF37), Color(0x1AD4AF37)],
                                ),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(
                                    Icons.verified_rounded,
                                    size: 16,
                                    color: AppColors.gold,
                                  ),
                                  const SizedBox(width: 6),
                                  PremiumText.caption(
                                    _statusLabel,
                                    color: AppColors.gold,
                                  ),
                                ],
                              ),
                            )
                          else
                            PremiumText.body(_statusLabel),
                          const SizedBox(height: 10),
                          ActionChip(
                            label: const Text('View order details'),
                            onPressed: () {
                              Navigator.of(context).push<void>(
                                MaterialPageRoute(
                                  builder: (_) => CustomerOrderDetailScreen(
                                    orderId: widget.orderId,
                                    orderNumber: widget.orderNumber,
                                  ),
                                ),
                              );
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    if (_items.isEmpty)
                      AppGlassCard(
                        child: PremiumText.caption(
                          'No timeline updates yet.',
                          color: AppColors.textSecondary,
                        ),
                      )
                    else
                      ..._items.map(_buildTimelineCard),
                  ],
                ),
        ),
      ),
    );
  }

  Widget _buildTimelineCard(OrderTimelineItem item) {
    final atLabel = item.at != null
        ? _dateFmt.format(item.at!.toLocal())
        : null;
    final event = item.progressEvent;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AppGlassCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            PremiumText.body(item.title),
            if (item.caption != null && item.caption!.trim().isNotEmpty) ...[
              const SizedBox(height: 4),
              PremiumText.caption(
                item.caption!,
                color: AppColors.textSecondary,
              ),
            ],
            if (event?.artisanName != null) ...[
              const SizedBox(height: 4),
              PremiumText.caption(
                'By ${event!.artisanName}',
                color: AppColors.textSecondary,
              ),
            ],
            if (atLabel != null) ...[
              const SizedBox(height: 4),
              PremiumText.caption(
                atLabel,
                color: AppColors.textSecondary.withValues(alpha: 0.9),
              ),
            ],
            if (event != null && event.attachmentUrls.isNotEmpty) ...[
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: event.attachmentUrls
                    .map(
                      (url) => ClipRRect(
                        borderRadius: BorderRadius.circular(AppRadii.sm),
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
          ],
        ),
      ),
    );
  }
}
