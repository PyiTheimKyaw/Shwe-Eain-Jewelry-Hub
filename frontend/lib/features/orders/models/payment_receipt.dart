class PaymentReceipt {
  const PaymentReceipt({
    required this.id,
    required this.receiptNumber,
    required this.orderId,
    required this.amountMmk,
    required this.currencyCode,
    required this.paymentType,
    this.paymentMethodLabel,
    this.isFullyPaid = false,
    required this.issuedAt,
  });

  final int id;
  final String receiptNumber;
  final int orderId;
  final double amountMmk;
  final String currencyCode;
  final String paymentType;
  final String? paymentMethodLabel;
  final bool isFullyPaid;
  final DateTime? issuedAt;

  factory PaymentReceipt.fromJson(Map<String, dynamic> json) {
    final rawAmount = json['amount_mmk'];
    final amount = rawAmount is num
        ? rawAmount.toDouble()
        : double.tryParse('$rawAmount') ?? 0;
    final rawId = json['id'];
    final parsedId = rawId is num ? rawId.toInt() : int.tryParse('$rawId') ?? 0;
    final rawOrderId = json['order_id'];
    final parsedOrderId = rawOrderId is num
        ? rawOrderId.toInt()
        : int.tryParse('$rawOrderId') ?? 0;
    final meta = json['meta_json'];
    Map<String, dynamic>? metaMap;
    if (meta is Map<String, dynamic>) {
      metaMap = meta;
    } else if (meta is Map) {
      metaMap = Map<String, dynamic>.from(meta);
    }

    return PaymentReceipt(
      id: parsedId,
      receiptNumber: (json['receipt_number'] ?? '-').toString(),
      orderId: parsedOrderId,
      amountMmk: amount,
      currencyCode: (json['currency_code'] ?? 'MMK').toString(),
      paymentType: (() {
        final topLevel = (json['payment_type'] ?? '').toString().trim();
        if (topLevel.isNotEmpty) {
          return topLevel;
        }
        if (metaMap != null) {
          return (metaMap['payment_type'] ?? 'deposit').toString();
        }
        return 'deposit';
      })(),
      paymentMethodLabel: (() {
        final topLevel = (json['payment_method_label'] ?? '').toString().trim();
        if (topLevel.isNotEmpty) {
          return topLevel;
        }
        final fromMeta =
            (metaMap?['payment_method_label'] ?? '').toString().trim();
        return fromMeta.isEmpty ? null : fromMeta;
      })(),
      isFullyPaid: (() {
        final topLevel = json['is_fully_paid'];
        if (topLevel is bool) {
          return topLevel;
        }
        final fromMeta = metaMap?['is_fully_paid'];
        if (fromMeta is bool) {
          return fromMeta;
        }
        return false;
      })(),
      issuedAt: DateTime.tryParse((json['issued_at'] ?? '').toString()),
    );
  }
}
