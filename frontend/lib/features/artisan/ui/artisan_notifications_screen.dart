import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/artisan/data/artisan_api.dart';
import 'package:jewelry_hub/features/artisan/models/artisan_notification.dart';
import 'package:jewelry_hub/features/artisan/ui/artisan_job_detail_screen.dart';
import 'package:provider/provider.dart';

class ArtisanNotificationsScreen extends StatefulWidget {
  const ArtisanNotificationsScreen({super.key});

  @override
  State<ArtisanNotificationsScreen> createState() =>
      _ArtisanNotificationsScreenState();
}

class _ArtisanNotificationsScreenState extends State<ArtisanNotificationsScreen> {
  final DateFormat _timeFmt = DateFormat('MMM d, h:mm a');
  List<ArtisanNotificationItem> _items = [];
  bool _loading = true;
  String? _error;

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
      final list = await ArtisanApi.fetchNotifications(token);
      if (!mounted) {
        return;
      }
      setState(() {
        _items = list;
        _loading = false;
      });
    } on ArtisanApiException catch (e) {
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

  Future<void> _openLinkedJob(ArtisanNotificationItem item) async {
    final orderId = item.orderId;
    if (orderId == null) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No linked order found for this notification.')),
      );
      return;
    }

    final token = context.read<AuthProvider>().token;
    if (token == null) {
      return;
    }

    try {
      final assignments = await ArtisanApi.fetchAssignments(token);
      Map<String, dynamic>? target;
      for (final a in assignments) {
        final order = a['order'];
        if (order is Map<String, dynamic>) {
          final id = order['id'];
          final parsed = id is num ? id.toInt() : int.tryParse('$id');
          if (parsed == orderId) {
            target = a;
            final st = (a['status'] ?? '').toString();
            if (st == 'assigned' || st == 'in_progress') {
              break;
            }
          }
        }
      }

      if (target == null) {
        if (!mounted) {
          return;
        }
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No active job found for this order.')),
        );
        return;
      }

      final assignmentIdRaw = target['id'];
      final assignmentId = assignmentIdRaw is num
          ? assignmentIdRaw.toInt()
          : int.tryParse('$assignmentIdRaw');
      if (assignmentId == null) {
        if (!mounted) {
          return;
        }
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to open linked job.')),
        );
        return;
      }

      if (!mounted) {
        return;
      }
      await Navigator.of(context).push<void>(
        MaterialPageRoute(
          builder: (_) => ArtisanJobDetailScreen(assignmentId: assignmentId),
        ),
      );
      await _load();
    } on ArtisanApiException catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
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
                          separatorBuilder: (_, index) =>
                              const SizedBox(height: 10),
                          itemBuilder: (context, index) {
                            final item = _items[index];
                            final unread = item.readAt == null;
                            return InkWell(
                              borderRadius: BorderRadius.circular(AppRadii.md),
                              onTap: () => _openLinkedJob(item),
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
                                        color: unread
                                            ? AppColors.gold
                                            : AppColors.textSecondary,
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          PremiumText.body(item.title),
                                          const SizedBox(height: 4),
                                          PremiumText.caption(
                                            item.subtitle,
                                            color: AppColors.textSecondary,
                                          ),
                                          if (item.createdAt != null) ...[
                                            const SizedBox(height: 6),
                                            PremiumText.caption(
                                              _timeFmt.format(item.createdAt!.toLocal()),
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
