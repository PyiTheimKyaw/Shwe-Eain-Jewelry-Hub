import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/catalog_pricing_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/display_preferences_sheet.dart';
import 'package:jewelry_hub/core/ui/widgets/price_tag.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/core/ui/widgets/shwe_eain_gold_placeholder.dart';
import 'package:jewelry_hub/core/ui/widgets/weight_badge.dart';
import 'package:jewelry_hub/features/catalog/data/catalog_api.dart';
import 'package:jewelry_hub/features/catalog/ui/catalog_toggle_bar.dart';
import 'package:jewelry_hub/features/catalog/models/catalog_product.dart';
import 'package:jewelry_hub/features/catalog/ui/product_detail_screen.dart';
import 'package:jewelry_hub/features/catalog/widgets/product_favorite_button.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';

class ProductCatalogScreen extends StatefulWidget {
  const ProductCatalogScreen({super.key});

  @override
  State<ProductCatalogScreen> createState() => _ProductCatalogScreenState();
}

class _ProductCatalogScreenState extends State<ProductCatalogScreen> {
  List<CatalogCategory> _categories = const [];
  List<CatalogProduct> _newArrivals = const [];
  List<CatalogProduct>? _items;
  int? _selectedCategoryId;
  Object? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    await context.read<CatalogPricing>().refresh();
    try {
      final categories = await CatalogApi.fetchCategories();
      final result = await CatalogApi.fetchProducts(
        categoryId: _selectedCategoryId,
      );
      if (mounted) {
        setState(() {
          _categories = categories;
          _newArrivals = result.newArrivals;
          _items = result.products;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  Future<void> _applyCategory(int? categoryId) async {
    setState(() {
      _selectedCategoryId = categoryId;
      _loading = true;
      _error = null;
    });
    try {
      final result = await CatalogApi.fetchProducts(categoryId: categoryId);
      if (!mounted) {
        return;
      }
      setState(() {
        _newArrivals = result.newArrivals;
        _items = result.products;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = e;
        _loading = false;
      });
    }
  }

  Future<void> _openDisplayPreferencesSheet() =>
      showDisplayPreferencesSheet(context);

  Widget _buildCategoryChips() {
    final chips = <Widget>[
      ChoiceChip(
        label: const Text('All'),
        selected: _selectedCategoryId == null,
        onSelected: (_) => _applyCategory(null),
      ),
      ..._categories.map(
        (category) => ChoiceChip(
          label: Text(category.name),
          selected: _selectedCategoryId == category.id,
          onSelected: (_) => _applyCategory(category.id),
        ),
      ),
    ];

    return SliverToBoxAdapter(
      child: SizedBox(
        height: 48,
        child: ListView.separated(
          padding: const EdgeInsets.fromLTRB(12, 6, 12, 6),
          scrollDirection: Axis.horizontal,
          itemBuilder: (context, index) => chips[index],
          separatorBuilder: (_, _) => const SizedBox(width: 8),
          itemCount: chips.length,
        ),
      ),
    );
  }

  Widget _buildNewArrivalsCarousel() {
    if (_newArrivals.isEmpty) {
      return const SliverToBoxAdapter(child: SizedBox.shrink());
    }

    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            PremiumText.caption('NEW ARRIVALS', color: AppColors.goldMuted),
            const SizedBox(height: 8),
            SizedBox(
              height: 204,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _newArrivals.length,
                separatorBuilder: (_, _) => const SizedBox(width: 10),
                itemBuilder: (context, index) {
                  final product = _newArrivals[index];
                  return _NewArrivalCard(product: product);
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: RefreshIndicator(
            color: AppColors.gold,
            onRefresh: _load,
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverAppBar(
                  pinned: true,
                  floating: false,
                  backgroundColor: AppColors.background.withValues(alpha: 0.85),
                  title: PremiumText.display('Jewelry Hub'),
                  actions: [
                    IconButton(
                      tooltip: 'Display preferences',
                      icon: const Icon(
                        Icons.tune_rounded,
                        color: AppColors.gold,
                      ),
                      onPressed: _openDisplayPreferencesSheet,
                    ),
                  ],
                ),
                // const SliverToBoxAdapter(child: CatalogToggleBar()),
                _buildNewArrivalsCarousel(),
                _buildCategoryChips(),
                if (_error != null)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(
                        'Could not load catalog.\n$_error',
                        style: Theme.of(context).textTheme.bodyLarge,
                      ),
                    ),
                  )
                else if (_loading)
                  SliverPadding(
                    padding: const EdgeInsets.all(12),
                    sliver: SliverGrid(
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            mainAxisSpacing: 12,
                            crossAxisSpacing: 12,
                            childAspectRatio: 0.72,
                          ),
                      delegate: SliverChildBuilderDelegate(
                        (context, index) => const _ShimmerCard(),
                        childCount: 6,
                      ),
                    ),
                  )
                else if (_items!.isEmpty)
                  const SliverToBoxAdapter(
                    child: Padding(
                      padding: EdgeInsets.all(32),
                      child: Center(child: Text('No published products yet.')),
                    ),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 24),
                    sliver: SliverGrid(
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            mainAxisSpacing: 12,
                            crossAxisSpacing: 12,
                            childAspectRatio: 0.72,
                          ),
                      delegate: SliverChildBuilderDelegate((context, index) {
                        final p = _items![index];
                        return _ProductTile(product: p);
                      }, childCount: _items!.length),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ShimmerCard extends StatelessWidget {
  const _ShimmerCard();

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: AppColors.surface,
      highlightColor: AppColors.surfaceElevated,
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadii.md),
        ),
      ),
    );
  }
}

class _NewArrivalCard extends StatelessWidget {
  const _NewArrivalCard({required this.product});

