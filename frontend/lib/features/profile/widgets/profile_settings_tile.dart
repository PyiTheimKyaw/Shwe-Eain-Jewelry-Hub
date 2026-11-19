import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';

class ProfileSettingsTile extends StatelessWidget {
  const ProfileSettingsTile({
    super.key,
    required this.icon,
    required this.title,
    this.subtitle,
    required this.onTap,
    this.destructive = false,
    this.trailing = Icons.chevron_right_rounded,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback onTap;
  final bool destructive;
  final IconData trailing;

  @override
  Widget build(BuildContext context) {
    final accent = destructive ? const Color(0xFFFF6B6B) : AppColors.gold;
    return AppGlassCard(
      padding: EdgeInsets.zero,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(AppRadii.md),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            child: Row(
              children: [
                Icon(icon, color: accent, size: 22),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: GoogleFonts.outfit(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          color: destructive
                              ? const Color(0xFFFF6B6B)
                              : AppColors.textPrimary,
                        ),
                      ),
                      if (subtitle != null && subtitle!.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          subtitle!,
                          style: GoogleFonts.outfit(
                            fontSize: 13,
                            color: AppColors.textSecondary,
                            height: 1.25,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                Icon(trailing, color: AppColors.textSecondary, size: 22),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
