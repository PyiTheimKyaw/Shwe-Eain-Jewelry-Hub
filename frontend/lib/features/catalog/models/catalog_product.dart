class CatalogImage {
  const CatalogImage({
    required this.id,
    required this.url,
    required this.sortOrder,
  });

  final int id;
  final String url;
  final int sortOrder;

  factory CatalogImage.fromJson(Map<String, dynamic> j) {
    return CatalogImage(
      id: j['id'] as int,
      url: j['url'] as String? ?? '',
      sortOrder: j['sort_order'] as int? ?? 0,
    );
  }
}

class CatalogProduct {
  const CatalogProduct({
    required this.id,
    required this.title,
    required this.sku,
    required this.weightGrams,
    required this.description,
    required this.isReadyMade,
    this.pricing,
    this.images = const [],
    this.categoryName,
  });

  final int id;
  final String title;
  final String sku;
  final double weightGrams;
  final String? description;
  final bool isReadyMade;
  final Map<String, dynamic>? pricing;
  final List<CatalogImage> images;
  final String? categoryName;

  String? get primaryImageUrl {
    if (images.isEmpty) return null;
    final sorted = [...images]..sort((a, b) => a.sortOrder.compareTo(b.sortOrder));
    final u = sorted.first.url;
    return u.isEmpty ? null : u;
  }

  factory CatalogProduct.fromJson(Map<String, dynamic> j) {
    final imgs = (j['images'] as List<dynamic>? ?? [])
        .map((e) => CatalogImage.fromJson(e as Map<String, dynamic>))
        .toList();

    Map<String, dynamic>? pricing;
    final p = j['pricing'];
    if (p is Map<String, dynamic>) {
      pricing = p;
    }

    final cat = j['category'];
    String? catName;
    if (cat is Map<String, dynamic>) {
      catName = cat['name'] as String?;
    }

    return CatalogProduct(
      id: j['id'] as int,
      title: j['title'] as String? ?? '',
      sku: j['sku'] as String? ?? '',
      weightGrams: double.tryParse(j['weight_grams']?.toString() ?? '0') ?? 0,
      description: j['description'] as String?,
      isReadyMade: j['is_ready_made'] as bool? ?? true,
      pricing: pricing,
      images: imgs,
      categoryName: catName,
    );
  }
}
