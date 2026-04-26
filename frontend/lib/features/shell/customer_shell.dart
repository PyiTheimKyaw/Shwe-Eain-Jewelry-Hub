import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/notifications/customer_notification_badge.dart';
import 'package:jewelry_hub/core/notifications/ui/notifications_screen.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_floating_nav_bar.dart';
import 'package:jewelry_hub/features/catalog/ui/product_catalog_screen.dart';
import 'package:jewelry_hub/features/inquiries/ui/inquiry_list_screen.dart';
import 'package:jewelry_hub/features/orders/ui/customer_orders_screen.dart';
import 'package:jewelry_hub/features/profile/ui/customer_profile_screen.dart';
import 'package:provider/provider.dart';

/// Bottom navigation: catalog + inquiries (premium dark shell).
class CustomerShell extends StatefulWidget {
  const CustomerShell({super.key, required this.navigatorKey});

  final GlobalKey<NavigatorState> navigatorKey;

  @override
  State<CustomerShell> createState() => _CustomerShellState();
}

class _CustomerShellState extends State<CustomerShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final unreadAlerts = context.watch<CustomerNotificationBadge>().hasUnread;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: IndexedStack(
        index: _index,
        children: [
          const ProductCatalogScreen(),
          const InquiryListScreen(),
          const CustomerOrdersScreen(),
          NotificationsScreen(navigatorKey: widget.navigatorKey),
          const CustomerProfileScreen(),
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
            destinations: [
              const PremiumNavDestinationData(
                icon: Icons.storefront_outlined,
                selectedIcon: Icons.storefront_rounded,
                label: 'Catalog',
              ),
              const PremiumNavDestinationData(
                icon: Icons.forum_outlined,
                selectedIcon: Icons.forum_rounded,
                label: 'Inquiries',
              ),
              const PremiumNavDestinationData(
                icon: Icons.receipt_long_outlined,
                selectedIcon: Icons.receipt_long_rounded,
                label: 'Orders',
              ),
              PremiumNavDestinationData(
                icon: Icons.notifications_none_rounded,
                selectedIcon: Icons.notifications_rounded,
                label: 'Alerts',
                iconOverlay: unreadAlerts
                    ? const _CustomerNavUnreadDot()
                    : null,
              ),
              const PremiumNavDestinationData(
                icon: Icons.person_outline_rounded,
                selectedIcon: Icons.person_rounded,
                label: 'Profile',
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CustomerNavUnreadDot extends StatelessWidget {
  const _CustomerNavUnreadDot();

  @override
  Widget build(BuildContext context) {
    return Positioned(
      right: 0,
      top: -3,
      child: Container(
        width: 8,
        height: 8,
        decoration: BoxDecoration(
          color: AppColors.gold,
          shape: BoxShape.circle,
          border: Border.all(
            color: AppColors.background,
            width: 1.5,
          ),
          boxShadow: [
            BoxShadow(
              color: AppColors.gold.withValues(alpha: 0.5),
              blurRadius: 5,
            ),
          ],
        ),
      ),
    );
  }
}
