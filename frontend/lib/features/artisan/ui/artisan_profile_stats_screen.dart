import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/customer_tab_header.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/artisan/data/artisan_api.dart';
import 'package:jewelry_hub/features/profile/ui/change_password_screen.dart';
import 'package:jewelry_hub/features/profile/widgets/profile_settings_tile.dart';
import 'package:provider/provider.dart';

class ArtisanProfileStatsScreen extends StatefulWidget {
  const ArtisanProfileStatsScreen({super.key});

  @override
  State<ArtisanProfileStatsScreen> createState() =>
      _ArtisanProfileStatsScreenState();
}

class _ArtisanProfileStatsScreenState extends State<ArtisanProfileStatsScreen> {
  static String _initial(String name) {
    final t = name.trim();
    if (t.isEmpty) return '?';
    return t[0].toUpperCase();
  }

  bool _loading = true;
  String? _error;
  int _activeLoad = 0;
  int _blocked = 0;
  int _completed = 0;

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
      final stats = await ArtisanApi.fetchStats(token);
      if (!mounted) return;
      setState(() {
        _activeLoad = stats['current_active_load'] ?? 0;
        _blocked = stats['blocked_count'] ?? 0;
        _completed = stats['total_completed_jobs'] ?? 0;
        _loading = false;
      });
    } on ArtisanApiException catch (e) {
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

  Future<void> _logout() async {
    await context.read<AuthProvider>().logout();
    if (!mounted) return;
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    final busy = auth.isSessionBusy;
    final name = user?.name ?? 'Artisan';
    final email = user?.email ?? '';

    Widget statsBody() {
      if (_loading) {
        return const Center(
          child: CircularProgressIndicator(
            color: AppColors.gold,
            strokeWidth: 2,
          ),
        );
      }
      if (_error != null) {
        return PremiumText.body(
          _error!,
          color: AppColors.textSecondary,
        );
      }
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          PremiumText.body('Current active load: $_activeLoad'),
          const SizedBox(height: 6),
          PremiumText.body('Blocked jobs: $_blocked'),
          const SizedBox(height: 6),
          PremiumText.body('Total completed jobs: $_completed'),
        ],
      );
    }

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: RefreshIndicator(
            color: AppColors.gold,
            onRefresh: _load,
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                CustomerTabHeader.sliverAppBar(
                  title: 'Artisan',
                  backgroundOpacity: 0.92,
                ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  sliver: SliverList(
                    delegate: SliverChildListDelegate([
                      AppGlassCard(
                        child: Row(
                          children: [
                            Container(
                              width: 56,
                              height: 56,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: AppColors.gold.withValues(alpha: 0.75),
                                  width: 2,
                                ),
                                color: AppColors.surface.withValues(alpha: 0.6),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                _initial(name),
                                style: GoogleFonts.cinzelDecorative(
                                  fontSize: 22,
                                  color: AppColors.gold,
                                ),
                              ),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    name,
                                    style: GoogleFonts.outfit(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.textPrimary,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    email,
                                    style: GoogleFonts.outfit(
                                      fontSize: 13,
                                      color: AppColors.textSecondary,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),
                      PremiumText.caption(
                        'WORKLOAD',
                        color: AppColors.goldMuted,
                      ),
                      const SizedBox(height: 10),
                      AppGlassCard(child: statsBody()),
                      const SizedBox(height: 20),
                      PremiumText.caption(
                        'ACCOUNT',
                        color: AppColors.goldMuted,
                      ),
                      const SizedBox(height: 10),
                      ProfileSettingsTile(
                        icon: Icons.password_rounded,
                        title: 'Change password',
                        subtitle: 'Keep your artisan account secure',
                        onTap: () {
                          Navigator.of(context).push<void>(
                            MaterialPageRoute(
                              builder: (_) => const ChangePasswordScreen(),
                            ),
                          );
                        },
                      ),
                      const SizedBox(height: 28),
                      OutlinedButton(
                        onPressed: busy ? null : _logout,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.gold,
                          side: BorderSide(
                            color: AppColors.gold.withValues(alpha: 0.85),
                          ),
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(AppRadii.sm),
                          ),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.logout_rounded, size: 20),
                            const SizedBox(width: 8),
                            Text(
                              busy ? 'Signing out...' : 'Sign out',
                              style: GoogleFonts.outfit(
                                fontWeight: FontWeight.w600,
                                letterSpacing: 0.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 24),
                    ]),
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
