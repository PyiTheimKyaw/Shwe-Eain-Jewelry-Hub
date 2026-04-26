import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/orders/ui/customer_orders_screen.dart';
import 'package:jewelry_hub/features/profile/ui/change_password_screen.dart';
import 'package:jewelry_hub/features/profile/ui/display_preferences_screen.dart';
import 'package:jewelry_hub/features/profile/ui/saved_products_screen.dart';
import 'package:jewelry_hub/features/profile/widgets/profile_settings_tile.dart';
import 'package:provider/provider.dart';

class CustomerProfileScreen extends StatelessWidget {
  const CustomerProfileScreen({super.key});

  static String _initial(String name) {
    final t = name.trim();
    if (t.isEmpty) return '?';
    return t[0].toUpperCase();
  }

  Future<void> _confirmSignOut(BuildContext context) async {
    final go = await showDialog<bool>(
      context: context,
      barrierColor: Colors.black54,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: AppColors.surfaceElevated,
          title: Text(
            'Sign out?',
            style: GoogleFonts.outfit(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w700,
            ),
          ),
          content: Text(
            'You will need to sign in again to view orders, inquiries, and alerts.',
            style: GoogleFonts.outfit(
              color: AppColors.textSecondary,
              height: 1.35,
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: Text(
                'Cancel',
                style: GoogleFonts.outfit(color: AppColors.textSecondary),
              ),
            ),
            TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: Text(
                'Sign out',
                style: GoogleFonts.outfit(
                  color: AppColors.gold,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        );
      },
    );
    if (go != true || !context.mounted) return;
    await context.read<AuthProvider>().logout();
  }

  Future<void> _confirmDelete(BuildContext context) async {
    final proceed = await showDialog<bool>(
      context: context,
      barrierColor: Colors.black54,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: AppColors.surfaceElevated,
          title: Text(
            'Delete account?',
            style: GoogleFonts.outfit(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w700,
            ),
          ),
          content: Text(
            'This permanently closes your profile. You will not be able to sign in again with this email. '
            'Order history remains with the shop for their records.',
            style: GoogleFonts.outfit(
              color: AppColors.textSecondary,
              height: 1.35,
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: Text(
                'Cancel',
                style: GoogleFonts.outfit(color: AppColors.textSecondary),
              ),
            ),
            TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: Text(
                'Continue',
                style: GoogleFonts.outfit(
                  color: const Color(0xFFFF6B6B),
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        );
      },
    );
    if (proceed != true || !context.mounted) return;

    final passwordController = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      barrierColor: Colors.black54,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: AppColors.surfaceElevated,
          title: Text(
            'Confirm with password',
            style: GoogleFonts.outfit(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w700,
            ),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Enter your current password to permanently delete this account.',
                style: GoogleFonts.outfit(
                  color: AppColors.textSecondary,
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: passwordController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Current password',
                  border: OutlineInputBorder(),
                ),
                onSubmitted: (_) {
                  if (passwordController.text.isNotEmpty) {
                    Navigator.pop(ctx, true);
                  }
                },
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: Text(
                'Cancel',
                style: GoogleFonts.outfit(color: AppColors.textSecondary),
              ),
            ),
            TextButton(
              onPressed: () {
                if (passwordController.text.trim().isEmpty) {
                  ScaffoldMessenger.of(ctx).showSnackBar(
                    const SnackBar(content: Text('Enter your password.')),
                  );
                  return;
                }
                Navigator.pop(ctx, true);
              },
              child: Text(
                'Delete account',
                style: GoogleFonts.outfit(
                  color: const Color(0xFFFF6B6B),
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        );
      },
    );
    if (ok != true || !context.mounted) {
      passwordController.dispose();
      return;
    }
    final auth = context.read<AuthProvider>();
    final closed = await auth.closeAccount(
      currentPassword: passwordController.text,
    );
    passwordController.dispose();
    if (!context.mounted) return;
    if (!closed) {
      final msg = auth.lastError ?? 'Could not close account.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final name = user?.name ?? 'Guest';
    final email = user?.email ?? '';

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverAppBar(
                pinned: true,
                backgroundColor: AppColors.background.withValues(alpha: 0.92),
                title: PremiumText.display('Profile'),
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
                      'ACCOUNT',
                      color: AppColors.goldMuted,
                    ),
                    const SizedBox(height: 10),
                    ProfileSettingsTile(
                      icon: Icons.tune_rounded,
                      title: 'Display preferences',
                      subtitle: 'Default currency & weight units',
                      onTap: () {
                        Navigator.of(context).push<void>(
                          MaterialPageRoute(
                            builder: (_) => const DisplayPreferencesScreen(),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 10),
                    ProfileSettingsTile(
                      icon: Icons.favorite_rounded,
                      title: 'Saved pieces',
                      subtitle: 'Your favorite catalog items',
                      onTap: () {
                        Navigator.of(context).push<void>(
                          MaterialPageRoute(
                            builder: (_) => const SavedProductsScreen(),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 10),
                    ProfileSettingsTile(
                      icon: Icons.receipt_long_rounded,
                      title: 'Order history',
                      subtitle: 'Orders, timelines & receipts',
                      onTap: () {
                        Navigator.of(context).push<void>(
                          MaterialPageRoute(
                            builder: (_) => const CustomerOrdersScreen(),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 20),
                    PremiumText.caption(
                      'SECURITY',
                      color: AppColors.goldMuted,
                    ),
                    const SizedBox(height: 10),
                    ProfileSettingsTile(
                      icon: Icons.password_rounded,
                      title: 'Change password',
                      onTap: () {
                        Navigator.of(context).push<void>(
                          MaterialPageRoute(
                            builder: (_) => const ChangePasswordScreen(),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 10),
                    ProfileSettingsTile(
                      icon: Icons.delete_forever_rounded,
                      title: 'Delete account',
                      subtitle: 'Permanently close this profile',
                      destructive: true,
                      trailing: Icons.chevron_right_rounded,
                      onTap: () => _confirmDelete(context),
                    ),
                    const SizedBox(height: 28),
                    OutlinedButton(
                      onPressed: () => _confirmSignOut(context),
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
                            'Sign out',
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
    );
  }
}
