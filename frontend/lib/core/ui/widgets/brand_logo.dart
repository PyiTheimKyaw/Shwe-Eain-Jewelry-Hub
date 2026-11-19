import 'package:flutter/material.dart';

class BrandLogo extends StatelessWidget {
  const BrandLogo({
    super.key,
    this.height = 96,
    this.semanticLabel = 'Shwe Eain Jewelry Hub',
    this.useDarkThemeVariant = true,
  });

  final double height;
  final String semanticLabel;
  final bool useDarkThemeVariant;

  static const String lightAssetPath = 'assets/images/shwe_eain_logo.png';
  static const String darkAssetPath = 'assets/images/shwe_eain_logo_dark.png';

  @override
  Widget build(BuildContext context) {
    final isDark =
        useDarkThemeVariant && Theme.of(context).brightness == Brightness.dark;

    return Image.asset(
      isDark ? darkAssetPath : lightAssetPath,
      height: height,
      fit: BoxFit.contain,
      filterQuality: FilterQuality.high,
      semanticLabel: semanticLabel,
    );
  }
}
