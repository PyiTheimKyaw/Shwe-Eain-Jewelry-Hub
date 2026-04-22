import 'package:flutter/material.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/features/inquiries/data/inquiry_api.dart';
import 'package:jewelry_hub/features/inquiries/models/inquiry_pipeline.dart';
import 'package:jewelry_hub/features/inquiries/widgets/inquiry_quote_card.dart';
import 'package:provider/provider.dart';

/// Shows the latest quote from MySQL when Firestore has no [quote_card] message
/// (e.g. PHP cannot write to Firestore). Keeps UX aligned with the concierge flow.
class InquiryApiQuoteBanner extends StatefulWidget {
  const InquiryApiQuoteBanner({
    super.key,
    required this.quoteId,
    required this.pipeline,
    required this.onAcceptQuote,
    required this.onDeclineQuote,
    required this.quoteBusyId,
    required this.actedQuoteIds,
  });

  final int quoteId;
  final InquiryPipeline? pipeline;
  final Future<void> Function(int quoteId) onAcceptQuote;
  final Future<void> Function(int quoteId) onDeclineQuote;
  final int? quoteBusyId;
  final Set<int> actedQuoteIds;

  @override
  State<InquiryApiQuoteBanner> createState() => _InquiryApiQuoteBannerState();
}

class _InquiryApiQuoteBannerState extends State<InquiryApiQuoteBanner> {
  Map<String, dynamic>? _quote;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _load();
      }
    });
  }

  @override
  void didUpdateWidget(covariant InquiryApiQuoteBanner oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.quoteId != oldWidget.quoteId) {
      _load();
      return;
    }
    if (widget.pipeline?.orderId != oldWidget.pipeline?.orderId) {
      _load();
      return;
    }
    if (widget.pipeline?.activeQuoteId != oldWidget.pipeline?.activeQuoteId) {
      _load();
    }
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Not signed in';
        });
      }
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final q = await InquiryApi.fetchQuote(
        bearerToken: token,
        quoteId: widget.quoteId,
      );
      if (mounted) {
        setState(() {
          _quote = q;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = '$e';
          _loading = false;
        });
      }
    }
  }

  static List<Map<String, dynamic>> _linesFromQuote(Map<String, dynamic> quote) {
    final raw = quote['line_items'] as List<dynamic>? ?? [];
    return raw.map((e) {
      final m = Map<String, dynamic>.from(e as Map);
      final product = m['product'];
      String title = 'Item';
      if (m['custom_title'] != null && '${m['custom_title']}'.isNotEmpty) {
        title = '${m['custom_title']}';
      } else if (product is Map<String, dynamic> && product['title'] != null) {
        title = '${product['title']}';
      }
      return {
        'title': title,
        'metal_mmk': m['metal_value_mmk']?.toString() ?? '0',
        'labor_mmk': m['labor_fee_mmk']?.toString() ?? '0',
        'gems_mmk': m['gem_price_mmk']?.toString() ?? '0',
        'line_total_mmk': m['line_total_mmk']?.toString() ?? '0',
      };
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.fromLTRB(12, 0, 12, 12),
        child: Center(
          child: SizedBox(
            width: 28,
            height: 28,
            child: CircularProgressIndicator(
              color: Color(0xFFD4AF37),
              strokeWidth: 2,
            ),
          ),
        ),
      );
    }
    if (_error != null || _quote == null) {
      return const SizedBox.shrink();
    }

    final quote = _quote!;
    final status = quote['status'] as String? ?? '';
    final acted = widget.actedQuoteIds.contains(widget.quoteId);
    final activeId = widget.pipeline?.activeQuoteId;
    final showActions = status == 'sent' &&
        !acted &&
        activeId != null &&
        activeId == widget.quoteId;

    final quoteNumber = quote['quote_number'] as String? ?? '';
    final totalMmk = quote['total_mmk']?.toString() ?? '0';
    final lines = _linesFromQuote(quote);

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
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
            busy: widget.quoteBusyId == widget.quoteId,
            onAccept: showActions
                ? () => widget.onAcceptQuote(widget.quoteId)
                : null,
            onDecline: showActions
                ? () => widget.onDeclineQuote(widget.quoteId)
                : null,
          ),
        ),
      ),
    );
  }
}
