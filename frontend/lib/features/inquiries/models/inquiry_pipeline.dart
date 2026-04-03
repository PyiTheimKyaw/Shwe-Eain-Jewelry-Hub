/// Concierge pipeline step (mirrors backend [InquiryPipelinePresenter]).
class InquiryPipeline {
  const InquiryPipeline({
    required this.step,
    this.orderId,
    this.orderStatus,
    this.activeQuoteId,
    this.orderNumber,
    this.depositState,
    this.depositAmountMmk,
    this.depositInstructionsSentAt,
    this.depositPayment,
  });

  final String step;
  final int? orderId;
  final String? orderStatus;
  final int? activeQuoteId;
  final String? orderNumber;

  /// Backend: none | instructions_sent | proof_submitted | confirmed | rejected
  final String? depositState;
  final String? depositAmountMmk;
  final String? depositInstructionsSentAt;

  /// Same keys as Firestore `payment_info_card` when MySQL has instructions (REST-only).
  final Map<String, dynamic>? depositPayment;

  factory InquiryPipeline.fromJson(Map<String, dynamic> json) {
    final order = json['order'];
    String? orderNum;
    String? ordStatus;
    if (order is Map<String, dynamic>) {
      orderNum = order['order_number'] as String?;
      ordStatus = order['status'] as String?;
    }
    final rawDp = json['deposit_payment'];
    Map<String, dynamic>? depositPayment;
    if (rawDp is Map<String, dynamic>) {
      depositPayment = rawDp;
    }
    return InquiryPipeline(
      step: json['step'] as String? ?? 'consulting',
      orderId: json['order_id'] as int?,
      orderStatus: json['order_status'] as String? ?? ordStatus,
      activeQuoteId: json['active_quote_id'] as int?,
      orderNumber: orderNum,
      depositState: json['deposit_state'] as String?,
      depositAmountMmk: json['deposit_amount_mmk'] as String?,
      depositInstructionsSentAt: json['deposit_instructions_sent_at'] as String?,
      depositPayment: depositPayment,
    );
  }

  static InquiryPipeline? fromFirestore(Map<String, dynamic>? data) {
    if (data == null) {
      return null;
    }
    final step = data['pipeline_step'] as String?;
    if (step == null) {
      return null;
    }
    final oid = data['order_id'];
    final aq = data['active_quote_id'];
    return InquiryPipeline(
      step: step,
      orderId: oid is num ? oid.toInt() : null,
      orderStatus: data['order_status'] as String?,
      activeQuoteId: aq is num ? aq.toInt() : null,
      orderNumber: data['order_number'] as String?,
      depositState: data['deposit_state'] as String?,
      depositAmountMmk: data['deposit_amount_mmk'] as String?,
      depositInstructionsSentAt: null,
      depositPayment: null,
    );
  }
}
