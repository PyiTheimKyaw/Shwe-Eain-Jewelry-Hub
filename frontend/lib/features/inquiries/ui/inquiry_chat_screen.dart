import 'dart:async';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart' as auth;
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/storage/public_storage_url.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_gold_button.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_image_viewer.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/inquiries/data/inquiry_api.dart';
import 'package:jewelry_hub/features/inquiries/models/inquiry_pipeline.dart';
import 'package:jewelry_hub/features/inquiries/models/inquiry_summary.dart';
import 'package:jewelry_hub/features/inquiries/widgets/concierge_pipeline_stepper.dart';
import 'package:jewelry_hub/features/inquiries/widgets/inquiry_api_quote_banner.dart';
import 'package:jewelry_hub/features/inquiries/widgets/inquiry_payment_info_card.dart';
import 'package:jewelry_hub/features/inquiries/widgets/inquiry_quote_card.dart';
import 'package:jewelry_hub/features/orders/models/payment_receipt.dart';
import 'package:jewelry_hub/features/orders/ui/receipt_detail_screen.dart';
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
  bool _sendingImage = false;
  bool _uploadingDeposit = false;
  InquiryPipeline? _restPipeline;
  final Set<int> _actedQuoteIds = {};
  int? _quoteActionBusyId;
  bool _didInitialScrollToBottom = false;
  Timer? _pipelineRefreshDebounce;
  Timer? _pipelineHeartbeat;
  bool _pipelineRequestInFlight = false;
  String? _threadPipelineSyncKey;
  DocumentSnapshot<Map<String, dynamic>>? _lastThreadSnapForSync;
  QuerySnapshot<Map<String, dynamic>>? _lastMessageSnapForSync;
  bool _messagesPipelinePrimed = false;
  int _lastIngestedMessageCount = 0;
  String? _lastSeenDepositState;
  String? _lastSeenRestDepositState;
  int _lastRenderedEntryCount = 0;
  final List<_LocalTimelineLog> _localTimelineLogs = [];

  /// Firestore stream instances must be stable across [setState] rebuilds. Recreating
  /// `.snapshots()` in [build] makes [StreamBuilder] cancel/resubscribe every rebuild
  /// (e.g. after every [_loadPipeline]), which drops real-time delivery and can flash
  /// the loading state over the message list.
  String? _firestoreBoundThreadId;
  Stream<DocumentSnapshot<Map<String, dynamic>>>? _threadDocStream;
  Stream<QuerySnapshot<Map<String, dynamic>>>? _messagesStream;

  void _bindFirestoreStreamsIfNeeded(String threadId) {
    if (_firestoreBoundThreadId == threadId &&
        _threadDocStream != null &&
        _messagesStream != null) {
      return;
    }
    _firestoreBoundThreadId = threadId;
    final ref = FirebaseFirestore.instance
        .collection('inquiry_threads')
        .doc(threadId);
    _threadDocStream = ref.snapshots();
    // Do not order at query-time: older/manual Firestore docs may miss `created_at`,
    // and Firestore would exclude them from ordered queries (history appears empty).
    _messagesStream = ref.collection('messages').snapshots();
  }

  /// When the thread document changes (Laravel / metadata), re-fetch merged pipeline from REST.
  void _schedulePipelineRefresh() {
    _pipelineRefreshDebounce?.cancel();
    _pipelineRefreshDebounce = Timer(const Duration(milliseconds: 200), () {
      if (mounted) {
        _loadPipeline();
      }
    });
  }

  /// Safety net for environments where server-side Firestore card writes are delayed/missing.
  /// Keeps quote/payment fallback cards and CTA visibility in near real-time from REST.
  void _startPipelineHeartbeat() {
    _pipelineHeartbeat?.cancel();
    _pipelineHeartbeat = Timer.periodic(const Duration(seconds: 3), (_) {
      if (!mounted || _connecting || _error != null) {
        return;
      }
      _loadPipeline();
    });
  }

  void _openReceiptFromChat(Map<String, dynamic> data) {
    final rawReceiptId = data['receipt_id'];
    final receiptId = rawReceiptId is num
        ? rawReceiptId.toInt()
        : int.tryParse('$rawReceiptId');
    final rawOrderId = data['order_id'];
    final orderId = rawOrderId is num
        ? rawOrderId.toInt()
        : int.tryParse('$rawOrderId');
    if (receiptId == null || orderId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Receipt information is unavailable.')),
      );
      return;
    }
    final rawAmount = data['amount_mmk'];
    final amountMmk = rawAmount is num
        ? rawAmount.toDouble()
        : double.tryParse('$rawAmount') ?? 0;
    final receipt = PaymentReceipt(
      id: receiptId,
      receiptNumber: (data['receipt_number'] ?? 'Receipt').toString(),
      orderId: orderId,
      amountMmk: amountMmk,
      currencyCode: 'MMK',
      paymentType: (data['payment_type'] ?? 'deposit').toString(),
      paymentMethodLabel: (data['payment_method_label'] ?? '').toString().trim().isEmpty
          ? null
          : data['payment_method_label'].toString(),
      issuedAt: null,
    );
    Navigator.of(context).push<void>(
      MaterialPageRoute(builder: (_) => ReceiptDetailScreen(receipt: receipt)),
    );
  }

  void _ingestThreadSnapshotForPipeline(
    DocumentSnapshot<Map<String, dynamic>> d,
  ) {
    if (identical(d, _lastThreadSnapForSync)) {
      return;
    }
    _lastThreadSnapForSync = d;
    final threadData = d.data();
    if (threadData == null) {
      return;
    }
    final oid = threadData['order_id'];
    final aq = threadData['active_quote_id'];
    final depositState = (threadData['deposit_state'] ?? '').toString();
    if (_lastSeenDepositState != null &&
        depositState != _lastSeenDepositState) {
      if (depositState == 'rejected') {
        _appendLocalTimelineLog('--- Payment Rejected ---');
      } else if (depositState == 'confirmed') {
        _appendLocalTimelineLog('--- Deposit Confirmed ---');
      }
    }
    _lastSeenDepositState = depositState;
    final key = <String>[
      (threadData['order_status'] ?? '').toString(),
      (threadData['pipeline_step'] ?? '').toString(),
      if (oid is num) oid.toString() else '',
      if (aq is num) aq.toString() else '',
      (threadData['active_quote_status'] ?? '').toString(),
      (threadData['deposit_state'] ?? '').toString(),
    ].join('|');
    if (key == _threadPipelineSyncKey) {
      return;
    }
    final hadKey = _threadPipelineSyncKey != null;
    _threadPipelineSyncKey = key;
    if (!hadKey) {
      return;
    }
    _schedulePipelineRefresh();
  }

  void _appendLocalTimelineLog(String text) {
    if (!mounted || text.trim().isEmpty) {
      return;
    }
    final now = DateTime.now();
    final recentlyExists = _localTimelineLogs.any(
      (e) => e.text == text && now.difference(e.at).inSeconds.abs() <= 5,
    );
    if (recentlyExists) {
      return;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      setState(() {
        _localTimelineLogs.add(_LocalTimelineLog(text: text, at: now));
      });
    });
  }

  void _ingestMessageSnapshotForPipeline(
    QuerySnapshot<Map<String, dynamic>> snap,
  ) {
    if (identical(snap, _lastMessageSnapForSync)) {
      return;
    }
    _lastMessageSnapForSync = snap;
    if (!_messagesPipelinePrimed) {
      _messagesPipelinePrimed = true;
      _lastIngestedMessageCount = snap.docs.length;
      return;
    }
    void scheduleRest() {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          _loadPipeline();
        }
      });
    }

    var didSchedule = false;
    for (final change in snap.docChanges) {
      if (change.type != DocumentChangeType.added &&
          change.type != DocumentChangeType.modified) {
        continue;
      }
      final data = change.doc.data();
      if (data == null) {
        continue;
      }
      if (_shouldRefetchRestPipelineFromMessage(data)) {
        scheduleRest();
        didSchedule = true;
        break;
      }
    }
    if (!didSchedule &&
        snap.docChanges.isEmpty &&
        snap.docs.length > _lastIngestedMessageCount) {
      for (var i = _lastIngestedMessageCount; i < snap.docs.length; i++) {
        final data = snap.docs[i].data();
        if (_shouldRefetchRestPipelineFromMessage(data)) {
          scheduleRest();
          break;
        }
      }
    }
    _lastIngestedMessageCount = snap.docs.length;
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
      _bindFirestoreStreamsIfNeeded(threadId);
      _startPipelineHeartbeat();
      if (mounted) {
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
      _pipelineHeartbeat?.cancel();
    } catch (e) {
      if (mounted) {
        setState(() {
          _connecting = false;
          _error = '$e';
        });
      }
      _pipelineHeartbeat?.cancel();
    }
  }

  Future<void> _loadPipeline() async {
    if (_pipelineRequestInFlight) {
      return;
    }
    final bearer = context.read<AuthProvider>().token;
    if (bearer == null) {
      return;
    }
    _pipelineRequestInFlight = true;
    try {
      final p = await InquiryApi.fetchPipeline(
        bearerToken: bearer,
        inquiryId: widget.inquiry.id,
      );
      final next =
          p ??
          InquiryPipeline(
            step: widget.inquiry.status == 'quoted' ? 'quoted' : 'consulting',
          );
      final ds = next.depositState ?? '';
      if (_lastSeenRestDepositState != null &&
          ds != _lastSeenRestDepositState) {
        if (ds == 'rejected') {
          _appendLocalTimelineLog('--- Payment Rejected ---');
        } else if (ds == 'confirmed') {
          _appendLocalTimelineLog('--- Deposit Confirmed ---');
        }
      }
      _lastSeenRestDepositState = ds;
      if (mounted) {
        setState(() {
          _restPipeline = next;
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
    } finally {
      _pipelineRequestInFlight = false;
    }
  }

  InquiryPipeline _effectivePipeline(InquiryPipeline? fromFirestore) {
    final rest =
        _restPipeline ??
        InquiryPipeline(
          step: widget.inquiry.status == 'quoted' ? 'quoted' : 'consulting',
        );
    if (fromFirestore == null) {
      return rest;
    }
    final mergedActiveQuoteId =
        rest.activeQuoteId ?? fromFirestore.activeQuoteId;
    String? mergedActiveQuoteStatus;
    if (mergedActiveQuoteId != null &&
        mergedActiveQuoteId == rest.activeQuoteId) {
      mergedActiveQuoteStatus = rest.activeQuoteStatus;
    } else if (mergedActiveQuoteId != null &&
        mergedActiveQuoteId == fromFirestore.activeQuoteId) {
      mergedActiveQuoteStatus = fromFirestore.activeQuoteStatus;
    } else {
      mergedActiveQuoteStatus =
          rest.activeQuoteStatus ?? fromFirestore.activeQuoteStatus;
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
          fromFirestore.depositInstructionsSentAt ??
          rest.depositInstructionsSentAt,
      depositPayment: rest.depositPayment,
      latestReceipt: rest.latestReceipt ?? fromFirestore.latestReceipt,
      timelineLogs: rest.timelineLogs,
    );
  }

  bool _canUploadDeposit(InquiryPipeline p) {
    if (p.orderId == null) {
      return false;
    }
    if (p.orderStatus != 'awaiting_deposit') {
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
    if (ds == 'confirmed') {
      return false;
    }
    if (ds == 'instructions_sent' || ds == 'rejected') {
      return true;
    }
    return p.depositInstructionsSentAt != null;
  }

  bool _canViewReceipt(InquiryPipeline p) {
    if (p.orderId == null) {
      return false;
    }
    if (p.latestReceipt == null) {
      return false;
    }
    if (p.orderStatus == 'deposit_confirmed') {
      return true;
    }
    final due = p.resolvedAdditionalDepositDueMmk();
    return due != null && due <= 0;
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
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Payment proof submitted.')));
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

  Future<void> _sendImage() async {
    if (_sendingImage) {
      return;
    }
    final threadId = widget.inquiry.firebaseThreadId;
    if (threadId == null) {
      return;
    }
    final bearer = context.read<AuthProvider>().token;
    final uid = context.read<AuthProvider>().user?.id;
    if (bearer == null) {
      return;
    }
    final picked = await ImagePicker().pickMultiImage(imageQuality: 85);
    if (picked.isEmpty) {
      return;
    }
    setState(() => _sendingImage = true);
    try {
      for (final file in picked) {
        final imageUrl = await InquiryApi.uploadChatImage(
          bearerToken: bearer,
          file: file,
          inquiryId: widget.inquiry.id,
          folder: 'inquiry-chat',
        );
        await FirebaseFirestore.instance
            .collection('inquiry_threads')
            .doc(threadId)
            .collection('messages')
            .add({
              'type': 'image',
              'image_url': imageUrl,
              'sender_role': 'customer',
              'created_at': FieldValue.serverTimestamp(),
              'laravel_user_id': uid,
              'text': 'Image message',
            });
      }
      _scrollToBottom(animated: true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Image send failed: $e')));
      }
    } finally {
      if (mounted) {
        setState(() => _sendingImage = false);
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
        const SnackBar(
          content: Text('Quote declined. We will follow up soon.'),
        ),
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
    _pipelineHeartbeat?.cancel();
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
              else if (_threadDocStream == null || _messagesStream == null)
                const Expanded(
                  child: Center(
                    child: CircularProgressIndicator(
                      color: AppColors.gold,
                      strokeWidth: 2,
                    ),
                  ),
                )
              else
                Expanded(
                  child: StreamBuilder<DocumentSnapshot<Map<String, dynamic>>>(
                    stream: _threadDocStream,
                    builder: (context, threadSnap) {
                      if (threadSnap.hasData) {
                        _ingestThreadSnapshotForPipeline(threadSnap.data!);
                      }
                      final threadData = threadSnap.data?.data();
                      final fromFs = InquiryPipeline.fromFirestore(threadData);
                      final effectivePipeline = _effectivePipeline(fromFs);

                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Expanded(
                            child:
                                StreamBuilder<
                                  QuerySnapshot<Map<String, dynamic>>
                                >(
                                  stream: _messagesStream,
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
                                    _ingestMessageSnapshotForPipeline(
                                      msgSnap.data!,
                                    );
                                    final docs =
                                        List<
                                            QueryDocumentSnapshot<
                                              Map<String, dynamic>
                                            >
                                          >.from(msgSnap.data!.docs)
                                          ..sort((a, b) {
                                            DateTime? toDate(dynamic v) {
                                              if (v is Timestamp) {
                                                return v.toDate();
                                              }
                                              return null;
                                            }

                                            final aAt = toDate(
                                              a.data()['created_at'],
                                            );
                                            final bAt = toDate(
                                              b.data()['created_at'],
                                            );
                                            if (aAt != null && bAt != null) {
                                              return aAt.compareTo(bAt);
                                            }
                                            if (aAt != null) {
                                              return -1;
                                            }
                                            if (bAt != null) {
                                              return 1;
                                            }
                                            return a.id.compareTo(b.id);
                                          });
                                    final fsTimelineEvents = docs
                                        .where(
                                          (d) =>
                                              _messageTypeKey(d.data()) ==
                                              'timeline_log',
                                        )
                                        .map((d) {
                                          final m = d.data();
                                          final text =
                                              _firestoreString(m['text']) ?? '';
                                          final ts = m['created_at'];
                                          DateTime? at;
                                          if (ts is Timestamp) {
                                            at = ts.toDate();
                                          }
                                          return (text: text, at: at);
                                        })
                                        .where((e) => e.text.isNotEmpty)
                                        .toList(growable: false);
                                    final fallbackTimelineLogs =
                                        _localTimelineLogs
                                            .where((local) {
                                              for (final remote
                                                  in fsTimelineEvents) {
                                                if (remote.text != local.text) {
                                                  continue;
                                                }
                                                if (remote.at == null) {
                                                  continue;
                                                }
                                                final diff = remote.at!
                                                    .difference(local.at)
                                                    .inSeconds
                                                    .abs();
                                                if (diff <= 12) {
                                                  return false;
                                                }
                                              }
                                              return true;
                                            })
                                            .toList(growable: false);
                                    final persistedTimelineLogs =
                                        <_LocalTimelineLog>[];
                                    final rawPersisted =
                                        effectivePipeline.timelineLogs ??
                                        const [];
                                    for (final t in rawPersisted) {
                                      final text =
                                          _firestoreString(
                                            t['message'],
                                          )?.trim() ??
                                          '';
                                      if (text.isEmpty) {
                                        continue;
                                      }
                                      final rawAt = _firestoreString(
                                        t['occurred_at'],
                                      );
                                      final parsedAt = rawAt != null
                                          ? DateTime.tryParse(rawAt)?.toLocal()
                                          : null;
                                      final at = parsedAt ?? DateTime.now();
                                      var hasNearbyFs = false;
                                      for (final remote in fsTimelineEvents) {
                                        if (remote.text != text ||
                                            remote.at == null) {
                                          continue;
                                        }
                                        final diff = remote.at!
                                            .toLocal()
                                            .difference(at)
                                            .inSeconds
                                            .abs();
                                        if (diff <= 45) {
                                          hasNearbyFs = true;
                                          break;
                                        }
                                      }
                                      if (hasNearbyFs) {
                                        continue;
                                      }
                                      persistedTimelineLogs.add(
                                        _LocalTimelineLog(text: text, at: at),
                                      );
                                    }
                                    final fallbackTimelineLogsFiltered =
                                        fallbackTimelineLogs
                                            .where((local) {
                                              for (final persisted
                                                  in persistedTimelineLogs) {
                                                if (persisted.text !=
                                                    local.text) {
                                                  continue;
                                                }
                                                final diff = persisted.at
                                                    .difference(local.at)
                                                    .inSeconds
                                                    .abs();
                                                if (diff <= 60) {
                                                  return false;
                                                }
                                              }
                                              return true;
                                            })
                                            .toList(growable: false);
                                    final activeQuoteId =
                                        effectivePipeline.activeQuoteId;
                                    final hasFsQuoteCard =
                                        activeQuoteId != null &&
                                        docs.any((doc) {
                                          final d = doc.data();
                                          return d['type'] == 'quote_card' &&
                                              (d['quote_id'] as num?)
                                                      ?.toInt() ==
                                                  activeQuoteId;
                                        });
                                    final showApiQuoteBanner =
                                        activeQuoteId != null &&
                                        !hasFsQuoteCard;
                                    final showApiDepositPayment =
                                        _showApiDepositPaymentCard(
                                          effectivePipeline,
                                          docs,
                                        );
                                    final fsPaymentProofUrls = docs
                                        .where((doc) {
                                          final d = doc.data();
                                          return _messageTypeKey(d) == 'image' &&
                                              _firestoreString(
                                                    d['image_category'],
                                                  ) ==
                                                  'payment_proof';
                                        })
                                        .map(
                                          (doc) =>
                                              _firestoreString(
                                                doc.data()['image_url'],
                                              ) ??
                                              '',
                                        )
                                        .where((url) => url.isNotEmpty)
                                        .toSet();
                                    final apiProofHistoryRaw =
                                        effectivePipeline.paymentProofHistory ??
                                        effectivePipeline
                                            .depositPayment?['payment_proof_history'];
                                    final apiProofHistory =
                                        <Map<String, dynamic>>[];
                                    if (apiProofHistoryRaw is List) {
                                      for (final entry in apiProofHistoryRaw) {
                                        if (entry is Map) {
                                          apiProofHistory.add(
                                            Map<String, dynamic>.from(entry),
                                          );
                                        }
                                      }
                                    } else {
                                      final singleUrl =
                                          _firestoreString(
                                            effectivePipeline.depositPayment?['payment_proof_image_url'],
                                          ) ??
                                          '';
                                      if (singleUrl.isNotEmpty) {
                                        apiProofHistory.add({
                                          'image_url': singleUrl,
                                          'submitted_at': _firestoreString(
                                            effectivePipeline.depositPayment?['payment_proof_submitted_at'],
                                          ),
                                        });
                                      }
                                    }

                                    final orderedEntries =
                                        <Map<String, dynamic>>[];
                                    for (final doc in docs) {
                                      orderedEntries.add(
                                        Map<String, dynamic>.from(doc.data()),
                                      );
                                    }
                                    for (final e in persistedTimelineLogs) {
                                      orderedEntries.add({
                                        'type': 'timeline_log',
                                        'sender_role': 'system',
                                        'text': e.text,
                                        'created_at': Timestamp.fromDate(e.at),
                                      });
                                    }
                                    for (final e
                                        in fallbackTimelineLogsFiltered) {
                                      orderedEntries.add({
                                        'type': 'timeline_log',
                                        'sender_role': 'system',
                                        'text': e.text,
                                        'created_at': Timestamp.fromDate(e.at),
                                      });
                                    }
                                    for (final proof in apiProofHistory) {
                                      final url =
                                          _firestoreString(proof['image_url']) ??
                                          '';
                                      if (url.isEmpty ||
                                          fsPaymentProofUrls.contains(url)) {
                                        continue;
                                      }
                                      final submittedAtRaw = _firestoreString(
                                        proof['submitted_at'],
                                      );
                                      final submittedAt = submittedAtRaw != null
                                          ? DateTime.tryParse(
                                              submittedAtRaw,
                                            )?.toLocal()
                                          : null;
                                      orderedEntries.add({
                                        'type': 'image',
                                        'sender_role': 'customer',
                                        'image_url': url,
                                        'caption': 'Payment proof uploaded',
                                        'image_category': 'payment_proof',
                                        'text': 'Payment proof uploaded',
                                        'created_at': submittedAt != null
                                            ? Timestamp.fromDate(
                                                submittedAt,
                                              )
                                            : null,
                                      });
                                    }
                                    DateTime? toDate(dynamic v) {
                                      if (v is Timestamp) {
                                        return v.toDate();
                                      }
                                      return null;
                                    }

                                    orderedEntries.sort((a, b) {
                                      final aAt = toDate(a['created_at']);
                                      final bAt = toDate(b['created_at']);
                                      if (aAt != null && bAt != null) {
                                        return aAt.compareTo(bAt);
                                      }
                                      if (aAt != null) {
                                        return -1;
                                      }
                                      if (bAt != null) {
                                        return 1;
                                      }
                                      return 0;
                                    });

                                    if (!_didInitialScrollToBottom &&
                                        orderedEntries.isNotEmpty) {
                                      _didInitialScrollToBottom = true;
                                      WidgetsBinding.instance
                                          .addPostFrameCallback((_) {
                                            if (!mounted) {
                                              return;
                                            }
                                            _scrollToBottom();
                                          });
                                    }
                                    if (_didInitialScrollToBottom &&
                                        orderedEntries.length >
                                            _lastRenderedEntryCount) {
                                      WidgetsBinding.instance
                                          .addPostFrameCallback((_) {
                                            if (!mounted) {
                                              return;
                                            }
                                            _scrollToBottom(animated: true);
                                          });
                                    }
                                    _lastRenderedEntryCount =
                                        orderedEntries.length;

                                    return CustomScrollView(
                                      controller: _scrollController,
                                      keyboardDismissBehavior:
                                          ScrollViewKeyboardDismissBehavior
                                              .onDrag,
                                      slivers: [
                                        if (widget
                                            .inquiry
                                            .attachmentStoragePaths
                                            .isNotEmpty)
                                          SliverToBoxAdapter(
                                            child: Padding(
                                              padding:
                                                  const EdgeInsets.fromLTRB(
                                                    12,
                                                    0,
                                                    12,
                                                    8,
                                                  ),
                                              child: AppGlassCard(
                                                child: Column(
                                                  crossAxisAlignment:
                                                      CrossAxisAlignment.start,
                                                  children: [
                                                    PremiumText.caption(
                                                      'INITIAL INQUIRY PHOTOS',
                                                      color: AppColors.goldMuted,
                                                    ),
                                                    const SizedBox(height: 8),
                                                    Wrap(
                                                      spacing: 8,
                                                      runSpacing: 8,
                                                      children: widget
                                                          .inquiry
                                                          .attachmentStoragePaths
                                                          .map(
                                                            (path) => ClipRRect(
                                                              borderRadius:
                                                                  BorderRadius.circular(
                                                                    AppRadii.sm,
                                                                  ),
                                                              child: CachedNetworkImage(
                                                                imageUrl:
                                                                    publicStorageUrl(
                                                                      path,
                                                                    ),
                                                                width: 88,
                                                                height: 88,
                                                                fit: BoxFit.cover,
                                                              ),
                                                            ),
                                                          )
                                                          .toList(
                                                            growable: false,
                                                          ),
                                                    ),
                                                  ],
                                                ),
                                              ),
                                            ),
                                          ),
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
                                              padding:
                                                  const EdgeInsets.fromLTRB(
                                                    12,
                                                    0,
                                                    12,
                                                    8,
                                                  ),
                                              child:
                                                  _apiDepositPaymentFromPipeline(
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
                                                SliverChildBuilderDelegate((
                                                  context,
                                                  index,
                                                ) {
                                                  final d =
                                                      orderedEntries[index];
                                                  return _MessageTile(
                                                    data: d,
                                                    timeFmt: timeFmt,
                                                    onAcceptQuote: _acceptQuote,
                                                    onDeclineQuote:
                                                        _declineQuote,
                                                    onViewReceipt:
                                                        _openReceiptFromChat,
                                                    quoteBusyId:
                                                        _quoteActionBusyId,
                                                    actedQuoteIds:
                                                        _actedQuoteIds,
                                                    pipeline: effectivePipeline,
                                                  );
                                                },
                                                  childCount:
                                                      orderedEntries.length,
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
                                    : () => _pickAndUploadDeposit(
                                        effectivePipeline,
                                      ),
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
                          if (!_canUploadDeposit(effectivePipeline) &&
                              _canViewReceipt(effectivePipeline))
                            Padding(
                              padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                              child: OutlinedButton.icon(
                                onPressed: () {
                                  final receipt =
                                      effectivePipeline.latestReceipt;
                                  final receiptIdRaw = receipt?['id'];
                                  final receiptId = receiptIdRaw is num
                                      ? receiptIdRaw.toInt()
                                      : int.tryParse('$receiptIdRaw');
                                  final orderId = effectivePipeline.orderId;
                                  if (receiptId == null || orderId == null) {
                                    return;
                                  }
                                  final amountRaw = receipt?['amount_mmk'];
                                  final amount = amountRaw is num
                                      ? amountRaw.toDouble()
                                      : double.tryParse('$amountRaw') ?? 0;
                                  final receiptModel = PaymentReceipt(
                                    id: receiptId,
                                    receiptNumber:
                                        (receipt?['receipt_number'] ??
                                                'Receipt')
                                            .toString(),
                                    orderId: orderId,
                                    amountMmk: amount,
                                    currencyCode: 'MMK',
                                    paymentType:
                                        (receipt?['payment_type'] ?? 'deposit')
                                            .toString(),
                                    paymentMethodLabel: (receipt?['payment_method_label'] ?? '')
                                            .toString()
                                            .trim()
                                            .isEmpty
                                        ? null
                                        : receipt?['payment_method_label']
                                            .toString(),
                                    issuedAt: DateTime.tryParse(
                                      (receipt?['issued_at'] ?? '').toString(),
                                    ),
                                  );
                                  Navigator.of(context).push<void>(
                                    MaterialPageRoute(
                                      builder: (_) => ReceiptDetailScreen(
                                        receipt: receiptModel,
                                      ),
                                    ),
                                  );
                                },
                                icon: const Icon(Icons.receipt_long_rounded),
                                label: const Text('View Receipt'),
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
                                IconButton(
                                  onPressed: _sendingImage ? null : _sendImage,
                                  icon: _sendingImage
                                      ? const SizedBox(
                                          width: 20,
                                          height: 20,
                                          child: CircularProgressIndicator(
                                            color: AppColors.gold,
                                            strokeWidth: 2,
                                          ),
                                        )
                                      : const Icon(
                                          Icons.photo_library_outlined,
                                          color: AppColors.gold,
                                        ),
                                  tooltip: 'Send image',
                                ),
                                const SizedBox(width: 4),
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

String? _cleanImageCaption(String? rawCaption) {
  final caption = rawCaption?.trim();
  if (caption == null || caption.isEmpty) {
    return null;
  }
  final normalized = caption.toLowerCase();
  if (normalized == 'customer image' ||
      normalized == 'admin image' ||
      normalized == 'image message') {
    return null;
  }
  return caption;
}

/// Card / payment messages carry display data in Firestore; canonical state is on the REST pipeline.
bool _shouldRefetchRestPipelineFromMessage(Map<String, dynamic> data) {
  final t = _messageTypeKey(data);
  if (t == 'quote_card' || t == 'payment_info_card' || t == 'receipt_card') {
    return true;
  }
  return _isPaymentInfoPayload(data);
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
    required this.onViewReceipt,
    required this.quoteBusyId,
    required this.actedQuoteIds,
    required this.pipeline,
  });

  final Map<String, dynamic> data;
  final DateFormat timeFmt;
  final Future<void> Function(int) onAcceptQuote;
  final Future<void> Function(int) onDeclineQuote;
  final void Function(Map<String, dynamic>) onViewReceipt;
  final int? quoteBusyId;
  final Set<int> actedQuoteIds;
  final InquiryPipeline? pipeline;

  @override
  Widget build(BuildContext context) {
    final type = _messageTypeKey(data);
    if (type == 'timeline_log') {
      final text = _firestoreString(data['text']) ?? '';
      if (text.isEmpty) {
        return const SizedBox.shrink();
      }
      final ts = data['created_at'];
      DateTime? at;
      if (ts is Timestamp) {
        at = ts.toDate();
      }
      final timeLabel = at != null ? ' · ${timeFmt.format(at.toLocal())}' : '';
      return Padding(
        padding: const EdgeInsets.fromLTRB(8, 4, 8, 12),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              PremiumText.caption(
                text,
                color: AppColors.textSecondary,
                textAlign: TextAlign.center,
              ),
              PremiumText.caption(
                'Workflow update$timeLabel',
                color: AppColors.textSecondary.withValues(alpha: 0.8),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      );
    }
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
              bankAccountName:
                  _firestoreString(data['bank_account_name']) ?? '',
              paymentMethodLabel: _firestoreString(
                data['payment_method_label'],
              ),
              qrImageUrl: _firestoreString(data['qr_image_url']),
              orderTotalMmk: _firestoreString(data['order_total_mmk']),
              creditedDepositMmk: _firestoreString(
                data['credited_deposit_mmk'],
              ),
              balanceRemainingMmk: _firestoreString(
                data['balance_remaining_mmk'],
              ),
            ),
          ),
        ),
      );
    }
    if (type == 'image') {
      final url = data['image_url'] as String? ?? '';
      final role = _firestoreString(data['sender_role']) ?? '';
      final isMe = role == 'customer';
      final caption = _cleanImageCaption(_firestoreString(data['caption']));
      final isPaymentProof =
          _firestoreString(data['image_category']) == 'payment_proof';
      final stageRaw = _firestoreString(data['stage'])?.trim() ?? '';
      final noteRaw = _firestoreString(data['note'])?.trim() ?? '';
      String stageLabel = '';
      if (stageRaw.isNotEmpty) {
        if (stageRaw == 'qc') {
          stageLabel = 'QC';
        } else {
          stageLabel = '${stageRaw[0].toUpperCase()}${stageRaw.substring(1)}';
        }
      }
      final composedCaption = [
        if (stageLabel.isNotEmpty) 'Workshop · $stageLabel',
        if (noteRaw.isNotEmpty) noteRaw,
      ].join(' · ');
      final displayCaption =
          composedCaption.isNotEmpty ? composedCaption : caption;
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Align(
          alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.sizeOf(context).width * 0.82,
            ),
            child: DecoratedBox(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(AppRadii.md),
                gradient: isMe
                    ? LinearGradient(
                        colors: [
                          AppColors.gold.withValues(alpha: 0.22),
                          AppColors.gold.withValues(alpha: 0.08),
                        ],
                      )
                    : null,
                border: Border.all(color: AppColors.glassBorder),
                color: isMe ? null : AppColors.surface.withValues(alpha: 0.6),
              ),
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: isMe
                      ? CrossAxisAlignment.end
                      : CrossAxisAlignment.start,
                  children: [
                    if (isPaymentProof)
                      PremiumText.caption(
                        'Payment Proof Uploaded',
                        color: isMe ? AppColors.goldMuted : AppColors.textSecondary,
                      ),
                    if (isPaymentProof) const SizedBox(height: 6),
                    if (url.isNotEmpty)
                      GestureDetector(
                        onTap: () => PremiumImageViewer.open(
                          context: context,
                          imageUrls: [url],
                          initialIndex: 0,
                        ),
                        child: ClipRRect(
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
                            errorWidget: (context, url, error) => Container(
                              width: 220,
                              height: 140,
                              color: AppColors.surface.withValues(alpha: 0.6),
                              alignment: Alignment.center,
                              child: PremiumText.caption(
                                'Image unavailable',
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ),
                        ),
                      ),
                    if (displayCaption != null) ...[
                      const SizedBox(height: 6),
                      PremiumText.caption(
                        displayCaption,
                        color: AppColors.textSecondary,
                      ),
                    ],
                  ],
                ),
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
    if (type == 'receipt_card') {
      final receiptNumber =
          _firestoreString(data['receipt_number']) ?? 'Receipt';
      final amountMmk = _firestoreString(data['amount_mmk']) ?? '0';
      final ctaLabel = _firestoreString(data['cta_label']) ?? 'View Receipt';

      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Align(
          alignment: Alignment.centerLeft,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.sizeOf(context).width * 0.94,
            ),
            child: AppGlassCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  PremiumText.headline('Payment receipt issued', maxLines: 1),
                  const SizedBox(height: 6),
                  PremiumText.body(receiptNumber),
                  const SizedBox(height: 4),
                  PremiumText.caption(
                    '$amountMmk MMK',
                    color: AppColors.textSecondary,
                  ),
                  const SizedBox(height: 12),
                  AppGoldButton(
                    label: ctaLabel,
                    icon: Icons.receipt_long_rounded,
                    onPressed: () => onViewReceipt(data),
                  ),
                ],
              ),
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

class _LocalTimelineLog {
  const _LocalTimelineLog({required this.text, required this.at});

  final String text;
  final DateTime at;
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
        crossAxisAlignment: isMe
            ? CrossAxisAlignment.end
            : CrossAxisAlignment.start,
        children: [
          Text(text, style: Theme.of(context).textTheme.bodyLarge),
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
