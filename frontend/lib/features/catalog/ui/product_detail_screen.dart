import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/catalog_pricing_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_gold_button.dart';
import 'package:jewelry_hub/core/ui/widgets/price_tag.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/core/ui/widgets/weight_badge.dart';
import 'package:jewelry_hub/features/catalog/data/catalog_api.dart';
import 'package:jewelry_hub/features/catalog/models/catalog_product.dart';
import 'package:jewelry_hub/features/catalog/ui/catalog_toggle_bar.dart';
import 'package:jewelry_hub/features/inquiries/ui/new_inquiry_screen.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';

class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({super.key, required this.productId});

  final int productId;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  CatalogProduct? _product;
  Object? _error;
  bool _loading = true;
  final PageController _carouselController = PageController();
  int _currentImageIndex = 0;

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
      final p = await CatalogApi.fetchProduct(widget.productId);
      if (mounted) {
        setState(() {
          _product = p;
          _currentImageIndex = 0;
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

  @override
  void dispose() {
    _carouselController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: _loading
              ? const Center(
                  child: CircularProgressIndicator(color: AppColors.gold),
                )
              : _error != null
              ? Center(child: Text('$_error'))
              : _buildContent(context, _product!),
        ),
      ),
    );
  }

  Widget _buildContent(BuildContext context, CatalogProduct p) {
    final images = [...p.images]
      ..sort((a, b) => a.sortOrder.compareTo(b.sortOrder));
    final imageUrls = images
        .map((e) => e.url.trim())
        .where((u) => u.isNotEmpty)
        .toList(growable: false);

    return CustomScrollView(
      slivers: [
        SliverAppBar(
          pinned: true,
          backgroundColor: AppColors.background.withValues(alpha: 0.9),
          title: PremiumText.headline(p.title, maxLines: 1),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh, color: AppColors.gold),
              onPressed: _load,
            ),
          ],
          bottom: const PreferredSize(
            preferredSize: Size.fromHeight(52),
            child: CatalogToggleBar(),
          ),
        ),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AppGlassCard(
                  padding: EdgeInsets.zero,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadii.md),
                    child: AspectRatio(
                      aspectRatio: 1.02,
                      child: imageUrls.isEmpty
                          ? Container(
                              color: AppColors.surface,
                              alignment: Alignment.center,
                              child: const Icon(
                                Icons.image_not_supported_outlined,
                                size: 64,
                                color: AppColors.textSecondary,
                              ),
                            )
                          : Stack(
                              children: [
                                PageView.builder(
                                  controller: _carouselController,
                                  itemCount: imageUrls.length,
                                  onPageChanged: (index) {
                                    if (!mounted) {
                                      return;
                                    }
                                    setState(() => _currentImageIndex = index);
                                  },
                                  itemBuilder: (context, index) {
                                    final url = imageUrls[index];
                                    return GestureDetector(
                                      onTap: () => _openZoomGallery(
                                        context: context,
                                        imageUrls: imageUrls,
                                        initialIndex: index,
                                      ),
                                      child: Hero(
                                        tag: 'product-hero-${p.id}-$index',
                                        child: CachedNetworkImage(
                                          imageUrl: url,
                                          fit: BoxFit.cover,
                                          placeholder: (context, _) =>
                                              Shimmer.fromColors(
                                                baseColor: AppColors.surface,
                                                highlightColor:
                                                    AppColors.surfaceElevated,
                                                child: Container(
                                                  color: AppColors.surface,
                                                ),
                                              ),
                                        ),
                                      ),
                                    );
                                  },
                                ),
                                Positioned(
                                  left: 12,
                                  right: 12,
                                  bottom: 12,
                                  child: Row(
                                    children: [
                                      Expanded(
                                        child: Wrap(
                                          spacing: 6,
                                          runSpacing: 6,
                                          children: List.generate(
                                            imageUrls.length,
                                            (index) => AnimatedContainer(
                                              duration: const Duration(
                                                milliseconds: 220,
                                              ),
                                              width: index == _currentImageIndex
                                                  ? 18
                                                  : 7,
                                              height: 7,
                                              decoration: BoxDecoration(
                                                borderRadius:
                                                    BorderRadius.circular(99),
                                                color:
                                                    index == _currentImageIndex
                                                    ? AppColors.gold
                                                    : AppColors.textSecondary
                                                          .withValues(
                                                            alpha: 0.55,
                                                          ),
                                              ),
                                            ),
                                          ),
                                        ),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 10,
                                          vertical: 5,
                                        ),
                                        decoration: BoxDecoration(
                                          color: AppColors.background
                                              .withValues(alpha: 0.55),
                                          borderRadius: BorderRadius.circular(
                                            999,
                                          ),
                                          border: Border.all(
                                            color: AppColors.glassBorder,
                                          ),
                                        ),
                                        child: PremiumText.caption(
                                          '${_currentImageIndex + 1}/${imageUrls.length}',
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
                if (imageUrls.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  PremiumText.caption(
                    'Swipe to view all images · Tap to zoom',
                    color: AppColors.textSecondary,
                  ),
                ],
                const SizedBox(height: 20),
                PremiumText.caption(p.sku),
                const SizedBox(height: 8),
                PremiumText.headline(p.title),
                if (p.categoryName != null) ...[
                  const SizedBox(height: 4),
                  PremiumText.body(p.categoryName!),
                ],
                const SizedBox(height: 12),
                Row(
                  children: [
                    const Icon(
                      Icons.scale_outlined,
                      size: 18,
                      color: AppColors.textSecondary,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: WeightBadge(grams: p.weightGrams, compact: false),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                PriceTag(pricing: p.pricing, compact: false),
                if (p.description != null && p.description!.isNotEmpty) ...[
                  const SizedBox(height: 20),
                  PremiumText.caption('About'),
                  const SizedBox(height: 6),
                  PremiumText.body(p.description!),
                ],
                if (p.pricing != null) ...[
                  const SizedBox(height: 20),
                  AppGlassCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        PremiumText.caption('Pricing breakdown (estimate)'),
                        const SizedBox(height: 8),
                        _row('Metal (MMK)', p.pricing!['metal_value_mmk']),
                        _row('Labor (MMK)', p.pricing!['labor_fee_mmk']),
                        _row('Gems (MMK)', p.pricing!['gem_price_mmk']),
                        _row('Total (MMK)', p.pricing!['line_total_mmk']),
                        _row('Total (USD est.)', p.pricing!['line_total_usd']),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 20),
                AppGoldButton(
                  label: 'Send inquiry',
                  icon: Icons.chat_bubble_outline_rounded,
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (context) => NewInquiryScreen(productId: p.id),
                      ),
                    );
                  },
                ),
                const SizedBox(height: 12),
                AppGoldButton(
                  label: 'Back to catalog',
                  icon: Icons.grid_view_rounded,
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _openZoomGallery({
    required BuildContext context,
    required List<String> imageUrls,
    required int initialIndex,
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
      barrierColor: Colors.black.withValues(alpha: 0.88),
      pageBuilder: (context, animation, secondaryAnimation) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return SafeArea(
              child: Stack(
                children: [
                  PageView.builder(
                    controller: zoomController,
                    itemCount: imageUrls.length,
                    onPageChanged: (idx) => setSheetState(() => active = idx),
                    itemBuilder: (context, index) {
                      return InteractiveViewer(
                        minScale: 1,
                        maxScale: 4,
                        child: Center(
                          child: Hero(
                            tag: 'product-hero-${_product?.id ?? 'p'}-$index',
                            child: CachedNetworkImage(
                              imageUrl: imageUrls[index],
                              fit: BoxFit.contain,
                            ),
                          ),
                        ),
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
                            color: Colors.black.withValues(alpha: 0.45),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(
                              color: Colors.white.withValues(alpha: 0.18),
                            ),
                          ),
                          child: Text(
                            '${active + 1}/${imageUrls.length}',
                            style: const TextStyle(color: Colors.white),
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

  static Widget _row(String label, Object? value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppColors.textSecondary)),
          Text(
            value?.toString() ?? '—',
            style: const TextStyle(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
