class AppNotificationItem {
  const AppNotificationItem({
    required this.id,
    required this.type,
    required this.data,
    this.readAt,
    this.createdAt,
  });

  final String id;
  final String type;
  final Map<String, dynamic> data;
  final DateTime? readAt;
  final DateTime? createdAt;

  bool get isRead => readAt != null;

  String get title {
    final t = data['title'];
    if (t is String && t.trim().isNotEmpty) {
      return t.trim();
    }
    return 'Notification';
  }

  String get body {
    final b = data['body'];
    if (b is String && b.trim().isNotEmpty) {
      return b.trim();
    }
    return type;
  }

  int? _asInt(dynamic raw) {
    if (raw is int) return raw;
    if (raw is num) return raw.toInt();
    if (raw is String) return int.tryParse(raw);
    return null;
  }

  int? get inquiryId => _asInt(data['inquiry_id']);
  int? get orderId => _asInt(data['order_id']);
  int? get paymentId => _asInt(data['payment_id']);
  int? get quoteId => _asInt(data['quote_id']);
  String? get orderNumber {
    final v = data['order_number'];
    return v is String && v.trim().isNotEmpty ? v.trim() : null;
  }

  factory AppNotificationItem.fromJson(Map<String, dynamic> json) {
    DateTime? parse(dynamic value) {
      if (value is! String || value.trim().isEmpty) return null;
      return DateTime.tryParse(value);
    }

    final rawData = json['data'];
    return AppNotificationItem(
      id: (json['id'] ?? '').toString(),
      type: (json['type'] ?? '').toString(),
      data: rawData is Map<String, dynamic>
          ? rawData
          : Map<String, dynamic>.from(rawData as Map? ?? const {}),
      readAt: parse(json['read_at']),
      createdAt: parse(json['created_at']),
    );
  }
}
