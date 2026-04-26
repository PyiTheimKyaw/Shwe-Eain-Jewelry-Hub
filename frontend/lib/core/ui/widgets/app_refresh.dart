import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';

/// Shared pull-to-refresh styling (HCI: consistent affordance across lists).
class AppRefresh {
  AppRefresh._();

  static const Color indicatorColor = AppColors.gold;

  static const double strokeWidth = 2.5;

  /// Prefer with [RefreshIndicator] so short lists still pull ([AlwaysScrollableScrollPhysics]).
  static const ScrollPhysics scrollPhysics = AlwaysScrollableScrollPhysics();
}
