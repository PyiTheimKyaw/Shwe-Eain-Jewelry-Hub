import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/saved_favorites_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:provider/provider.dart';

/// Compact heart control for catalog surfaces (does not open product detail).
class ProductFavoriteButton extends StatelessWidget {
  const ProductFavoriteButton({
    super.key,
    required this.productId,
    this.size = 20,
    this.compact = true,
  });

  final int productId;
  final double size;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Consumer<SavedFavorites>(
      builder: (context, fav, _) {
        final saved = fav.isFavorite(productId);
        final child = Icon(
          saved ? Icons.favorite_rounded : Icons.favorite_border_rounded,
          color: AppColors.gold,
          size: size,
        );
        return Material(
          color: compact
              ? AppColors.background.withValues(alpha: 0.45)
              : Colors.transparent,
          shape: const CircleBorder(),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => fav.toggle(productId),
            child: Padding(
              padding: EdgeInsets.all(compact ? 6 : 8),
              child: child,
            ),
          ),
        );
      },
    );
  }
}
