import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/notifications/data/notification_api.dart';
import 'package:jewelry_hub/core/notifications/models/app_notification_item.dart';
import 'package:jewelry_hub/core/notifications/push_notification_manager.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/brand_logo.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:provider/provider.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key, required this.navigatorKey});

  final GlobalKey<NavigatorState> navigatorKey;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final DateFormat _timeFmt = DateFormat('MMM d, h:mm a');
  List<AppNotificationItem> _items = [];
  bool _loading = true;
  String? _error;

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
      final list = await NotificationApi.fetchNotifications(token);
      if (!mounted) return;
      setState(() {
        _items = list;
        _loading = false;
      });
    } on NotificationApiException catch (e) {
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

  Future<void> _open(AppNotificationItem item) async {
    final auth = context.read<AuthProvider>();
    final token = auth.token;
    final role = auth.user?.role ?? 'customer';
    if (token != null && !item.isRead) {
      try {
        await NotificationApi.markRead(token: token, notificationId: item.id);
      } catch (_) {}
    }
    if (!mounted) return;
    setState(() {
      _items = _items
          .map(
            (n) => n.id == item.id
                ? AppNotificationItem(
                    id: n.id,
                    type: n.type,
                    data: n.data,
                    readAt: DateTime.now(),
                    createdAt: n.createdAt,
                  )
                : n,
          )
          .toList(growable: false);
    });

    PushNotificationManager.instance.openNotification(
      item,
      widget.navigatorKey,
      role,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leadingWidth: 56,
        leading: const Padding(
          padding: EdgeInsets.only(left: 12, top: 8, bottom: 8),
          child: BrandLogo(height: 28),
        ),
        title: PremiumText.headline('Notifications', maxLines: 1),
        backgroundColor: Colors.transparent,
        elevation: 0,
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
                  padding: const EdgeInsets.all(24),
                  child: AppGlassCard(
                    child: PremiumText.body(
                      _error!,
                      color: AppColors.textSecondary,
                    ),
                  ),
                )
              : _items.isEmpty
              ? Padding(
                  padding: const EdgeInsets.all(24),
                  child: AppGlassCard(
                    child: PremiumText.body(
                      'No notifications yet.',
                      color: AppColors.textSecondary,
                    ),
                  ),
                )
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 20),
                  itemCount: _items.length,
                  separatorBuilder: (_, index) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final item = _items[index];
                    return InkWell(
                      borderRadius: BorderRadius.circular(AppRadii.md),
                      onTap: () => _open(item),
                      child: AppGlassCard(
                        padding: const EdgeInsets.all(14),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 8,
                              height: 8,
                              margin: const EdgeInsets.only(top: 7),
                              decoration: BoxDecoration(
                                color: item.isRead
                                    ? AppColors.textSecondary
                                    : AppColors.gold,
                                shape: BoxShape.circle,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: PremiumText.body(item.title),
                                      ),
                                      const SizedBox(width: 8),
                                      PremiumText.caption(
                                        item.isRead ? 'Read' : 'Unread',
                                        color: item.isRead
                                            ? AppColors.textSecondary
                                            : AppColors.gold,
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  PremiumText.caption(
                                    item.body,
                                    color: AppColors.textSecondary,
                                  ),
                                  if (item.createdAt != null) ...[
                                    const SizedBox(height: 6),
                                    PremiumText.caption(
                                      _timeFmt.format(
                                        item.createdAt!.toLocal(),
                                      ),
                                      color: AppColors.goldMuted,
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            const SizedBox(width: 8),
                            const Icon(
                              Icons.chevron_right_rounded,
                              color: AppColors.textSecondary,
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
