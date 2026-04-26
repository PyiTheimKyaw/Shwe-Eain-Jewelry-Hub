import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';

/// Pill badge for order lifecycle status (customer lists).
class OrderStatusBadge extends StatelessWidget {
  const OrderStatusBadge({super.key, required this.status});

  final String status;

  static String labelFor(String raw) {
    final s = raw.replaceAll('_', ' ').trim();
    if (s.isEmpty) return '—';
    return s.split(' ').map((w) {
      if (w.isEmpty) return w;
      return '${w[0].toUpperCase()}${w.length > 1 ? w.substring(1).toLowerCase() : ''}';
    }).join(' ');
  }

  static (Color bg, Color fg) _style(String normalized) {
    switch (normalized) {
      case 'completed':
      case 'ready':
      case 'shipped':
        return (
          AppColors.gold.withValues(alpha: 0.22),
          AppColors.gold,
        );
      case 'cancelled':
        return (
          const Color(0x33FF6B6B),
          const Color(0xFFFF8A8A),
        );
      case 'awaiting_deposit':
        return (
          AppColors.surfaceElevated.withValues(alpha: 0.85),
          AppColors.textSecondary,
        );
      case 'deposit_confirmed':
      case 'pending_qc':
        return (
          const Color(0xFF1E3A5F).withValues(alpha: 0.65),
          const Color(0xFF93C5FD),
        );
      case 'in_production':
        return (
          const Color(0xFF2D1F4A).withValues(alpha: 0.7),
          const Color(0xFFC4B5FD),
        );
      case 'awaiting_final_settlement':
        return (
          AppColors.gold.withValues(alpha: 0.14),
          AppColors.goldMuted,
        );
      default:
        return (
          AppColors.surface.withValues(alpha: 0.55),
          AppColors.textPrimary,
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final key = status.toLowerCase().replaceAll(' ', '_').trim();
    final (bg, fg) = _style(key.isEmpty ? '-' : key);
    final text = labelFor(status);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: fg.withValues(alpha: 0.35)),
      ),
      child: Text(
        text,
        style: GoogleFonts.outfit(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: fg,
          letterSpacing: 0.2,
        ),
      ),
    );
  }
}
