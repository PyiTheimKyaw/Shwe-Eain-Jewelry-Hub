import 'package:jewelry_hub/core/config/api_config.dart';

/// Builds a public URL for a path on Laravel's `public` disk (e.g. inquiry attachments).
String publicStorageUrl(String storagePath) {
  final raw = storagePath.trim();
  if (raw.startsWith('http://') || raw.startsWith('https://')) {
    return raw;
  }
  final base = ApiConfig.baseUrl.replaceAll(RegExp(r'/$'), '');
  final path = raw.startsWith('/') ? raw.substring(1) : raw;
  return '$base/storage/$path';
}
