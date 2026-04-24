import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/artisan/data/artisan_api.dart';
import 'package:jewelry_hub/features/artisan/ui/artisan_job_detail_screen.dart';
import 'package:jewelry_hub/features/artisan/ui/artisan_notifications_screen.dart';
import 'package:provider/provider.dart';

class ArtisanJobsScreen extends StatefulWidget {
  const ArtisanJobsScreen({super.key});

  @override
  State<ArtisanJobsScreen> createState() => _ArtisanJobsScreenState();
}

class _ArtisanJobsScreenState extends State<ArtisanJobsScreen> {
  List<Map<String, dynamic>> _items = [];
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
      final list = await ArtisanApi.fetchAssignments(token);
      if (mounted) {
        setState(() {
          _items = list;
          _loading = false;
        });
      }
    } on ArtisanApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = '$e';
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: Row(
                  children: [
                    PremiumText.headline('Workshop jobs', maxLines: 1),
                    const Spacer(),
                    IconButton(
                      onPressed: () {
                        Navigator.of(context).push<void>(
                          MaterialPageRoute(
                            builder: (_) => const ArtisanNotificationsScreen(),
                          ),
                        );
                      },
                      icon: const Icon(
                        Icons.notifications_none_rounded,
                        color: AppColors.gold,
                      ),
                    ),
                    IconButton(
                      onPressed: _load,
                      icon: const Icon(
                        Icons.refresh_rounded,
                        color: AppColors.gold,
                      ),
                    ),
                  ],
                ),
              ),
              if (_loading)
                const Expanded(
                  child: Center(
                    child: CircularProgressIndicator(
                      color: AppColors.gold,
                      strokeWidth: 2,
                    ),
                  ),
                )
              else if (_error != null)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: AppGlassCard(
                      child: PremiumText.body(
                        _error!,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                )
              else
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                    itemCount: _items.length,
                    itemBuilder: (context, i) {
                      final a = _items[i];
                      final order = a['order'] as Map<String, dynamic>?;
                      final num = order?['order_number'] as String? ?? '';
                      final status = a['status'] as String? ?? '';
                      final id = a['id'] as int? ?? 0;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: InkWell(
                          onTap: () async {
                            await Navigator.of(context).push<void>(
                              MaterialPageRoute(
                                builder: (_) =>
                                    ArtisanJobDetailScreen(assignmentId: id),
                              ),
                            );
                            await _load();
                          },
                          borderRadius: BorderRadius.circular(AppRadii.md),
                          child: AppGlassCard(
                            padding: const EdgeInsets.all(14),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      PremiumText.body(
                                        'Order $num',
                                        maxLines: 1,
                                      ),
                                      const SizedBox(height: 4),
                                      PremiumText.caption(
                                        status,
                                        color: AppColors.goldMuted,
                                      ),
                                    ],
                                  ),
                                ),
                                const Icon(
                                  Icons.chevron_right_rounded,
                                  color: AppColors.textSecondary,
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
