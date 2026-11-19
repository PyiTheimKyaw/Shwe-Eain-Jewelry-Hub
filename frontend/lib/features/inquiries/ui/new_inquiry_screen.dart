import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_gold_button.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/inquiries/data/inquiry_api.dart';
import 'package:jewelry_hub/features/inquiries/ui/inquiry_chat_screen.dart';
import 'package:provider/provider.dart';

class NewInquiryScreen extends StatefulWidget {
  const NewInquiryScreen({super.key, this.productId});

  final int? productId;

  @override
  State<NewInquiryScreen> createState() => _NewInquiryScreenState();
}

class _NewInquiryScreenState extends State<NewInquiryScreen> {
  final _message = TextEditingController();
  final List<XFile> _photos = [];
  bool _busy = false;

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _pickPhotos() async {
    final picker = ImagePicker();
    final list = await picker.pickMultiImage();
    if (list.isEmpty) {
      return;
    }
    setState(() {
      _photos
        ..clear()
        ..addAll(list.take(10));
    });
  }

  Future<void> _submit() async {
    final text = _message.text.trim();
    if (text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a message.')),
      );
      return;
    }
    final token = context.read<AuthProvider>().token;
    if (token == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      final created = await InquiryApi.create(
        token: token,
        message: text,
        productId: widget.productId,
        attachments: _photos,
      );
      if (!mounted) {
        return;
      }
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (context) => InquiryChatScreen(inquiry: created),
        ),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.backgroundMesh),
        child: SafeArea(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.arrow_back_ios_new_rounded,
                          color: AppColors.textPrimary),
                      onPressed: _busy ? null : () => Navigator.of(context).pop(),
                    ),
                    Expanded(
                      child: PremiumText.headline('New inquiry'),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: AppGlassCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (widget.productId != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: PremiumText.caption(
                              'Linked to catalog product #${widget.productId}',
                            ),
                          ),
                        TextFormField(
                          controller: _message,
                          minLines: 4,
                          maxLines: 10,
                          decoration: const InputDecoration(
                            labelText: 'Message',
                            alignLabelWithHint: true,
                          ),
                        ),
                        const SizedBox(height: 16),
                        OutlinedButton.icon(
                          onPressed: _busy ? null : _pickPhotos,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.gold,
                            side: const BorderSide(color: AppColors.glassBorder),
                          ),
                          icon: const Icon(Icons.photo_library_outlined),
                          label: Text(
                            _photos.isEmpty
                                ? 'Add photos (optional, max 10)'
                                : '${_photos.length} photo(s) selected',
                          ),
                        ),
                        const SizedBox(height: 24),
                        AnimatedSwitcher(
                          duration: const Duration(milliseconds: 200),
                          child: _busy
                              ? const Center(
                                  key: ValueKey('b'),
                                  child: Padding(
                                    padding: EdgeInsets.symmetric(vertical: 12),
                                    child: CircularProgressIndicator(
                                      color: AppColors.gold,
                                      strokeWidth: 2,
                                    ),
                                  ),
                                )
                              : AppGoldButton(
                                  key: const ValueKey('s'),
                                  label: 'Send inquiry',
                                  icon: Icons.send_rounded,
                                  onPressed: _submit,
                                ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
