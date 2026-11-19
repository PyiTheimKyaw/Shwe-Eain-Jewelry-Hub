import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/brand_logo.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';

/// Standard customer-shell header: [BrandLogo] on the left, tab title beside it.
class CustomerTabHeader {
  CustomerTabHeader._();

  static const double logoHeight = 28;
  static const double leadingWidth = 50;

  static Widget _logoLeading() {
    return Padding(
      padding: const EdgeInsets.only(left: 10),
      child: Align(
        alignment: Alignment.centerLeft,
        child: BrandLogo(height: logoHeight),
      ),
    );
  }

  static AppBar appBar({
    required String title,
    List<Widget> actions = const [],
    double backgroundOpacity = 0.92,
  }) {
    return AppBar(
      leadingWidth: leadingWidth,
      leading: _logoLeading(),
      title: PremiumText.headline(title, maxLines: 1),
      backgroundColor: AppColors.background.withValues(alpha: backgroundOpacity),
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      actions: actions,
    );
  }

  static SliverAppBar sliverAppBar({
    required String title,
    List<Widget> actions = const [],
    double backgroundOpacity = 0.85,
  }) {
    return SliverAppBar(
      pinned: true,
      floating: false,
      leadingWidth: leadingWidth,
      leading: _logoLeading(),
      title: PremiumText.headline(title, maxLines: 1),
      backgroundColor: AppColors.background.withValues(alpha: backgroundOpacity),
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      actions: actions,
    );
  }
}
