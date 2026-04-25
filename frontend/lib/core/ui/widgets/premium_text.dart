import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';

class PremiumText {
  PremiumText._();

  static Widget display(String text, {TextAlign? textAlign}) {
    return Text(
      text,
      textAlign: textAlign,
      style: GoogleFonts.cinzelDecorative(
        fontSize: 26,
        fontWeight: FontWeight.w600,
        color: AppColors.gold,
      ),
    );
  }

  static Widget headline(
    String text, {
    TextAlign? textAlign,
    int? maxLines,
    TextOverflow? overflow,
    Color? color,
  }) {
    return Builder(
      builder: (context) => Text(
        text,
        textAlign: textAlign,
        maxLines: maxLines,
        overflow: overflow,
        style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: color),
      ),
    );
  }

  static Widget body(
    String text, {
    TextAlign? textAlign,
    Color? color,
    int? maxLines,
    TextOverflow? overflow = TextOverflow.ellipsis,
  }) {
    return Builder(
      builder: (context) => Text(
        text,
        textAlign: textAlign,
        maxLines: maxLines,
        overflow: overflow,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: color),
      ),
    );
  }

  static Widget caption(
    String text, {
    TextAlign? textAlign,
    Color? color,
  }) {
    return Builder(
      builder: (context) => Text(
        text,
        textAlign: textAlign,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(color: color),
      ),
    );
  }
}
