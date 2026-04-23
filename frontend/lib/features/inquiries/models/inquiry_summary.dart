class InquirySummary {
  const InquirySummary({
    required this.id,
    required this.status,
    required this.message,
    required this.createdAt,
    required this.firebaseThreadId,
    this.productTitle,
    this.attachmentStoragePaths = const [],
  });

  final int id;
  final String status;
  final String message;
  final DateTime createdAt;
  final String? firebaseThreadId;
  final String? productTitle;
  final List<String> attachmentStoragePaths;

  factory InquirySummary.fromJson(Map<String, dynamic> json) {
    final product = json['product'];
    return InquirySummary(
      id: json['id'] as int,
      status: json['status'] as String,
      message: json['message'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
      firebaseThreadId: json['firebase_thread_id'] as String?,
      productTitle: product is Map<String, dynamic>
          ? product['title'] as String?
          : null,
      attachmentStoragePaths: ((json['attachments'] as List<dynamic>?) ?? const [])
          .map((e) => e is Map<String, dynamic> ? (e['storage_path'] ?? '').toString() : '')
          .where((path) => path.trim().isNotEmpty)
          .toList(growable: false),
    );
  }
}
