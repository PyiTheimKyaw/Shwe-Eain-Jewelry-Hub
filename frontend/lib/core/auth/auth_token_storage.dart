import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the Laravel Sanctum plain-text token between app launches.
class AuthTokenStorage {
  AuthTokenStorage._();

  static const _key = 'sanctum_token';

  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static Future<String?> read() => _storage.read(key: _key);

  static Future<void> write(String token) =>
      _storage.write(key: _key, value: token);

  static Future<void> clear() => _storage.delete(key: _key);
}
