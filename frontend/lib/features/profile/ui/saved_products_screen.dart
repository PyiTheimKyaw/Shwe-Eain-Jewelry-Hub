import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/catalog_pricing_provider.dart';
import 'package:jewelry_hub/core/providers/saved_favorites_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_refresh.dart';
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

  Future<void> _load({bool silent = false}) async {
    if (!mounted) return;
    final pricing = context.read<CatalogPricing>();
    final fav = context.read<SavedFavorites>();
    await pricing.refresh();
    if (!mounted) return;
    final ids = List<int>.from(fav.orderedIds);
    if (!silent) {
      setState(() {
        _loading = true;
        _error = null;
        _products.clear();
      });
    }
    if (ids.isEmpty) {
      if (mounted) {
        setState(() {
          _loading = false;
          _products.clear();
          _error = null;
        });
      }
      return;
    }
    final stale = <int>[];
    final nextProducts = <CatalogProduct>[];
    try {
      for (final id in ids) {
        try {
          final p = await CatalogApi.fetchProduct(id);
          nextProducts.add(p);
        } catch (_) {
          stale.add(id);
        }
      }
      for (final id in stale) {
        fav.remove(id);
      }
      if (mounted) {
        setState(() {
          _products
            ..clear()
            ..addAll(nextProducts);
          _loading = false;
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) {
        if (silent) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('$e')),
          );
        } else {
          setState(() {
            _error = '$e';
            _loading = false;
          });
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: PremiumText.headline('Saved pieces', maxLines: 1),
        surfaceTintColor: Colors.transparent,
      ),
      extendBodyBehindAppBar: true,
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: Consumer<SavedFavorites>(
            builder: (context, fav, _) {
              return RefreshIndicator(
                color: AppRefresh.indicatorColor,
                strokeWidth: AppRefresh.strokeWidth,
                onRefresh: () => _load(silent: true),
                child: CustomScrollView(
                  physics: AppRefresh.scrollPhysics,
                  slivers: [
                    if (_loading && _products.isEmpty && fav.orderedIds.isNotEmpty)
                      const SliverFillRemaining(
                        hasScrollBody: false,
                        child: Center(
                          child: CircularProgressIndicator(
                            color: AppColors.gold,
                            strokeWidth: 2,
                          ),
                        ),
                      )
                    else if (_error != null && _products.isEmpty)
                      SliverFillRemaining(
                        hasScrollBody: false,
                        child: Padding(
                          padding: const EdgeInsets.all(20),
                          child: AppGlassCard(
                            child: PremiumText.body(
                              _error!,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ),
                      )
                    else if (fav.orderedIds.isEmpty || _products.isEmpty)
                      SliverFillRemaining(
                        hasScrollBody: false,
                        child: Padding(
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
                        ),
                      )
                    else
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
                        sliver: SliverGrid(
                          gridDelegate:
                              const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            mainAxisSpacing: 12,
                            crossAxisSpacing: 12,
                            childAspectRatio: 0.72,
                          ),
                          delegate: SliverChildBuilderDelegate(
                            (context, index) {
                              final p = _products[index];
                              final url = p.primaryImageUrl;
                              return AppGlassCard(
                                onTap: () {
                                  Navigator.of(context).push<void>(
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          ProductDetailScreen(productId: p.id),
                                    ),
                                  );
                                },
                                padding: EdgeInsets.zero,
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.stretch,
                                  children: [
                                    Expanded(
                                      child: ClipRRect(
                                        borderRadius:
                                            const BorderRadius.vertical(
                                          top: Radius.circular(AppRadii.md),
                                        ),
                                        child: url == null
                                            ? Container(
                                                color: AppColors.surface,
                                                child:
                                                    const ShweEainGoldPlaceholder(
                                                  compact: true,
                                                ),
                                              )
                                            : CachedNetworkImage(
                                                imageUrl: url,
                                                fit: BoxFit.cover,
                                                placeholder: (context, _) =>
                                                    Shimmer.fromColors(
                                                  baseColor: AppColors.surface,
                                                  highlightColor: AppColors
                                                      .surfaceElevated,
                                                  child: Container(
                                                      color: AppColors.surface),
                                                ),
                                                errorWidget: (context, _, _) =>
                                                    Container(
                                                  color: AppColors.surface,
                                                  child:
                                                      const ShweEainGoldPlaceholder(
                                                    compact: true,
                                                  ),
                                                ),
                                              ),
                                      ),
                                    ),
                                    Padding(
                                      padding: const EdgeInsets.fromLTRB(
                                          10, 8, 10, 10),
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
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
                            childCount: _products.length,
                          ),
                        ),
                      ),
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}
