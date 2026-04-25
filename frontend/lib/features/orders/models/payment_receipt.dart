class PaymentReceipt {
  const PaymentReceipt({
    required this.id,
    required this.receiptNumber,
    required this.orderId,
    required this.amountMmk,
    required this.currencyCode,
    required this.paymentType,
    required this.issuedAt,
  });

  final int id;
  final String receiptNumber;
  final int orderId;
  final double amountMmk;
  final String currencyCode;
  final String paymentType;
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
        final meta = json['meta_json'];
        if (meta is Map<String, dynamic>) {
          return (meta['payment_type'] ?? 'deposit').toString();
        }
        if (meta is Map) {
          return (meta['payment_type'] ?? 'deposit').toString();
        }
        return 'deposit';
      })(),
      issuedAt: DateTime.tryParse((json['issued_at'] ?? '').toString()),
    );
  }
}
