import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';

class PremiumImageViewer {
  PremiumImageViewer._();

  static Future<void> open({
    required BuildContext context,
    required List<String> imageUrls,
    int initialIndex = 0,
    String? heroPrefix,
  }) async {
    if (imageUrls.isEmpty) {
      return;
    }
    final zoomController = PageController(initialPage: initialIndex);
    var active = initialIndex;

    await showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Close image viewer',
      barrierColor: Colors.black.withValues(alpha: 0.9),
      pageBuilder: (context, animation, secondaryAnimation) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return SafeArea(
              child: Stack(
                children: [
                  PageView.builder(
                    controller: zoomController,
                    itemCount: imageUrls.length,
                    onPageChanged: (idx) => setStateDialog(() => active = idx),
                    itemBuilder: (context, index) {
                      final image = CachedNetworkImage(
                        imageUrl: imageUrls[index],
                        fit: BoxFit.contain,
                      );
                      final child = heroPrefix == null
                          ? image
                          : Hero(
                              tag: '$heroPrefix-$index',
                              child: image,
                            );
                      return InteractiveViewer(
                        minScale: 1,
                        maxScale: 4,
                        child: Center(child: child),
                      );
                    },
                  ),
                  Positioned(
                    top: 8,
                    left: 8,
                    right: 8,
                    child: Row(
                      children: [
                        IconButton(
                          icon: const Icon(
                            Icons.close_rounded,
                            color: Colors.white,
                          ),
                          onPressed: () => Navigator.of(context).pop(),
                        ),
                        const Spacer(),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.background.withValues(alpha: 0.75),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            '${active + 1}/${imageUrls.length}',
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w600,
                              fontSize: 12,
                              height: 1,
                              decoration: TextDecoration.none,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
    zoomController.dispose();
  }
}
