class ArtisanNotificationItem {
  const ArtisanNotificationItem({
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

  String get title {
    final message = data['message'];
    if (message is String && message.trim().isNotEmpty) {
      return message.trim();
    }
    return 'Order update';
  }

  String get subtitle {
    final orderNumber = data['order_number'];
    final inquiryId = data['inquiry_id'];
    final parts = <String>[];
    if (orderNumber is String && orderNumber.trim().isNotEmpty) {
      parts.add('Order ${orderNumber.trim()}');
    }
    if (inquiryId != null) {
      parts.add('Inquiry #$inquiryId');
    }
    if (parts.isEmpty) {
      return type;
    }
    return parts.join(' · ');
  }

  int? get orderId {
    final raw = data['order_id'];
    if (raw is int) {
      return raw;
    }
    if (raw is num) {
      return raw.toInt();
    }
    if (raw is String) {
      return int.tryParse(raw);
    }
    return null;
  }

  factory ArtisanNotificationItem.fromJson(Map<String, dynamic> json) {
    DateTime? parseDate(dynamic value) {
      if (value is! String || value.trim().isEmpty) {
        return null;
      }
      return DateTime.tryParse(value);
    }

    final rawData = json['data'];
    return ArtisanNotificationItem(
      id: (json['id'] ?? '').toString(),
      type: (json['type'] ?? '').toString(),
      data: rawData is Map<String, dynamic>
          ? rawData
          : Map<String, dynamic>.from(rawData as Map? ?? const {}),
      readAt: parseDate(json['read_at']),
      createdAt: parseDate(json['created_at']),
    );
  }
}
