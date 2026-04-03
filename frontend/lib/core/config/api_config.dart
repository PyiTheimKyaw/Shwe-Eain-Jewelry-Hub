/// Laravel API base. Override at build time, e.g.
/// `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000`
/// Catalog pricing uses MMK as stored base; USD is derived via `mmk_per_usd`.
class ApiConfig {
  ApiConfig._();

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000',
  );

  static String get apiV1 => '$baseUrl/api/v1';
}
