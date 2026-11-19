import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/display_preferences_controls.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';

class DisplayPreferencesScreen extends StatelessWidget {
  const DisplayPreferencesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: PremiumText.headline('Display preferences', maxLines: 2),
      ),
      extendBodyBehindAppBar: true,
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: [
              PremiumText.caption(
                'CATALOG DEFAULTS',
                color: AppColors.goldMuted,
              ),
              const SizedBox(height: 10),
              AppGlassCard(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 18),
                child: const DisplayPreferencesControls(),
              ),
              const SizedBox(height: 16),
              AppGlassCard(
                child: PremiumText.body(
                  'Prices and weights across the catalog use these defaults. '
                  'You can still change them anytime from the catalog if needed.',
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
