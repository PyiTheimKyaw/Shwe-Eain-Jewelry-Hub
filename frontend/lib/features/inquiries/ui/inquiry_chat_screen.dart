import 'dart:async';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart' as auth;
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_gold_button.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/inquiries/data/inquiry_api.dart';
import 'package:jewelry_hub/features/inquiries/models/inquiry_pipeline.dart';
import 'package:jewelry_hub/features/inquiries/models/inquiry_summary.dart';
import 'package:jewelry_hub/features/inquiries/widgets/concierge_pipeline_stepper.dart';
import 'package:jewelry_hub/features/inquiries/widgets/inquiry_api_quote_banner.dart';
import 'package:jewelry_hub/features/inquiries/widgets/inquiry_payment_info_card.dart';
import 'package:jewelry_hub/features/inquiries/widgets/inquiry_quote_card.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:image_picker/image_picker.dart';
import 'package:jewelry_hub/features/inquiries/widgets/inquiry_status_badge.dart';
import 'package:provider/provider.dart';

class InquiryChatScreen extends StatefulWidget {
  const InquiryChatScreen({super.key, required this.inquiry});

  final InquirySummary inquiry;

  @override
  State<InquiryChatScreen> createState() => _InquiryChatScreenState();
}

class _InquiryChatScreenState extends State<InquiryChatScreen> {
  final _composer = TextEditingController();
  final _scrollController = ScrollController();

  bool _connecting = true;
  String? _error;
  bool _sending = false;
  bool _uploadingDeposit = false;
  InquiryPipeline? _restPipeline;
  final Set<int> _actedQuoteIds = {};
  int? _quoteActionBusyId;
  bool _didInitialScrollToBottom = false;
  Timer? _pipelineRefreshDebounce;
  StreamSubscription<DocumentSnapshot<Map<String, dynamic>>>? _threadPipelineSub;
  StreamSubscription<QuerySnapshot<Map<String, dynamic>>>? _messagesPipelineSub;

