import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/notifications/ui/notifications_screen.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
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
      body: IndexedStack(
        index: _index,
        children: [
          const ArtisanJobsScreen(),
          NotificationsScreen(navigatorKey: widget.navigatorKey),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        backgroundColor: AppColors.surface.withValues(alpha: 0.95),
        indicatorColor: AppColors.gold.withValues(alpha: 0.22),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.construction_outlined),
            selectedIcon: Icon(Icons.construction_rounded),
            label: 'Jobs',
          ),
          NavigationDestination(
            icon: Icon(Icons.notifications_none_rounded),
            selectedIcon: Icon(Icons.notifications_rounded),
            label: 'Alerts',
          ),
        ],
      ),
    );
  }
}
