/// Concierge pipeline step (mirrors backend [InquiryPipelinePresenter]).
class InquiryPipeline {
  const InquiryPipeline({
    required this.step,
    this.orderId,
    this.orderStatus,
    this.activeQuoteId,
    this.activeQuoteStatus,
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

  /// Latest sent/accepted quote status from API (`active_quote.status`): `sent` | `accepted` | …
  final String? activeQuoteStatus;
  final String? orderNumber;

  /// Backend: none | instructions_sent | proof_submitted | confirmed | rejected
  final String? depositState;
  final String? depositAmountMmk;
  final String? depositInstructionsSentAt;

  /// Same keys as Firestore `payment_info_card` when MySQL has instructions (REST-only).
  final Map<String, dynamic>? depositPayment;

  /// Additional deposit still required on the current order (supersede / top-up), from API
  /// `deposit_payment.additional_deposit_due_mmk` or root `deposit_amount_mmk`. Null if unknown.
  double? resolvedAdditionalDepositDueMmk() {
    final dp = depositPayment?['additional_deposit_due_mmk'];
    final fromPayment = dp?.toString().trim() ?? '';
    if (fromPayment.isNotEmpty) {
      return double.tryParse(fromPayment);
    }
    final root = depositAmountMmk?.trim() ?? '';
    if (root.isEmpty) {
      return null;
    }
    return double.tryParse(root);
  }

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
    String? activeQuoteStatus;
    final rawAq = json['active_quote'];
    if (rawAq is Map<String, dynamic>) {
      activeQuoteStatus = rawAq['status'] as String?;
    }
    return InquiryPipeline(
      step: json['step'] as String? ?? 'consulting',
      orderId: json['order_id'] as int?,
      orderStatus: json['order_status'] as String? ?? ordStatus,
      activeQuoteId: json['active_quote_id'] as int?,
      activeQuoteStatus: activeQuoteStatus,
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
      activeQuoteStatus: data['active_quote_status'] as String?,
      orderNumber: data['order_number'] as String?,
      depositState: data['deposit_state'] as String?,
      depositAmountMmk: data['deposit_amount_mmk'] as String?,
      depositInstructionsSentAt: null,
      depositPayment: null,
    );
  }
}
