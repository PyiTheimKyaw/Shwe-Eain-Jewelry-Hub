class OrderProgressEvent {
  const OrderProgressEvent({
    required this.id,
    required this.stage,
    required this.note,
    required this.artisanName,
    required this.createdAt,
    required this.attachmentUrls,
  });

  final int id;
  final String stage;
  final String? note;
  final String? artisanName;
  final DateTime? createdAt;
  final List<String> attachmentUrls;

  factory OrderProgressEvent.fromJson(
    Map<String, dynamic> json, {
    required String Function(String) storageUrlBuilder,
  }) {
    final rawId = json['id'];
    final id = rawId is num ? rawId.toInt() : int.tryParse('$rawId') ?? 0;
    final rawAttachments = json['attachments'] as List<dynamic>? ?? const [];
    final urls = <String>[];
    for (final item in rawAttachments) {
      final map = Map<String, dynamic>.from(item as Map);
      final path = (map['storage_path'] ?? '').toString().trim();
      if (path.isEmpty) {
        continue;
      }
      urls.add(storageUrlBuilder(path));
    }
    final artisan = json['artisan'] is Map
        ? Map<String, dynamic>.from(json['artisan'] as Map)
        : const <String, dynamic>{};
    return OrderProgressEvent(
      id: id,
      stage: (json['stage'] ?? 'other').toString(),
      note: (json['note'] ?? '').toString().trim().isEmpty
          ? null
          : (json['note'] ?? '').toString().trim(),
      artisanName: (artisan['name'] ?? '').toString().trim().isEmpty
          ? null
          : (artisan['name'] ?? '').toString().trim(),
      createdAt: DateTime.tryParse((json['created_at'] ?? '').toString()),
      attachmentUrls: urls,
    );
  }
}
