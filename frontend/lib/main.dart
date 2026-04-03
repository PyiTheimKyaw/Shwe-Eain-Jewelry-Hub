import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/catalog_pricing_provider.dart';
import 'package:jewelry_hub/core/providers/display_preferences_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/features/catalog/ui/product_catalog_screen.dart';
import 'package:provider/provider.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const JewelryHubApp());
}

/// Customer catalog entry (auth / roles can branch here later).
class JewelryHubApp extends StatelessWidget {
  const JewelryHubApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => DisplayPreferences()),
        ChangeNotifierProvider(create: (_) => CatalogPricing()),
      ],
      child: MaterialApp(
        title: 'Jewelry Hub',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.darkGold(),
        themeMode: ThemeMode.dark,
        home: const ProductCatalogScreen(),
      ),
    );
  }
}
