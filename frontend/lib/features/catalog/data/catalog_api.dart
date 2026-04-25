import 'dart:convert';

import 'package:http/http.dart' as http;
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

  static Future<List<CatalogCategory>> fetchCategories() async {
    final uri = Uri.parse('${ApiConfig.apiV1}/catalog/categories');
    final res = await http.get(uri);
    if (res.statusCode != 200) {
      throw CatalogApiException('Categories ${res.statusCode}');
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    return list
        .map((e) => CatalogCategory.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList(growable: false);
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

    final res = await http.get(uri);
    if (res.statusCode != 200) {
      throw CatalogApiException('Products ${res.statusCode}');
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>? ?? [];
    final meta = map['meta'];
    final metaMap = meta is Map ? Map<String, dynamic>.from(meta) : const <String, dynamic>{};
    final arrivalsRaw = metaMap['new_arrivals'];
    final arrivals = (arrivalsRaw is List ? arrivalsRaw : const <dynamic>[])
        .whereType<Map>()
        .map((e) => CatalogProduct.fromJson(Map<String, dynamic>.from(e)))
        .toList(growable: false);

    return CatalogProductsResponse(
      products: list
          .map((e) => CatalogProduct.fromJson(e as Map<String, dynamic>))
          .toList(growable: false),
      newArrivals: arrivals,
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
  });

  final List<CatalogProduct> products;
  final List<CatalogProduct> newArrivals;
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
