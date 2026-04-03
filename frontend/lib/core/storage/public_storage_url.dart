import 'package:jewelry_hub/core/config/api_config.dart';

/// Builds a public URL for a path on Laravel's `public` disk (e.g. inquiry attachments).
String publicStorageUrl(String storagePath) {
  final base = ApiConfig.baseUrl.replaceAll(RegExp(r'/$'), '');
  final path = storagePath.startsWith('/') ? storagePath.substring(1) : storagePath;
  return '$base/storage/$path';
}
