import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/notifications/ui/notifications_screen.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_floating_nav_bar.dart';
import 'package:jewelry_hub/features/artisan/ui/artisan_jobs_screen.dart';

/// Artisan home: workshop job list (Dark Gold shell).
class ArtisanShell extends StatefulWidget {
  const ArtisanShell({super.key, required this.navigatorKey});

  final GlobalKey<NavigatorState> navigatorKey;

  @override
  State<ArtisanShell> createState() => _ArtisanShellState();
}

class _ArtisanShellState extends State<ArtisanShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: IndexedStack(
        index: _index,
        children: [
          const ArtisanJobsScreen(),
          NotificationsScreen(navigatorKey: widget.navigatorKey),
        ],
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.only(bottom: 2),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
          child: PremiumFloatingNavBar(
            selectedIndex: _index,
            onDestinationSelected: (i) => setState(() => _index = i),
            borderStyle: PremiumFloatingNavBorderStyle.none,
            destinations: const [
              PremiumNavDestinationData(
                icon: Icons.construction_outlined,
                selectedIcon: Icons.construction_rounded,
                label: 'Jobs',
              ),
              PremiumNavDestinationData(
                icon: Icons.notifications_none_rounded,
                selectedIcon: Icons.notifications_rounded,
                label: 'Alerts',
              ),
            ],
          ),
        ),
      ),
    );
  }
}
