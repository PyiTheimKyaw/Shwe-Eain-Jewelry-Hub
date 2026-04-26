import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_refresh.dart';
import 'package:jewelry_hub/core/ui/widgets/brand_logo.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/artisan/data/artisan_api.dart';
import 'package:jewelry_hub/features/artisan/ui/artisan_job_detail_screen.dart';
import 'package:jewelry_hub/features/artisan/ui/artisan_profile_stats_screen.dart';
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

  Future<void> _load({bool silent = false}) async {
    final token = context.read<AuthProvider>().token;
    if (token == null) {
      return;
    }
    if (!silent) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final list = await ArtisanApi.fetchAssignments(token);
      if (mounted) {
        setState(() {
          _items = list;
          _loading = false;
          _error = null;
        });
      }
    } on ArtisanApiException catch (e) {
      if (mounted) {
        if (silent && _items.isNotEmpty) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(e.message)),
          );
        } else {
          setState(() {
            _error = e.message;
            _loading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        if (silent && _items.isNotEmpty) {
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
                    const BrandLogo(height: 28),
                    const SizedBox(width: 10),
                    Expanded(
                      child: PremiumText.headline('Workshop jobs', maxLines: 1),
                    ),
                    IconButton(
                      icon: const Icon(
                        Icons.tune_rounded,
                        color: AppColors.textPrimary,
                      ),
                      tooltip: 'Settings',
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => const ArtisanProfileStatsScreen(),
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ),
              Expanded(
                child: RefreshIndicator(
                  color: AppRefresh.indicatorColor,
                  strokeWidth: AppRefresh.strokeWidth,
                  onRefresh: () => _load(silent: true),
                  child: CustomScrollView(
                    physics: AppRefresh.scrollPhysics,
                    slivers: [
                      if (_loading && _items.isEmpty)
                        const SliverFillRemaining(
                          hasScrollBody: false,
                          child: Center(
                            child: CircularProgressIndicator(
                              color: AppColors.gold,
                              strokeWidth: 2,
                            ),
                          ),
                        )
                      else if (_error != null && _items.isEmpty)
                        SliverFillRemaining(
                          hasScrollBody: false,
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
                      else if (_items.isEmpty)
                        SliverFillRemaining(
                          hasScrollBody: false,
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: AppGlassCard(
                              child: PremiumText.body(
                                'No assignments right now.',
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ),
                        )
                      else
                        SliverPadding(
                          padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                          sliver: SliverList(
                            delegate: SliverChildBuilderDelegate(
                              (context, i) {
                                final a = _items[i];
                                final order =
                                    a['order'] as Map<String, dynamic>?;
                                final num =
                                    order?['order_number'] as String? ?? '';
                                final status = a['status'] as String? ?? '';
                                final id = a['id'] as int? ?? 0;
                                return Padding(
                                  padding: EdgeInsets.only(
                                    bottom: i < _items.length - 1 ? 10 : 0,
                                  ),
                                  child: InkWell(
                                    onTap: () async {
                                      await Navigator.of(context).push<void>(
                                        MaterialPageRoute(
                                          builder: (_) =>
                                              ArtisanJobDetailScreen(
                                            assignmentId: id,
                                          ),
                                        ),
                                      );
                                      await _load();
                                    },
                                    borderRadius:
                                        BorderRadius.circular(AppRadii.md),
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
                              childCount: _items.length,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
