import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/notifications/ui/notifications_screen.dart';

class AdminShell extends StatelessWidget {
  const AdminShell({super.key, required this.navigatorKey});

  final GlobalKey<NavigatorState> navigatorKey;

  @override
  Widget build(BuildContext context) {
    return NotificationsScreen(navigatorKey: navigatorKey);
  }
}
