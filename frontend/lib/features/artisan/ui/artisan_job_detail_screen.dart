import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_gold_button.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_text.dart';
import 'package:jewelry_hub/features/artisan/data/artisan_api.dart';
import 'package:provider/provider.dart';

class ArtisanJobDetailScreen extends StatefulWidget {
  const ArtisanJobDetailScreen({super.key, required this.assignmentId});

  final int assignmentId;

  @override
  State<ArtisanJobDetailScreen> createState() => _ArtisanJobDetailScreenState();
}

class _ArtisanJobDetailScreenState extends State<ArtisanJobDetailScreen> {
  static const List<String> _progressStages = <String>[
    'casting',
    'filing',
    'polishing',
    'setting',
    'qc',
    'other',
  ];

  Map<String, dynamic>? _assignment;
  bool _loading = true;
  String? _error;
  bool _busy = false;

  String _formatStageLabel(String stage) {
    switch (stage) {
      case 'qc':
        return 'QC';
      default:
        return stage.isEmpty
            ? 'Other'
            : '${stage[0].toUpperCase()}${stage.substring(1)}';
    }
  }

  Future<({String stage, String? note})?> _pickProgressMeta() async {
    var selectedStage = 'other';
    final noteCtrl = TextEditingController();

    final result = await showModalBottomSheet<({String stage, String? note})>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Padding(
              padding: EdgeInsets.fromLTRB(
                12,
                12,
                12,
                MediaQuery.of(context).viewInsets.bottom + 12,
              ),
              child: AppGlassCard(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    PremiumText.headline('Progress details', maxLines: 1),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      initialValue: selectedStage,
                      decoration: const InputDecoration(labelText: 'Stage'),
                      items: _progressStages
                          .map(
                            (stage) => DropdownMenuItem<String>(
                              value: stage,
                              child: Text(_formatStageLabel(stage)),
                            ),
                          )
                          .toList(growable: false),
                      onChanged: (v) {
                        if (v == null) return;
                        setSheetState(() => selectedStage = v);
                      },
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: noteCtrl,
                      maxLength: 160,
                      decoration: const InputDecoration(
                        labelText: 'Short note (optional)',
                        counterText: '',
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        TextButton(
                          onPressed: () => Navigator.of(context).pop(),
                          child: const Text('Cancel'),
                        ),
                        const SizedBox(width: 8),
                        AppGoldButton(
                          label: 'Continue',
                          icon: Icons.arrow_forward_rounded,
                          onPressed: () {
                            final note = noteCtrl.text.trim();
                            Navigator.of(context).pop((
                              stage: selectedStage,
                              note: note.isEmpty ? null : note,
                            ));
                          },
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
    noteCtrl.dispose();
    return result;
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
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
      final a = await ArtisanApi.fetchAssignment(token, widget.assignmentId);
      if (mounted) {
        setState(() {
          _assignment = a;
          _loading = false;
        });
      }
    } on ArtisanApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
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

  Future<void> _start() async {
    final token = context.read<AuthProvider>().token;
    final orderId =
        (_assignment?['order'] as Map<String, dynamic>?)?['id'] as int?;
    if (token == null || orderId == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      await ArtisanApi.startJob(bearerToken: token, orderId: orderId);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Production started.')));
      }
      await _load();
    } on ArtisanApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _progress() async {
    final token = context.read<AuthProvider>().token;
    final orderId =
        (_assignment?['order'] as Map<String, dynamic>?)?['id'] as int?;
    if (token == null || orderId == null) {
      return;
    }
    final progressMeta = await _pickProgressMeta();
    if (progressMeta == null) {
      return;
    }
    final files = await ImagePicker().pickMultiImage(imageQuality: 85);
    if (files.isEmpty) {
      return;
    }
    setState(() => _busy = true);
    try {
      await ArtisanApi.uploadProgress(
        bearerToken: token,
        orderId: orderId,
        stage: progressMeta.stage,
        note: progressMeta.note,
        photos: files,
      );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Progress uploaded.')));
      }
    } on ArtisanApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _ready() async {
    final token = context.read<AuthProvider>().token;
    final orderId =
        (_assignment?['order'] as Map<String, dynamic>?)?['id'] as int?;
    if (token == null || orderId == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      await ArtisanApi.readyForReview(bearerToken: token, orderId: orderId);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Marked ready for review.')),
        );
      }
      await _load();
    } on ArtisanApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = _assignment?['order'] as Map<String, dynamic>?;
    final status = _assignment?['status'] as String? ?? '';
    final orderNum = order?['order_number'] as String? ?? '';
    final orderStatus = order?['status'] as String? ?? '';

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
                      icon: const Icon(
                        Icons.arrow_back_ios_new_rounded,
                        color: AppColors.textPrimary,
                      ),
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                    Expanded(
                      child: PremiumText.headline('Job $orderNum', maxLines: 1),
                    ),
                  ],
                ),
              ),
              if (_loading)
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
              else
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                    children: [
                      AppGlassCard(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            PremiumText.caption(
                              'ASSIGNMENT',
                              color: AppColors.goldMuted,
                            ),
                            const SizedBox(height: 6),
                            PremiumText.body(status),
                            const SizedBox(height: 12),
                            PremiumText.caption(
                              'ORDER STATUS',
                              color: AppColors.goldMuted,
                            ),
                            const SizedBox(height: 6),
                            PremiumText.body(orderStatus),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (status == 'assigned' &&
                          orderStatus == 'deposit_confirmed')
                        AppGoldButton(
                          label: _busy ? 'Please wait…' : 'Start job',
                          icon: Icons.play_arrow_rounded,
                          onPressed: _busy ? null : _start,
                        ),
                      if (orderStatus == 'in_production') ...[
                        AppGoldButton(
                          label: _busy
                              ? 'Please wait…'
                              : 'Upload progress photos',
                          icon: Icons.photo_camera_outlined,
                          onPressed: _busy ? null : _progress,
                        ),
                        const SizedBox(height: 10),
                        OutlinedButton(
                          onPressed: _busy ? null : _ready,
                          child: Text(
                            _busy ? 'Please wait…' : 'Ready for review',
                          ),
                        ),
                      ],
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
