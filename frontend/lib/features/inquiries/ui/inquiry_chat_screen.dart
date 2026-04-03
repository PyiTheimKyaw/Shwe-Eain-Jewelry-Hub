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
import 'package:jewelry_hub/features/inquiries/models/inquiry_summary.dart';
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
  bool _connecting = true;
  String? _error;
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _connect());
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
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Send failed: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  @override
  void dispose() {
    _composer.dispose();
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
                      icon: const Icon(Icons.arrow_back_ios_new_rounded,
                          color: AppColors.textPrimary),
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          PremiumText.headline('Chat', maxLines: 1),
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
                const Expanded(
                  child: Center(child: Text('No thread id')),
                )
              else
                Expanded(
                  child: StreamBuilder<QuerySnapshot<Map<String, dynamic>>>(
                    stream: FirebaseFirestore.instance
                        .collection('inquiry_threads')
                        .doc(threadId)
                        .collection('messages')
                        .orderBy('created_at', descending: false)
                        .snapshots(),
                    builder: (context, snap) {
                      if (snap.hasError) {
                        return Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Text('${snap.error}'),
                          ),
                        );
                      }
                      if (!snap.hasData) {
                        return const Center(
                          child: CircularProgressIndicator(
                            color: AppColors.gold,
                            strokeWidth: 2,
                          ),
                        );
                      }
                      final docs = snap.data!.docs;
                      return ListView.builder(
                        padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                        itemCount: docs.length,
                        itemBuilder: (context, i) {
                          final d = docs[i].data();
                          final text = d['text'] as String? ?? '';
                          final role = d['sender_role'] as String? ?? '';
                          final isMe = role == 'customer';
                          final ts = d['created_at'];
                          DateTime? at;
                          if (ts is Timestamp) {
                            at = ts.toDate();
                          }
                          return Align(
                            alignment: isMe
                                ? Alignment.centerRight
                                : Alignment.centerLeft,
                            child: Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: AppGlassCard(
                                padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                                child: ConstrainedBox(
                                  constraints: BoxConstraints(
                                    maxWidth:
                                        MediaQuery.sizeOf(context).width * 0.82,
                                  ),
                                  child: Column(
                                    crossAxisAlignment: isMe
                                        ? CrossAxisAlignment.end
                                        : CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        text,
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodyLarge,
                                      ),
                                      const SizedBox(height: 6),
                                      PremiumText.caption(
                                        '${role == 'admin' ? 'Shop' : 'You'}'
                                        '${at != null ? ' · ${timeFmt.format(at.toLocal())}' : ''}',
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      );
                    },
                  ),
                ),
              if (!_connecting && _error == null && threadId != null)
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
          ),
        ),
      ),
    );
  }
}
