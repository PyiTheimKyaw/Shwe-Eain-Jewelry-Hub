class PaymentReceipt {
  const PaymentReceipt({
    required this.id,
    required this.receiptNumber,
    required this.orderId,
    required this.amountMmk,
    required this.currencyCode,
    required this.issuedAt,
  });

  final int id;
  final String receiptNumber;
  final int orderId;
  final double amountMmk;
  final String currencyCode;
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
      issuedAt: DateTime.tryParse((json['issued_at'] ?? '').toString()),
    );
  }
}

