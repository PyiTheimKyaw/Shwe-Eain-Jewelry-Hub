import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/notifications/push_notification_manager.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/providers/catalog_pricing_provider.dart';
import 'package:jewelry_hub/core/providers/display_preferences_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/brand_logo.dart';
import 'package:jewelry_hub/features/auth/ui/login_screen.dart';
import 'package:jewelry_hub/features/shell/admin_shell.dart';
import 'package:jewelry_hub/features/shell/artisan_shell.dart';
import 'package:jewelry_hub/features/shell/customer_shell.dart';
import 'package:jewelry_hub/firebase_options.dart';
import 'package:provider/provider.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  } catch (e, st) {
    debugPrint('Firebase.initializeApp failed: $e\n$st');
  }
  runApp(const JewelryHubApp());
}

final GlobalKey<NavigatorState> appNavigatorKey = GlobalKey<NavigatorState>();
final GlobalKey<ScaffoldMessengerState> appMessengerKey =
    GlobalKey<ScaffoldMessengerState>();

class JewelryHubApp extends StatelessWidget {
  const JewelryHubApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => DisplayPreferences()),
        ChangeNotifierProvider(create: (_) => CatalogPricing()),
      ],
      child: MaterialApp(
        title: 'Jewelry Hub',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.darkGold(),
        themeMode: ThemeMode.dark,
        navigatorKey: appNavigatorKey,
        scaffoldMessengerKey: appMessengerKey,
        home: const _AuthGate(),
      ),
    );
  }
}

class _AuthGate extends StatelessWidget {
  const _AuthGate();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    PushNotificationManager.instance.initialize(
      appNavigatorKey,
      appMessengerKey,
    );

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 240),
      switchInCurve: Curves.easeOut,
      switchOutCurve: Curves.easeIn,
      child: !auth.isBootstrapComplete
          ? const _BootstrapSplash(key: ValueKey('boot'))
          : auth.isSignedIn
          ? (auth.user?.role == 'artisan'
                ? ArtisanShell(
                    key: const ValueKey('artisan'),
                    navigatorKey: appNavigatorKey,
                  )
                : auth.user?.role == 'admin'
                ? AdminShell(
                    key: const ValueKey('admin'),
                    navigatorKey: appNavigatorKey,
                  )
                : CustomerShell(
                    key: const ValueKey('shell'),
                    navigatorKey: appNavigatorKey,
                  ))
          : const LoginScreen(key: ValueKey('login')),
    );
  }
}

class _BootstrapSplash extends StatelessWidget {
  const _BootstrapSplash({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        width: double.infinity,
        height: double.infinity,
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: const Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              BrandLogo(height: 164),
              SizedBox(height: 22),
              CircularProgressIndicator(
                color: AppColors.gold,
                strokeWidth: 2,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