  final CatalogProduct product;

  @override
  Widget build(BuildContext context) {
    final url = product.primaryImageUrl;
    return SizedBox(
      width: 162,
      child: AppGlassCard(
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (context) => ProductDetailScreen(productId: product.id),
            ),
          );
        },
        padding: const EdgeInsets.all(0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: ClipRRect(
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(AppRadii.md),
                ),
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (url == null)
                      Container(
                        color: AppColors.surface,
                        child: const ShweEainGoldPlaceholder(compact: true),
                      )
                    else
                      CachedNetworkImage(
                        imageUrl: url,
                        fit: BoxFit.cover,
                        placeholder: (context, url) => Shimmer.fromColors(
                          baseColor: AppColors.surface,
                          highlightColor: AppColors.surfaceElevated,
                          child: Container(color: AppColors.surface),
                        ),
                        errorWidget: (context, url, error) => Container(
                          color: AppColors.surface,
                          child: const ShweEainGoldPlaceholder(compact: true),
                        ),
                      ),
                    Positioned(
                      top: 8,
                      right: 8,
                      child: ProductFavoriteButton(productId: product.id),
                    ),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  PremiumText.body(product.title, maxLines: 1),
                  const SizedBox(height: 6),
                  PriceTag(pricing: product.pricing),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProductTile extends StatelessWidget {
  const _ProductTile({required this.product});

  final CatalogProduct product;

  @override
  Widget build(BuildContext context) {
    final url = product.primaryImageUrl;
    return AppGlassCard(
      onTap: () {
        Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (context) => ProductDetailScreen(productId: product.id),
          ),
        );
      },
      padding: const EdgeInsets.all(0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: ClipRRect(
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(AppRadii.md),
              ),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (url == null)
                    Container(
                      color: AppColors.surface,
                      child: const ShweEainGoldPlaceholder(compact: true),
                    )
                  else
                    Hero(
                      tag: 'product-hero-${product.id}',
                      child: CachedNetworkImage(
                        imageUrl: url,
                        fit: BoxFit.cover,
                        placeholder: (context, url) => Shimmer.fromColors(
                          baseColor: AppColors.surface,
                          highlightColor: AppColors.surfaceElevated,
                          child: Container(color: AppColors.surface),
                        ),
                        errorWidget: (context, url, error) => Container(
                          color: AppColors.surface,
                          child: const ShweEainGoldPlaceholder(compact: true),
                        ),
                      ),
                    ),
                  Positioned(
                    top: 8,
                    right: 8,
                    child: ProductFavoriteButton(productId: product.id),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  product.title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 4),
                WeightBadge(grams: product.weightGrams),
                const SizedBox(height: 6),
                PriceTag(pricing: product.pricing),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
