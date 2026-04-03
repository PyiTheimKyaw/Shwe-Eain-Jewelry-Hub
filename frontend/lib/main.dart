import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/providers/catalog_pricing_provider.dart';
import 'package:jewelry_hub/core/providers/display_preferences_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/features/auth/ui/login_screen.dart';
import 'package:jewelry_hub/features/catalog/ui/product_catalog_screen.dart';
import 'package:provider/provider.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const JewelryHubApp());
}

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

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 240),
      switchInCurve: Curves.easeOut,
      switchOutCurve: Curves.easeIn,
      child: !auth.isBootstrapComplete
          ? const _BootstrapSplash(key: ValueKey('boot'))
          : auth.isSignedIn
              ? const ProductCatalogScreen(key: ValueKey('catalog'))
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
          child: CircularProgressIndicator(
            color: AppColors.gold,
            strokeWidth: 2,
          ),
        ),
      ),
    );
  }
}
