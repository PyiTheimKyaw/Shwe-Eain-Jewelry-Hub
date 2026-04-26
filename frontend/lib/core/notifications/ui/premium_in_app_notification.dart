import 'dart:async';
import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';

class PremiumInAppNotification {
  PremiumInAppNotification._();

  static OverlayEntry? _activeEntry;

  static void show({
    required GlobalKey<NavigatorState> navigatorKey,
    required String title,
    required String body,
  }) {
    final overlay = navigatorKey.currentState?.overlay;
    if (overlay == null) return;

    _activeEntry?.remove();
    _activeEntry = null;

    late final OverlayEntry entry;
    entry = OverlayEntry(
      builder: (_) => _PremiumInAppBanner(
        title: title,
        body: body,
        onDismissed: () {
          if (_activeEntry == entry) {
            _activeEntry?.remove();
            _activeEntry = null;
          }
        },
      ),
    );

    _activeEntry = entry;
    overlay.insert(entry);
  }
}

class _PremiumInAppBanner extends StatefulWidget {
  const _PremiumInAppBanner({
    required this.title,
    required this.body,
    required this.onDismissed,
  });

  final String title;
  final String body;
  final VoidCallback onDismissed;

  @override
  State<_PremiumInAppBanner> createState() => _PremiumInAppBannerState();
}

class _PremiumInAppBannerState extends State<_PremiumInAppBanner>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 280),
    reverseDuration: const Duration(milliseconds: 220),
  );
  late final Animation<double> _opacity = CurvedAnimation(
    parent: _controller,
    curve: Curves.easeOutCubic,
  );
  late final Animation<Offset> _offset = Tween<Offset>(
    begin: const Offset(0, -0.25),
    end: Offset.zero,
  ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));

  Timer? _autoDismiss;
  bool _dismissing = false;

  @override
  void initState() {
    super.initState();
    _controller.forward();
    _autoDismiss = Timer(const Duration(milliseconds: 3600), _dismiss);
  }

  Future<void> _dismiss() async {
    if (_dismissing) return;
    _dismissing = true;
    _autoDismiss?.cancel();
    _autoDismiss = null;
    if (!mounted) return;
    await _controller.reverse();
    if (!mounted) return;
    widget.onDismissed();
  }

  @override
  void dispose() {
    _autoDismiss?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Positioned(
      top: 16,
      left: 0,
      right: 0,
      child: SafeArea(
        child: Center(
          child: FadeTransition(
            opacity: _opacity,
            child: SlideTransition(
              position: _offset,
              child: Material(
                type: MaterialType.transparency,
                child: GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTap: _dismiss,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadii.lg),
                    child: BackdropFilter(
                      filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                      child: Container(
                        constraints: const BoxConstraints(maxWidth: 560),
                        margin: const EdgeInsets.symmetric(horizontal: 16),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 12,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFF0F2E35).withValues(alpha: 0.9),
                          borderRadius: BorderRadius.circular(AppRadii.lg),
                          border: Border.all(
                            color: AppColors.gold.withValues(alpha: 0.82),
                            width: 1.1,
                          ),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x66000000),
                              blurRadius: 16,
                              offset: Offset(0, 8),
                            ),
                          ],
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Padding(
                              padding: EdgeInsets.only(top: 1),
                              child: Icon(
                                Icons.notifications_active_rounded,
                                color: AppColors.gold,
                                size: 18,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    widget.title,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: GoogleFonts.outfit(
                                      color: AppColors.gold,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 14,
                                      height: 1.2,
                                      decoration: TextDecoration.none,
                                      decorationColor: Colors.transparent,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    widget.body,
                                    maxLines: 4,
                                    overflow: TextOverflow.ellipsis,
                                    softWrap: true,
                                    style: GoogleFonts.outfit(
                                      color: AppColors.textPrimary,
                                      fontSize: 13,
                                      height: 1.35,
                                      fontWeight: FontWeight.w400,
                                      decoration: TextDecoration.none,
                                      decorationColor: Colors.transparent,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