  /// Keeps REST pipeline (quote API banner, deposit_payment) in sync when Firestore updates.
  void _schedulePipelineRefresh() {
    _pipelineRefreshDebounce?.cancel();
    _pipelineRefreshDebounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) {
        _loadPipeline();
      }
    });
  }

  void _attachPipelineSyncListeners(String threadId) {
    _threadPipelineSub?.cancel();
    _messagesPipelineSub?.cancel();
    _threadPipelineSub = FirebaseFirestore.instance
        .collection('inquiry_threads')
        .doc(threadId)
        .snapshots()
        .listen((_) => _schedulePipelineRefresh());
    _messagesPipelineSub = FirebaseFirestore.instance
        .collection('inquiry_threads')
        .doc(threadId)
        .collection('messages')
        .orderBy('created_at', descending: false)
        .snapshots()
        .listen((_) => _schedulePipelineRefresh());
  }

  void _scrollToBottom({bool animated = false}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_scrollController.hasClients) {
        return;
      }
      final max = _scrollController.position.maxScrollExtent;
      if (max <= 0) {
        return;
      }
      if (animated) {
        _scrollController.animateTo(
          max,
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
        );
      } else {
        _scrollController.jumpTo(max);
      }
    });
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _connect();
      _loadPipeline();
    });
  }

  Future<void> _connect() async {
    final threadId = widget.inquiry.firebaseThreadId;
    if (threadId == null) {
      setState(() {
        _connecting = false;
        _error = 'This inquiry has no chat thread yet.';
      });
      return;
    }

    final bearer = context.read<AuthProvider>().token;
    if (bearer == null) {
      setState(() {
        _connecting = false;
        _error = 'Not signed in.';
      });
      return;
    }

    try {
      final creds = await InquiryApi.fetchFirebaseChatToken(
        bearerToken: bearer,
        inquiryId: widget.inquiry.id,
      );
      await auth.FirebaseAuth.instance.signInWithCustomToken(creds.token);
      if (mounted) {
        _attachPipelineSyncListeners(threadId);
        setState(() {
          _connecting = false;
          _error = null;
        });
      }
    } on auth.FirebaseAuthException catch (e) {
      if (mounted) {
        setState(() {
          _connecting = false;
          _error =
              '${e.code}: ${e.message ?? "no message"}. If unknown: check Firebase App Check (Auth not enforced without debug), device internet, then full restart after flutter clean.';
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _connecting = false;
          _error = '$e';
        });
      }
    }
  }

  Future<void> _loadPipeline() async {
    final bearer = context.read<AuthProvider>().token;
    if (bearer == null) {
      return;
    }
    try {
      final p = await InquiryApi.fetchPipeline(
        bearerToken: bearer,
        inquiryId: widget.inquiry.id,
      );
      if (mounted) {
        setState(() {
          _restPipeline = p ??
              InquiryPipeline(
                step: widget.inquiry.status == 'quoted' ? 'quoted' : 'consulting',
              );
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _restPipeline = InquiryPipeline(
            step: widget.inquiry.status == 'quoted' ? 'quoted' : 'consulting',
          );
        });
      }
    }
  }

  InquiryPipeline _effectivePipeline(InquiryPipeline? fromFirestore) {
    final rest = _restPipeline ??
        InquiryPipeline(
          step: widget.inquiry.status == 'quoted' ? 'quoted' : 'consulting',
        );
    if (fromFirestore == null) {
      return rest;
    }
    final mergedActiveQuoteId = rest.activeQuoteId ?? fromFirestore.activeQuoteId;
    String? mergedActiveQuoteStatus;
    if (mergedActiveQuoteId != null && mergedActiveQuoteId == rest.activeQuoteId) {
      mergedActiveQuoteStatus = rest.activeQuoteStatus;
    } else if (mergedActiveQuoteId != null &&
        mergedActiveQuoteId == fromFirestore.activeQuoteId) {
      mergedActiveQuoteStatus = fromFirestore.activeQuoteStatus;
    } else {
      mergedActiveQuoteStatus = rest.activeQuoteStatus ?? fromFirestore.activeQuoteStatus;
    }
    return InquiryPipeline(
      step: fromFirestore.step,
      orderId: rest.orderId ?? fromFirestore.orderId,
      orderStatus: rest.orderStatus ?? fromFirestore.orderStatus,
      activeQuoteId: mergedActiveQuoteId,
      activeQuoteStatus: mergedActiveQuoteStatus,
      orderNumber: fromFirestore.orderNumber ?? rest.orderNumber,
      depositState: fromFirestore.depositState ?? rest.depositState,
      depositAmountMmk: rest.depositAmountMmk ?? fromFirestore.depositAmountMmk,
      depositInstructionsSentAt:
          fromFirestore.depositInstructionsSentAt ?? rest.depositInstructionsSentAt,
      depositPayment: rest.depositPayment,
    );
  }

  bool _canUploadDeposit(InquiryPipeline p) {
    if (p.orderId == null) {
      return false;
    }
    final ds = p.depositState ?? '';
    if (ds == 'proof_submitted') {
      return false;
    }
    final due = p.resolvedAdditionalDepositDueMmk();
    if (due != null) {
      if (due > 0) {
        // Supersede/top-up path: if additional due remains positive, keep upload CTA available
        // even if thread metadata/order status is briefly stale after a quote revision.
        return true;
      }
      return false;
    }
    if (p.orderStatus != 'awaiting_deposit') {
      return false;
    }
    if (ds == 'confirmed') {
      return false;
    }
    if (ds == 'instructions_sent' || ds == 'rejected') {
      return true;
    }
    return p.depositInstructionsSentAt != null;
  }

  Future<void> _pickAndUploadDeposit(InquiryPipeline pipeline) async {
    final oid = pipeline.orderId;
    if (oid == null) {
      return;
    }
    final bearer = context.read<AuthProvider>().token;
    if (bearer == null) {
      return;
    }
    final file = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (file == null) {
      return;
    }
    setState(() => _uploadingDeposit = true);
    try {
      await InquiryApi.uploadDepositProof(
        bearerToken: bearer,
        orderId: oid,
        proof: file,
      );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Payment proof submitted.')),
      );
      await _loadPipeline();
    } on InquiryApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message)),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _uploadingDeposit = false);
      }
    }
  }

  Future<void> _send() async {
    final text = _composer.text.trim();
    if (text.isEmpty || _sending) {
      return;
    }
    final threadId = widget.inquiry.firebaseThreadId;
    if (threadId == null) {
      return;
    }
    final uid = context.read<AuthProvider>().user?.id;
    setState(() => _sending = true);
    try {
      await FirebaseFirestore.instance
          .collection('inquiry_threads')
          .doc(threadId)
          .collection('messages')
          .add({
            'text': text,
            'sender_role': 'customer',
            'created_at': FieldValue.serverTimestamp(),
            'laravel_user_id': uid,
          });
      _composer.clear();
      _scrollToBottom(animated: true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Send failed: $e')));
      }
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  Future<void> _acceptQuote(int quoteId) async {
    final bearer = context.read<AuthProvider>().token;
    if (bearer == null) {
      return;
    }
    setState(() => _quoteActionBusyId = quoteId);
    try {
      final r = await InquiryApi.acceptQuote(
        bearerToken: bearer,
        quoteId: quoteId,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _actedQuoteIds.add(quoteId);
        _quoteActionBusyId = null;
        final order = r.order;
        final num = order['order_number'] as String?;
        _restPipeline = InquiryPipeline(
          step: 'awaiting_deposit',
          orderId: order['id'] as int?,
          orderStatus: order['status'] as String?,
          orderNumber: num,
        );
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Order ${r.order['order_number'] ?? ''} — awaiting deposit.',
          ),
        ),
      );
      await _loadPipeline();
    } on InquiryApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) {
        setState(() => _quoteActionBusyId = null);
      }
    }
  }

  Future<void> _declineQuote(int quoteId) async {
    final bearer = context.read<AuthProvider>().token;
    if (bearer == null) {
      return;
    }
    setState(() => _quoteActionBusyId = quoteId);
    try {
      await InquiryApi.declineQuote(bearerToken: bearer, quoteId: quoteId);
      if (!mounted) {
        return;
      }
      setState(() {
        _actedQuoteIds.add(quoteId);
        _quoteActionBusyId = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Quote declined. We will follow up soon.')),
      );
      await _loadPipeline();
    } on InquiryApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _quoteActionBusyId = null);
      }
    }
  }

  @override
  void dispose() {
    _pipelineRefreshDebounce?.cancel();
    _threadPipelineSub?.cancel();
    _messagesPipelineSub?.cancel();
    _composer.dispose();
    _scrollController.dispose();
    if (Firebase.apps.isNotEmpty) {
      try {
        auth.FirebaseAuth.instance.signOut();
      } catch (_) {}
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final threadId = widget.inquiry.firebaseThreadId;
    final timeFmt = DateFormat.jm();

    return Scaffold(
      resizeToAvoidBottomInset: true,
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(8, 4, 8, 8),
                child: Row(
                  children: [
                    IconButton(
                      icon: const Icon(
                        Icons.arrow_back_ios_new_rounded,
                        color: AppColors.textPrimary,
                      ),
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          PremiumText.headline('Concierge', maxLines: 1),
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              InquiryStatusBadge(status: widget.inquiry.status),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              if (_connecting)
                const Expanded(
                  child: Center(
                    child: CircularProgressIndicator(
                      color: AppColors.gold,
                      strokeWidth: 2,
                    ),
                  ),
                )
              else if (_error != null)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: AppGlassCard(
                      child: PremiumText.body(
                        _error!,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                )
              else if (threadId == null)
                const Expanded(child: Center(child: Text('No thread id')))
              else
                Expanded(
                  child: StreamBuilder<DocumentSnapshot<Map<String, dynamic>>>(
                    stream: FirebaseFirestore.instance
                        .collection('inquiry_threads')
                        .doc(threadId)
                        .snapshots(),
                    builder: (context, threadSnap) {
                      final threadData = threadSnap.data?.data();
                      final fromFs = InquiryPipeline.fromFirestore(threadData);
                      final effectivePipeline = _effectivePipeline(fromFs);

                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Expanded(
                            child: StreamBuilder<
                                QuerySnapshot<Map<String, dynamic>>>(
                              stream: FirebaseFirestore.instance
                                  .collection('inquiry_threads')
                                  .doc(threadId)
                                  .collection('messages')
                                  .orderBy('created_at', descending: false)
                                  .snapshots(),
                              builder: (context, msgSnap) {
                                if (msgSnap.hasError) {
                                  return Center(
                                    child: Padding(
                                      padding: const EdgeInsets.all(24),
                                      child: Text('${msgSnap.error}'),
                                    ),
                                  );
                                }
                                if (!msgSnap.hasData) {
                                  return const Center(
                                    child: CircularProgressIndicator(
                                      color: AppColors.gold,
                                      strokeWidth: 2,
                                    ),
                                  );
                                }
                                final docs = msgSnap.data!.docs;
                                final activeQuoteId =
                                    effectivePipeline.activeQuoteId;
                                final hasFsQuoteCard = activeQuoteId != null &&
                                    docs.any((doc) {
                                      final d = doc.data();
                                      return d['type'] == 'quote_card' &&
                                          (d['quote_id'] as num?)?.toInt() ==
                                              activeQuoteId;
                                    });
                                final showApiQuoteBanner = activeQuoteId !=
                                        null &&
                                    !hasFsQuoteCard;
                                final showApiDepositPayment =
                                    _showApiDepositPaymentCard(
                                  effectivePipeline,
                                  docs,
                                );

                                if (!_didInitialScrollToBottom &&
                                    docs.isNotEmpty) {
                                  _didInitialScrollToBottom = true;
                                  WidgetsBinding.instance
                                      .addPostFrameCallback((_) {
                                    if (!mounted) {
                                      return;
                                    }
                                    _scrollToBottom();
                                  });
                                }

                                return CustomScrollView(
                                  controller: _scrollController,
                                  keyboardDismissBehavior:
                                      ScrollViewKeyboardDismissBehavior.onDrag,
                                  slivers: [
                                    SliverToBoxAdapter(
                                      child: Padding(
                                        padding: const EdgeInsets.fromLTRB(
                                          12,
                                          0,
                                          12,
                                          8,
                                        ),
                                        child: ConciergePipelineStepper(
                                          pipeline: effectivePipeline,
                                        ),
                                      ),
                                    ),
                                    if (showApiQuoteBanner)
                                      SliverToBoxAdapter(
                                        child: InquiryApiQuoteBanner(
                                          key: ValueKey(activeQuoteId),
                                          quoteId: activeQuoteId,
                                          pipeline: effectivePipeline,
                                          onAcceptQuote: _acceptQuote,
                                          onDeclineQuote: _declineQuote,
                                          quoteBusyId: _quoteActionBusyId,
                                          actedQuoteIds: _actedQuoteIds,
                                        ),
                                      ),
                                    if (showApiDepositPayment)
                                      SliverToBoxAdapter(
                                        child: Padding(
                                          padding: const EdgeInsets.fromLTRB(
                                            12,
                                            0,
                                            12,
                                            8,
                                          ),
                                          child: _apiDepositPaymentFromPipeline(
                                            effectivePipeline,
                                          ),
                                        ),
                                      ),
                                    SliverPadding(
                                      padding: const EdgeInsets.fromLTRB(
                                        12,
                                        0,
                                        12,
                                        12,
                                      ),
                                      sliver: SliverList(
                                        delegate:
                                            SliverChildBuilderDelegate(
                                          (context, index) {
                                            final doc = docs[index];
                                            final d = doc.data();
                                            return _MessageTile(
                                              data: d,
                                              timeFmt: timeFmt,
                                              onAcceptQuote: _acceptQuote,
                                              onDeclineQuote: _declineQuote,
                                              quoteBusyId: _quoteActionBusyId,
                                              actedQuoteIds: _actedQuoteIds,
                                              pipeline: effectivePipeline,
                                            );
                                          },
                                          childCount: docs.length,
                                        ),
                                      ),
                                    ),
                                  ],
                                );
                              },
                            ),
                          ),
                          if (_canUploadDeposit(effectivePipeline))
                            Padding(
                              padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                              child: OutlinedButton.icon(
                                onPressed: _uploadingDeposit
                                    ? null
                                    : () =>
                                        _pickAndUploadDeposit(effectivePipeline),
                                icon: _uploadingDeposit
                                    ? const SizedBox(
                                        width: 16,
                                        height: 16,
                                        child: CircularProgressIndicator(
                                          color: AppColors.gold,
                                          strokeWidth: 2,
                                        ),
                                      )
                                    : const Icon(Icons.upload_file_outlined),
                                label: Text(
                                  _uploadingDeposit
                                      ? 'Uploading…'
                                      : 'Upload payment proof',
                                ),
                              ),
                            ),
                          Padding(
                            padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Expanded(
                                  child: TextField(
                                    controller: _composer,
                                    minLines: 1,
                                    maxLines: 4,
                                    maxLength: 4000,
                                    decoration: const InputDecoration(
                                      labelText: 'Message',
                                      counterText: '',
                                    ),
                                    onSubmitted: (_) => _send(),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                _sending
                                    ? const Padding(
                                        padding: EdgeInsets.all(12),
                                        child: SizedBox(
                                          width: 24,
                                          height: 24,
                                          child: CircularProgressIndicator(
                                            color: AppColors.gold,
                                            strokeWidth: 2,
                                          ),
                                        ),
                                      )
                                    : AppGoldButton(
                                        label: 'Send',
                                        icon: Icons.send_rounded,
                                        onPressed: _send,
                                      ),
                              ],
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

String? _firestoreString(dynamic v) {
  if (v == null) {
    return null;
  }
  if (v is String) {
    return v;
  }
  return v.toString();
}

String _messageTypeKey(Map<String, dynamic> data) {
  final raw = data['type'];
  if (raw == null) {
    return '';
  }
  if (raw is String) {
    return raw;
  }
  return raw.toString();
}

/// Recognises server-written payment cards even if [type] is missing (older writes / SDK quirks).
bool _isPaymentInfoPayload(Map<String, dynamic> data) {
  final t = _messageTypeKey(data);
  if (t == 'payment_info_card') {
    return true;
  }
  if (data['sender_role'] != 'admin') {
    return false;
  }
  if (data.containsKey('quote_id')) {
    return false;
  }
  if (data['payment_profile_id'] != null) {
    return true;
  }
  return data['order_number'] != null &&
      data['amount_mmk'] != null &&
      data.containsKey('kpay_id');
}

/// When Firestore never received `payment_info_card`, show the same data from REST (MySQL).
bool _showApiDepositPaymentCard(
  InquiryPipeline pipeline,
  List<QueryDocumentSnapshot<Map<String, dynamic>>> docs,
) {
  final orderNum = pipeline.orderNumber;
  if (orderNum == null ||
      pipeline.orderStatus != 'awaiting_deposit' ||
      pipeline.depositPayment == null) {
    return false;
  }
  final hasFs = docs.any((doc) {
    final d = doc.data();
    if (!_isPaymentInfoPayload(d)) {
      return false;
    }
    return _firestoreString(d['order_number']) == orderNum;
  });
  return !hasFs;
}

Widget _apiDepositPaymentFromPipeline(InquiryPipeline pipeline) {
  final m = pipeline.depositPayment!;
  return InquiryPaymentInfoCard(
    orderNumber: (m['order_number'] as String?) ?? '',
    amountMmk: m['amount_mmk']?.toString() ?? '0',
    kpayId: m['kpay_id'] as String? ?? '',
    bankName: m['bank_name'] as String? ?? '',
    bankAccountNumber: m['bank_account_number'] as String? ?? '',
    bankAccountName: m['bank_account_name'] as String? ?? '',
    paymentMethodLabel: m['payment_method_label'] as String?,
    qrImageUrl: m['qr_image_url'] as String?,
    orderTotalMmk: m['order_total_mmk']?.toString(),
    creditedDepositMmk: m['credited_deposit_mmk']?.toString(),
    balanceRemainingMmk: m['balance_remaining_mmk']?.toString(),
  );
}

/// Accept/Decline only when this card is the pipeline active quote in `sent` state,
/// not merely when there is no order (superseding quotes after an order still need actions).
bool _quoteCardShowActions({
  required int? quoteId,
  required InquiryPipeline? pipeline,
  required bool acted,
}) {
  if (quoteId == null || acted) {
    return false;
  }
  final activeId = pipeline?.activeQuoteId;
  final st = pipeline?.activeQuoteStatus;
  if (activeId == quoteId) {
    return st == 'sent' || st == null;
  }
  if (activeId == null && pipeline?.orderId == null) {
    return true;
  }
  return false;
}

class _MessageTile extends StatelessWidget {
  const _MessageTile({
    required this.data,
    required this.timeFmt,
    required this.onAcceptQuote,
    required this.onDeclineQuote,
    required this.quoteBusyId,
    required this.actedQuoteIds,
    required this.pipeline,
  });

  final Map<String, dynamic> data;
  final DateFormat timeFmt;
  final Future<void> Function(int) onAcceptQuote;
  final Future<void> Function(int) onDeclineQuote;
  final int? quoteBusyId;
  final Set<int> actedQuoteIds;
  final InquiryPipeline? pipeline;

  @override
  Widget build(BuildContext context) {
    final type = _messageTypeKey(data);
    if (_isPaymentInfoPayload(data)) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Align(
          alignment: Alignment.centerLeft,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.sizeOf(context).width * 0.94,
            ),
            child: InquiryPaymentInfoCard(
              orderNumber: _firestoreString(data['order_number']) ?? '',
              amountMmk: data['amount_mmk']?.toString() ?? '0',
              kpayId: _firestoreString(data['kpay_id']) ?? '',
              bankName: _firestoreString(data['bank_name']) ?? '',
              bankAccountNumber:
                  _firestoreString(data['bank_account_number']) ?? '',
              bankAccountName: _firestoreString(data['bank_account_name']) ?? '',
              paymentMethodLabel: _firestoreString(data['payment_method_label']),
              qrImageUrl: _firestoreString(data['qr_image_url']),
              orderTotalMmk: _firestoreString(data['order_total_mmk']),
              creditedDepositMmk: _firestoreString(data['credited_deposit_mmk']),
              balanceRemainingMmk:
                  _firestoreString(data['balance_remaining_mmk']),
            ),
          ),
        ),
      );
    }
    if (type == 'image') {
      final url = data['image_url'] as String? ?? '';
      final caption = data['caption'] as String? ?? '';
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Align(
          alignment: Alignment.centerLeft,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.sizeOf(context).width * 0.94,
            ),
            child: AppGlassCard(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (url.isNotEmpty)
                    ClipRRect(
                      borderRadius: BorderRadius.circular(AppRadii.sm),
                      child: CachedNetworkImage(
                        imageUrl: url,
                        width: 220,
                        fit: BoxFit.cover,
                        placeholder: (context, url) => const SizedBox(
                          width: 220,
                          height: 140,
                          child: Center(
                            child: CircularProgressIndicator(
                              color: AppColors.gold,
                              strokeWidth: 2,
                            ),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 6),
                  PremiumText.caption(
                    caption.isEmpty ? 'Workshop update' : caption,
                    color: AppColors.textSecondary,
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }
    if (type == 'quote_card') {
      final quoteId = (data['quote_id'] as num?)?.toInt();
      final quoteNumber = data['quote_number'] as String? ?? '';
      final totalMmk = data['total_mmk']?.toString() ?? '0';
      final rawLines = data['lines'] as List<dynamic>? ?? [];
      final lines = rawLines
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      final acted = quoteId != null && actedQuoteIds.contains(quoteId);
      final showActions = _quoteCardShowActions(
        quoteId: quoteId,
        pipeline: pipeline,
        acted: acted,
      );

      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Align(
          alignment: Alignment.centerLeft,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.sizeOf(context).width * 0.94,
            ),
            child: InquiryQuoteCard(
              quoteNumber: quoteNumber,
              totalMmk: totalMmk,
              lines: lines,
              actionsEnabled: showActions,
              busy: quoteBusyId == quoteId,
              onAccept: showActions && quoteId != null
                  ? () => onAcceptQuote(quoteId)
                  : null,
              onDecline: showActions && quoteId != null
                  ? () => onDeclineQuote(quoteId)
                  : null,
            ),
          ),
        ),
      );
    }

    final text = data['text'] as String? ?? '';
    final role = data['sender_role'] as String? ?? '';
    final isMe = role == 'customer';
    final ts = data['created_at'];
    DateTime? at;
    if (ts is Timestamp) {
      at = ts.toDate();
    }
    final label = role == 'admin' ? 'Concierge' : 'You';

    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: _TextBubble(
          isMe: isMe,
          text: text,
          caption:
              '$label${at != null ? ' · ${timeFmt.format(at.toLocal())}' : ''}',
        ),
      ),
    );
  }
}

class _TextBubble extends StatelessWidget {
  const _TextBubble({
    required this.isMe,
    required this.text,
    required this.caption,
  });

  final bool isMe;
  final String text;
  final String caption;

  @override
  Widget build(BuildContext context) {
    final child = ConstrainedBox(
      constraints: BoxConstraints(
        maxWidth: MediaQuery.sizeOf(context).width * 0.82,
      ),
      child: Column(
        crossAxisAlignment:
            isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          Text(
            text,
            style: Theme.of(context).textTheme.bodyLarge,
          ),
          const SizedBox(height: 6),
          PremiumText.caption(caption),
        ],
      ),
    );

    if (isMe) {
      return DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.md),
          gradient: LinearGradient(
            colors: [
              AppColors.gold.withValues(alpha: 0.22),
              AppColors.gold.withValues(alpha: 0.08),
            ],
          ),
          border: Border.all(color: AppColors.glassBorder),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
          child: child,
        ),
      );
    }

    return AppGlassCard(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
      child: child,
    );
  }
}
