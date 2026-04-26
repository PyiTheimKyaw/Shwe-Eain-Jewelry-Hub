import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/catalog_pricing_provider.dart';
import 'package:jewelry_hub/core/providers/saved_favorites_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/price_tag.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/core/ui/widgets/shwe_eain_gold_placeholder.dart';
import 'package:jewelry_hub/core/ui/widgets/weight_badge.dart';
import 'package:jewelry_hub/features/catalog/data/catalog_api.dart';
import 'package:jewelry_hub/features/catalog/models/catalog_product.dart';
import 'package:jewelry_hub/features/catalog/ui/product_detail_screen.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';

class SavedProductsScreen extends StatefulWidget {
  const SavedProductsScreen({super.key});

  @override
  State<SavedProductsScreen> createState() => _SavedProductsScreenState();
}

class _SavedProductsScreenState extends State<SavedProductsScreen> {
  final List<CatalogProduct> _products = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    if (!mounted) return;
    final pricing = context.read<CatalogPricing>();
    final fav = context.read<SavedFavorites>();
    await pricing.refresh();
    if (!mounted) return;
    final ids = List<int>.from(fav.orderedIds);
    setState(() {
      _loading = true;
      _error = null;
      _products.clear();
    });
    if (ids.isEmpty) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    final stale = <int>[];
    try {
      for (final id in ids) {
        try {
          final p = await CatalogApi.fetchProduct(id);
          _products.add(p);
        } catch (_) {
          stale.add(id);
        }
      }
      for (final id in stale) {
        fav.remove(id);
      }
      if (mounted) {
        setState(() => _loading = false);
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = '$e';
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: PremiumText.headline('Saved pieces', maxLines: 1),
        actions: [
          IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded, color: AppColors.gold),
          ),
        ],
      ),
      extendBodyBehindAppBar: true,
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: Consumer<SavedFavorites>(
            builder: (context, fav, _) {
              if (_loading) {
                return const Center(
                  child: CircularProgressIndicator(
                    color: AppColors.gold,
                    strokeWidth: 2,
                  ),
                );
              }
              if (_error != null) {
                return Padding(
                  padding: const EdgeInsets.all(20),
                  child: AppGlassCard(
                    child: PremiumText.body(
                      _error!,
                      color: AppColors.textSecondary,
                    ),
                  ),
                );
              }
              if (fav.orderedIds.isEmpty || _products.isEmpty) {
                return Padding(
                  padding: const EdgeInsets.all(24),
                  child: AppGlassCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        PremiumText.headline('No saved pieces yet'),
                        const SizedBox(height: 8),
                        PremiumText.body(
                          'Tap the heart on any product in the catalog to save it here.',
                          color: AppColors.textSecondary,
                        ),
                      ],
                    ),
                  ),
                );
              }
              return GridView.builder(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 0.72,
                ),
                itemCount: _products.length,
                itemBuilder: (context, index) {
                  final p = _products[index];
                  final url = p.primaryImageUrl;
                  return AppGlassCard(
                    onTap: () {
                      Navigator.of(context).push<void>(
                        MaterialPageRoute(
                          builder: (_) => ProductDetailScreen(productId: p.id),
                        ),
                      );
                    },
                    padding: EdgeInsets.zero,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Expanded(
                          child: ClipRRect(
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(AppRadii.md),
                            ),
                            child: url == null
                                ? Container(
                                    color: AppColors.surface,
                                    child: const ShweEainGoldPlaceholder(
                                      compact: true,
                                    ),
                                  )
                                : CachedNetworkImage(
                                    imageUrl: url,
                                    fit: BoxFit.cover,
                                    placeholder: (context, _) =>
                                        Shimmer.fromColors(
                                      baseColor: AppColors.surface,
                                      highlightColor:
                                          AppColors.surfaceElevated,
                                      child: Container(color: AppColors.surface),
                                    ),
                                    errorWidget: (context, _, _) => Container(
                                      color: AppColors.surface,
                                      child: const ShweEainGoldPlaceholder(
                                        compact: true,
                                      ),
                                    ),
                                  ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                p.title,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: Theme.of(context)
                                    .textTheme
                                    .titleSmall
                                    ?.copyWith(
                                      color: AppColors.textPrimary,
                                      fontWeight: FontWeight.w600,
                                    ),
                              ),
                              const SizedBox(height: 4),
                              WeightBadge(grams: p.weightGrams),
                              const SizedBox(height: 6),
                              PriceTag(pricing: p.pricing),
                            ],
                          ),
                        ),
                      ],
                    ),
                  );
                },
              );
            },
          ),
        ),
      ),
    );
  }
}
