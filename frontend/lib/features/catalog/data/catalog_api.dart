import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:jewelry_hub/core/cache/app_cache_store.dart';
import 'package:jewelry_hub/core/config/api_config.dart';
import 'package:jewelry_hub/features/catalog/models/catalog_product.dart';

class CatalogApiException implements Exception {
  CatalogApiException(this.message);
  final String message;
  @override
  String toString() => message;
}

class CatalogApi {
  CatalogApi._();
  static const _categoriesCacheKey = 'catalog_categories';

  static Future<List<CatalogCategory>> fetchCategories() async {
    final uri = Uri.parse('${ApiConfig.apiV1}/catalog/categories');
    try {
      final res = await http.get(uri);
      if (res.statusCode != 200) {
        throw CatalogApiException('Categories ${res.statusCode}');
      }
      final map = json.decode(res.body) as Map<String, dynamic>;
      final rawData = map['data'];
      await AppCacheStore.writeJson(key: _categoriesCacheKey, data: rawData);
      final list = rawData as List<dynamic>? ?? [];
      return list
          .map(
            (e) => CatalogCategory.fromJson(Map<String, dynamic>.from(e as Map)),
          )
          .toList(growable: false);
    } catch (_) {
      final cached = AppCacheStore.readJson(_categoriesCacheKey);
      if (cached != null && cached.data is List) {
        final list = cached.data as List<dynamic>;
        return list
            .whereType<Map>()
            .map((e) => CatalogCategory.fromJson(Map<String, dynamic>.from(e)))
            .toList(growable: false);
      }
      rethrow;
    }
  }

  static Future<CatalogProductsResponse> fetchProducts({
    int? categoryId,
    String? search,
    bool? readyMade,
  }) async {
    final qp = <String, String>{};
    if (categoryId != null) qp['category_id'] = '$categoryId';
    if (search != null && search.isNotEmpty) qp['search'] = search;
    if (readyMade != null) qp['ready_made'] = readyMade ? '1' : '0';

    final uri = Uri.parse('${ApiConfig.apiV1}/catalog/products')
        .replace(queryParameters: qp.isEmpty ? null : qp);
    final cacheKey = 'catalog_products_${uri.query}';

    try {
      final res = await http.get(uri);
      if (res.statusCode != 200) {
        throw CatalogApiException('Products ${res.statusCode}');
      }
      final map = json.decode(res.body) as Map<String, dynamic>;
      await AppCacheStore.writeJson(key: cacheKey, data: map);
      return _buildProductsResponse(
        map,
        fromCache: false,
        lastSyncedAt: DateTime.now(),
      );
    } catch (_) {
      final cached = AppCacheStore.readJson(cacheKey);
      if (cached != null && cached.data is Map) {
        return _buildProductsResponse(
          Map<String, dynamic>.from(cached.data as Map),
          fromCache: true,
          lastSyncedAt: cached.lastSyncedAt,
        );
      }
      rethrow;
    }
  }

  static CatalogProductsResponse _buildProductsResponse(
    Map<String, dynamic> map, {
    required bool fromCache,
    required DateTime lastSyncedAt,
  }) {
    final list = map['data'] as List<dynamic>? ?? [];
    final meta = map['meta'];
    final metaMap = meta is Map ? Map<String, dynamic>.from(meta) : const <String, dynamic>{};
    final arrivalsRaw = metaMap['new_arrivals'];
    final popularRaw = metaMap['popular_items'];
    final featuredCollectionsRaw = metaMap['featured_collections'];
    final arrivals = (arrivalsRaw is List ? arrivalsRaw : const <dynamic>[])
        .whereType<Map>()
        .map((e) => CatalogProduct.fromJson(Map<String, dynamic>.from(e)))
        .toList(growable: false);
    final popular = (popularRaw is List ? popularRaw : const <dynamic>[])
        .whereType<Map>()
        .map((e) => CatalogProduct.fromJson(Map<String, dynamic>.from(e)))
        .toList(growable: false);
    final featuredCollections =
        (featuredCollectionsRaw is List
                ? featuredCollectionsRaw
                : const <dynamic>[])
            .whereType<Map>()
            .map(
              (e) =>
                  FeaturedCollection.fromJson(Map<String, dynamic>.from(e)),
            )
            .toList(growable: false);

    return CatalogProductsResponse(
      products: list
          .map((e) => CatalogProduct.fromJson(e as Map<String, dynamic>))
          .toList(growable: false),
      newArrivals: arrivals,
      popularItems: popular,
      featuredCollections: featuredCollections,
      lastSyncedAt: lastSyncedAt,
      fromCache: fromCache,
    );
  }

  static Future<CatalogProduct> fetchProduct(int id) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/catalog/products/$id');
    final res = await http.get(uri);
    if (res.statusCode != 200) {
      throw CatalogApiException('Product ${res.statusCode}');
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final data = map['data'] as Map<String, dynamic>?;
    if (data == null) {
      throw CatalogApiException('Invalid product payload');
    }
    return CatalogProduct.fromJson(data);
  }
}

class CatalogProductsResponse {
  const CatalogProductsResponse({
    required this.products,
    required this.newArrivals,
    required this.popularItems,
    required this.featuredCollections,
    required this.lastSyncedAt,
    required this.fromCache,
  });

  final List<CatalogProduct> products;
  final List<CatalogProduct> newArrivals;
  final List<CatalogProduct> popularItems;
  final List<FeaturedCollection> featuredCollections;
  final DateTime lastSyncedAt;
  final bool fromCache;
}

class CatalogCategory {
  const CatalogCategory({
    required this.id,
    required this.name,
    required this.slug,
  });

  final int id;
  final String name;
  final String slug;

  factory CatalogCategory.fromJson(Map<String, dynamic> json) {
    final rawId = json['id'];
    return CatalogCategory(
      id: rawId is num ? rawId.toInt() : int.tryParse('$rawId') ?? 0,
      name: (json['name'] ?? 'Category').toString(),
      slug: (json['slug'] ?? '').toString(),
    );
  }
}

class FeaturedCollection {
  const FeaturedCollection({
    required this.id,
    required this.name,
    required this.slug,
    required this.publishedProductsCount,
    this.coverImageUrl,
  });

  final int id;
  final String name;
  final String slug;
  final int publishedProductsCount;
  final String? coverImageUrl;

  factory FeaturedCollection.fromJson(Map<String, dynamic> json) {
    final rawId = json['id'];
    final rawCount = json['published_products_count'];
    return FeaturedCollection(
      id: rawId is num ? rawId.toInt() : int.tryParse('$rawId') ?? 0,
      name: (json['name'] ?? 'Collection').toString(),
      slug: (json['slug'] ?? '').toString(),
      publishedProductsCount: rawCount is num
          ? rawCount.toInt()
          : int.tryParse('$rawCount') ?? 0,
      coverImageUrl: (json['cover_image_url'] ?? '').toString().trim().isEmpty
          ? null
          : (json['cover_image_url'] as String),
    );
  }
}
