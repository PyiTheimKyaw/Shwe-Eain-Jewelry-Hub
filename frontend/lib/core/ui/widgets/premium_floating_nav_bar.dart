import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';

/// One tab in [PremiumFloatingNavBar].
class PremiumNavDestinationData {
  const PremiumNavDestinationData({
    required this.icon,
    required this.selectedIcon,
    required this.label,
    this.iconOverlay,
  });

  final IconData icon;
  final IconData selectedIcon;
  final String label;

  /// Drawn above the icon (e.g. unread dot). Hit testing ignores overlay.
  final Widget? iconOverlay;
}

enum PremiumFloatingNavBorderStyle { none, ultraThin }

/// Luxury floating dock: slim glass pill, gold accent, ≥48dp touch targets.
class PremiumFloatingNavBar extends StatelessWidget {
  const PremiumFloatingNavBar({
    super.key,
    required this.selectedIndex,
    required this.onDestinationSelected,
    required this.destinations,
    this.borderStyle = PremiumFloatingNavBorderStyle.none,
  });

  final int selectedIndex;
  final ValueChanged<int> onDestinationSelected;
  final List<PremiumNavDestinationData> destinations;
  final PremiumFloatingNavBorderStyle borderStyle;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      elevation: 0,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(26),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 5),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(26),
              border: borderStyle == PremiumFloatingNavBorderStyle.ultraThin
                  ? Border.all(
                      color: AppColors.glassBorder.withValues(alpha: 0.18),
                      width: 0.8,
                    )
                  : null,
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  AppColors.surface.withValues(alpha: 0.76),
                  AppColors.background.withValues(alpha: 0.54),
                ],
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.36),
                  blurRadius: 22,
                  offset: const Offset(0, 10),
                ),
                BoxShadow(
                  color: AppColors.gold.withValues(alpha: 0.06),
                  blurRadius: 18,
                  spreadRadius: -3,
                ),
              ],
            ),
            child: Row(
              children: List.generate(destinations.length, (i) {
                final d = destinations[i];
                final selected = i == selectedIndex;
                return Expanded(
                  child: _PremiumNavItem(
                    destination: d,
                    selected: selected,
                    onTap: () => onDestinationSelected(i),
                  ),
                );
              }),
            ),
          ),
        ),
      ),
    );
  }
}

class _PremiumNavItem extends StatelessWidget {
  const _PremiumNavItem({
    required this.destination,
    required this.selected,
    required this.onTap,
  });

  final PremiumNavDestinationData destination;
  final bool selected;
  final VoidCallback onTap;

  /// Material minimum touch target; slim layout fits inside via tight padding.
  static const double _minTouchHeight = 48;
  static const double _iconSize = 22;

  @override
  Widget build(BuildContext context) {
    final iconData = selected ? destination.selectedIcon : destination.icon;
    final iconColor =
        selected ? AppColors.gold : AppColors.textSecondary.withValues(alpha: 0.92);

    return Semantics(
      button: true,
      selected: selected,
      label: destination.label,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(20),
          splashColor: AppColors.gold.withValues(alpha: 0.12),
          highlightColor: AppColors.gold.withValues(alpha: 0.06),
          child: ConstrainedBox(
            constraints: const BoxConstraints(
              minHeight: _minTouchHeight,
              minWidth: 48,
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 3),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                children: [
                  TweenAnimationBuilder<double>(
                    tween: Tween(begin: 1, end: selected ? 1.06 : 1),
                    duration: const Duration(milliseconds: 320),
                    curve: Curves.easeOutBack,
                    builder: (context, scale, child) {
                      return Transform.scale(scale: scale, child: child);
                    },
                    child: SizedBox(
                      height: 30,
                      width: 40,
                      child: Stack(
                        clipBehavior: Clip.none,
                        alignment: Alignment.center,
                        children: [
                          if (selected)
                            Container(
                              width: 34,
                              height: 34,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: Colors.transparent,
                                boxShadow: [
                                  BoxShadow(
                                    color: AppColors.gold.withValues(
                                      alpha: 0.26,
                                    ),
                                    blurRadius: 14,
                                    spreadRadius: 0,
                                  ),
                                  BoxShadow(
                                    color: AppColors.gold.withValues(
                                      alpha: 0.12,
                                    ),
                                    blurRadius: 8,
                                    spreadRadius: 0,
                                  ),
                                ],
                              ),
                            ),
                          Icon(
                            iconData,
                            size: _iconSize,
                            color: iconColor,
                          ),
                          if (destination.iconOverlay != null)
                            IgnorePointer(
                              child: destination.iconOverlay!,
                            ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 2),
                  SizedBox(
                    height: 2,
                    width: double.infinity,
                    child: Center(
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 280),
                        curve: Curves.easeOutCubic,
                        height: 2,
                        width: selected ? 22 : 0,
                        decoration: BoxDecoration(
                          color: AppColors.gold,
                          borderRadius: BorderRadius.circular(99),
                          boxShadow: selected
                              ? [
                                  BoxShadow(
                                    color: AppColors.gold.withValues(
                                      alpha: 0.4,
                                    ),
                                    blurRadius: 4,
                                  ),
                                ]
                              : null,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 1),
                  Text(
                    destination.label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.outfit(
                      fontSize: 9.5,
                      height: 1.1,
                      fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                      letterSpacing: 0.15,
                      color: selected
                          ? AppColors.gold.withValues(alpha: 0.95)
                          : AppColors.textSecondary.withValues(alpha: 0.88),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
