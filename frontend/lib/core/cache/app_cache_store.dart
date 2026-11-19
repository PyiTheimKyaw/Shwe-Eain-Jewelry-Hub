import 'package:hive_flutter/hive_flutter.dart';

class CachedJsonPayload {
  const CachedJsonPayload({
    required this.data,
    required this.lastSyncedAt,
  });

  final dynamic data;
  final DateTime lastSyncedAt;
}

class AppCacheStore {
  AppCacheStore._();

  static const _boxName = 'jh_app_cache_v1';
  static Box<dynamic>? _box;

  static Future<void> init() async {
    await Hive.initFlutter();
    _box ??= await Hive.openBox<dynamic>(_boxName);
  }

  static Future<void> writeJson({
    required String key,
    required dynamic data,
  }) async {
    final box = _box;
    if (box == null) {
      return;
    }
    await box.put(key, <String, dynamic>{
      'last_synced_at': DateTime.now().toIso8601String(),
      'data': data,
    });
  }

  static CachedJsonPayload? readJson(String key) {
    final box = _box;
    if (box == null) {
      return null;
    }
    final raw = box.get(key);
    if (raw is! Map) {
      return null;
    }
    final map = Map<String, dynamic>.from(raw);
    final synced = DateTime.tryParse(
      (map['last_synced_at'] ?? '').toString(),
    )?.toLocal();
    if (synced == null) {
      return null;
    }
    return CachedJsonPayload(data: map['data'], lastSyncedAt: synced);
  }
}
