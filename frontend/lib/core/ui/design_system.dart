import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Global Jewelry Hub design tokens — dark gold, luxury contrast.
/// New screens should import this file and reuse [AppColors], [AppRadii], [AppTheme].
abstract final class AppColors {
  static const Color gold = Color(0xFFD4AF37);
  static const Color goldMuted = Color(0xFFB8962E);
  static const Color background = Color(0xFF0F172A);
  static const Color surface = Color(0xFF1E293B);
  static const Color surfaceElevated = Color(0xFF334155);
  static const Color glassBorder = Color(0x4FD4AF37);
  static const Color textPrimary = Color(0xFFF8FAFC);
  static const Color textSecondary = Color(0xFF94A3B8);
}

abstract final class AppRadii {
  static const double sm = 8;
  static const double md = 14;
  static const double lg = 20;
}

abstract final class AppGradients {
  static const LinearGradient goldShimmer = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0x66D4AF37), Color(0x22D4AF37), Color(0x44FFD700)],
  );

  static const LinearGradient backgroundMesh = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: [Color(0xFF0F172A), Color(0xFF1E1B4B)],
  );
}

abstract final class AppTheme {
  static ThemeData darkGold() {
    final baseText = GoogleFonts.cormorantGaramondTextTheme(
      ThemeData(brightness: Brightness.dark).textTheme,
    );
    final textTheme = baseText.copyWith(
      displayLarge: GoogleFonts.cinzelDecorative(
        fontSize: 32,
        fontWeight: FontWeight.w600,
        color: AppColors.gold,
      ),
      headlineMedium: baseText.headlineMedium?.copyWith(
        color: AppColors.textPrimary,
        fontWeight: FontWeight.w600,
        fontSize: 22,
      ),
      titleLarge: baseText.titleLarge?.copyWith(
        color: AppColors.textPrimary,
        fontWeight: FontWeight.w600,
      ),
      bodyLarge: GoogleFonts.outfit(fontSize: 16, color: AppColors.textPrimary),
      bodyMedium: GoogleFonts.outfit(
        fontSize: 14,
        color: AppColors.textSecondary,
      ),
      labelLarge: GoogleFonts.outfit(
        fontSize: 12,
        letterSpacing: 1.2,
        color: AppColors.goldMuted,
        fontWeight: FontWeight.w600,
      ),
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      scaffoldBackgroundColor: AppColors.background,
      colorScheme: const ColorScheme.dark(
        primary: AppColors.gold,
        onPrimary: AppColors.background,
        surface: AppColors.surface,
        onSurface: AppColors.textPrimary,
        secondary: AppColors.goldMuted,
      ),
      textTheme: textTheme,
      appBarTheme: AppBarTheme(
        centerTitle: true,
        backgroundColor: AppColors.background.withValues(alpha: 0.92),
        elevation: 0,
        foregroundColor: AppColors.textPrimary,
        titleTextStyle: textTheme.titleLarge,
      ),
      dividerColor: AppColors.glassBorder,
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surface.withValues(alpha: 0.55),
        labelStyle: GoogleFonts.outfit(
          color: AppColors.textSecondary,
          fontSize: 14,
        ),
        hintStyle: GoogleFonts.outfit(
          color: AppColors.textSecondary.withValues(alpha: 0.85),
          fontSize: 14,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.sm),
          borderSide: const BorderSide(color: AppColors.glassBorder),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.sm),
          borderSide: const BorderSide(color: AppColors.glassBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.sm),
          borderSide: const BorderSide(color: AppColors.gold, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.sm),
          borderSide: BorderSide(color: Colors.red.shade300),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.sm),
          borderSide: BorderSide(color: Colors.red.shade200, width: 1.5),
        ),
      ),
    );
  }
}
