import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_gold_button.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/inquiries/data/inquiry_api.dart';
import 'package:jewelry_hub/features/inquiries/models/inquiry_summary.dart';
import 'package:jewelry_hub/features/inquiries/ui/inquiry_chat_screen.dart';
import 'package:jewelry_hub/features/inquiries/ui/new_inquiry_screen.dart';
import 'package:jewelry_hub/features/inquiries/widgets/inquiry_status_badge.dart';
import 'package:provider/provider.dart';

class InquiryListScreen extends StatefulWidget {
  const InquiryListScreen({super.key});

  @override
  State<InquiryListScreen> createState() => _InquiryListScreenState();
}

class _InquiryListScreenState extends State<InquiryListScreen> {
  List<InquirySummary>? _items;
  Object? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) {
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await InquiryApi.fetchMine(token);
      if (mounted) {
        setState(() {
          _items = list;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final dateFmt = DateFormat.MMMd();

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
                child: Row(
                  children: [
                    Expanded(child: PremiumText.headline('Inquiries')),
                    IconButton(
                      tooltip: 'Log out',
                      icon: const Icon(
                        Icons.logout_rounded,
                        color: AppColors.textSecondary,
                      ),
                      onPressed: () => auth.logout(),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: RefreshIndicator(
                  color: AppColors.gold,
                  onRefresh: _load,
                  child: _buildBody(context, dateFmt),
                ),
              ),
            ],
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          await Navigator.of(context).push<void>(
            MaterialPageRoute<void>(
              builder: (context) => const NewInquiryScreen(),
            ),
          );
          if (mounted) {
            await _load();
          }
        },
        backgroundColor: AppColors.gold,
        foregroundColor: AppColors.background,
        icon: const Icon(Icons.add_comment_rounded),
        label: const Text('New inquiry'),
      ),
    );
  }

  Widget _buildBody(BuildContext context, DateFormat dateFmt) {
    if (_error != null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              'Could not load inquiries.\n$_error',
              style: Theme.of(context).textTheme.bodyLarge,
            ),
          ),
        ],
      );
    }
    if (_loading) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.gold, strokeWidth: 2),
      );
    }
    final items = _items!;
    if (items.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          AppGlassCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                PremiumText.body(
                  'No inquiries yet. Start one to discuss a piece or a custom design.',
                  color: AppColors.textSecondary,
                ),
                const SizedBox(height: 20),
                AppGoldButton(
                  label: 'Create inquiry',
                  icon: Icons.edit_outlined,
                  onPressed: () async {
                    await Navigator.of(context).push<void>(
                      MaterialPageRoute<void>(
                        builder: (context) => const NewInquiryScreen(),
                      ),
                    );
                    if (mounted) {
                      await _load();
                    }
                  },
                ),
              ],
            ),
          ),
        ],
      );
    }

    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 88),
      itemCount: items.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final q = items[index];
        final preview = q.message.length > 120
            ? '${q.message.substring(0, 117)}…'
            : q.message;
        return AppGlassCard(
          onTap: q.firebaseThreadId == null
              ? null
              : () {
                  Navigator.of(context).push<void>(
                    MaterialPageRoute<void>(
                      builder: (context) => InquiryChatScreen(inquiry: q),
                    ),
                  );
                },
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      preview,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodyLarge,
                    ),
                  ),
                  const SizedBox(width: 8),
                  InquiryStatusBadge(status: q.status),
                ],
              ),
              if (q.productTitle != null) ...[
                const SizedBox(height: 8),
                PremiumText.caption('Re: ${q.productTitle}'),
              ],
              const SizedBox(height: 10),
              PremiumText.caption(
                dateFmt.format(q.createdAt.toLocal()),
                textAlign: TextAlign.right,
              ),
            ],
          ),
        );
      },
    );
  }
}
