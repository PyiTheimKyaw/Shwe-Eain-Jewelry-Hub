import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:jewelry_hub/core/providers/auth_provider.dart';
import 'package:jewelry_hub/core/storage/public_storage_url.dart';
import 'package:jewelry_hub/core/ui/design_system.dart';
import 'package:jewelry_hub/core/ui/widgets/app_glass_card.dart';
import 'package:jewelry_hub/core/ui/widgets/app_gold_button.dart';
import 'package:jewelry_hub/core/ui/widgets/premium_image_viewer.dart';
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
  final DateFormat _deadlineFmt = DateFormat('MMM d, yyyy • h:mm a');

  List<Map<String, dynamic>> _visibleItems() {
    final order = _assignment?['order'] as Map<String, dynamic>?;
    final allItemsRaw = order?['items'];
    final allItems = (allItemsRaw is List ? allItemsRaw : const <dynamic>[])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList(growable: false);
    final scope = (_assignment?['task_scope'] ?? '').toString();
    if (scope != 'line_item') {
      return allItems;
    }
    final scoped = _assignment?['order_item'];
    if (scoped is Map) {
      return [Map<String, dynamic>.from(scoped)];
    }
    return allItems;
  }

  List<String> _itemImageUrls(Map<String, dynamic> item) {
    final product = item['product'];
    if (product is! Map) {
      return const [];
    }
    final imagesRaw = product['images'];
    final images = (imagesRaw is List ? imagesRaw : const <dynamic>[])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList(growable: false);
    return images
        .map((img) => (img['storage_path'] ?? '').toString().trim())
        .where((path) => path.isNotEmpty)
        .map(publicStorageUrl)
        .toList(growable: false);
  }

  List<String> _inquiryAttachmentUrls() {
    final order = _assignment?['order'];
    if (order is! Map) {
      return const [];
    }
    final quote = order['quote'];
    if (quote is! Map) {
      return const [];
    }
    final inquiry = quote['inquiry'];
    if (inquiry is! Map) {
      return const [];
    }
    final raw = inquiry['attachments'];
    final attachments = (raw is List ? raw : const <dynamic>[])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList(growable: false);
    return attachments
        .map((a) => (a['url'] ?? a['storage_path'] ?? '').toString().trim())
        .where((path) => path.isNotEmpty)
        .map(publicStorageUrl)
        .toList(growable: false);
  }

  List<String> _selectedReferenceUrls() {
    final raw = _assignment?['reference_images'];
    final refs = (raw is List ? raw : const <dynamic>[])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList(growable: false);
    final urls = <String>[];
    for (final ref in refs) {
      final candidate = ref['candidate'];
      if (candidate is Map) {
        final c = Map<String, dynamic>.from(candidate);
        final directUrl = (c['url'] ?? '').toString().trim();
        final storage = (c['storage_path'] ?? '').toString().trim();
        if (directUrl.isNotEmpty) {
          urls.add(directUrl);
          continue;
        }
        if (storage.isNotEmpty) {
          urls.add(publicStorageUrl(storage));
          continue;
        }
      }
      final directUrl = (ref['url'] ?? '').toString().trim();
      final storage = (ref['storage_path'] ?? '').toString().trim();
      if (directUrl.isNotEmpty) {
        urls.add(directUrl);
      } else if (storage.isNotEmpty) {
        urls.add(publicStorageUrl(storage));
      }
    }
    return urls.toSet().toList(growable: false);
  }

  Widget _buildZoomableThumb({
    required List<String> imageUrls,
    required int index,
    required String heroPrefix,
  }) {
    final url = imageUrls[index];
    return GestureDetector(
      onTap: () => PremiumImageViewer.open(
        context: context,
        imageUrls: imageUrls,
        initialIndex: index,
        heroPrefix: heroPrefix,
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadii.sm),
        child: Hero(
          tag: '$heroPrefix-$index',
          child: CachedNetworkImage(
            imageUrl: url,
            width: 88,
            height: 88,
            fit: BoxFit.cover,
          ),
        ),
      ),
    );
  }

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

  String _formatDeadline(String? iso) {
    if (iso == null || iso.trim().isEmpty) {
      return '-';
    }
    final dt = DateTime.tryParse(iso);
    if (dt == null) {
      return '-';
    }
    return _deadlineFmt.format(dt.toLocal());
  }

  String _priorityLabel(String value) {
    switch (value) {
      case 'low':
        return 'Low';
      case 'high':
        return 'High';
      case 'urgent':
        return 'Urgent';
      default:
        return 'Normal';
    }
  }

  Color _priorityColor(String value) {
    switch (value) {
      case 'low':
        return Colors.lightBlueAccent;
      case 'high':
        return Colors.orangeAccent;
      case 'urgent':
        return Colors.redAccent;
      default:
        return AppColors.gold;
    }
  }

  Future<({String stage, String? note})?> _pickProgressMeta() async {
    return showModalBottomSheet<({String stage, String? note})>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _ProgressMetaSheet(
        stages: _progressStages,
        formatStageLabel: _formatStageLabel,
      ),
    );
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
    List<XFile> files;
    try {
      files = await ImagePicker().pickMultiImage(imageQuality: 85);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not open photos: $e')),
        );
      }
      return;
    }
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
    final targetDeadlineAt =
        (_assignment?['target_deadline_at'] as String?)?.trim();
    final priorityLevel =
        ((_assignment?['priority_level'] as String?) ?? 'normal').trim();
    final lastAdminFeedback =
        (_assignment?['latest_admin_feedback'] as String?)?.trim();

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
                            const SizedBox(height: 12),
                            PremiumText.caption(
                              'TARGET DEADLINE',
                              color: AppColors.goldMuted,
                            ),
                            const SizedBox(height: 6),
                            PremiumText.body(
                              _formatDeadline(targetDeadlineAt),
                              color: AppColors.textSecondary,
                            ),
                            const SizedBox(height: 12),
                            PremiumText.caption(
                              'PRIORITY',
                              color: AppColors.goldMuted,
                            ),
                            const SizedBox(height: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: _priorityColor(
                                  priorityLevel,
                                ).withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(99),
                                border: Border.all(
                                  color: _priorityColor(
                                    priorityLevel,
                                  ).withValues(alpha: 0.55),
                                  width: 1,
                                ),
                              ),
                              child: PremiumText.caption(
                                _priorityLabel(priorityLevel),
                                color: _priorityColor(priorityLevel),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      AppGlassCard(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            PremiumText.caption(
                              'LAST ADMIN FEEDBACK',
                              color: AppColors.goldMuted,
                            ),
                            const SizedBox(height: 6),
                            PremiumText.body(
                              (lastAdminFeedback ?? '').isEmpty
                                  ? 'No QC/admin feedback has been recorded yet.'
                                  : lastAdminFeedback!,
                              color: AppColors.textSecondary,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      AppGlassCard(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            PremiumText.caption(
                              'ADMIN INSTRUCTIONS',
                              color: AppColors.goldMuted,
                            ),
                            const SizedBox(height: 6),
                            PremiumText.body(
                              ((_assignment?['instructions'] ?? '') as String)
                                      .trim()
                                      .isEmpty
                                  ? 'No additional notes from admin.'
                                  : (_assignment?['instructions'] as String),
                              color: AppColors.textSecondary,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      ..._visibleItems().map((item) {
                        final title = (item['title_snapshot'] ?? 'Order item')
                            .toString();
                        final weight = (item['weight_grams'] ?? '').toString();
                        final metal = (item['metal_type_snapshot'] ?? '')
                            .toString();
                        final stone = (item['stone_details_snapshot'] ?? '')
                            .toString();
                        final dimensions = (item['dimensions_snapshot'] ?? '')
                            .toString();
                        final ringSize = (item['ring_size_snapshot'] ?? '')
                            .toString();
                        final images = _itemImageUrls(item);
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: AppGlassCard(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                PremiumText.caption(
                                  'MAKING SPECS',
                                  color: AppColors.goldMuted,
                                ),
                                const SizedBox(height: 6),
                                PremiumText.body(title),
                                const SizedBox(height: 10),
                                PremiumText.caption(
                                  'Weight',
                                  color: AppColors.textSecondary,
                                ),
                                PremiumText.body(
                                  weight.isEmpty ? '-' : '$weight g',
                                  color: AppColors.textSecondary,
                                ),
                                const SizedBox(height: 8),
                                PremiumText.caption(
                                  'Metal type',
                                  color: AppColors.textSecondary,
                                ),
                                PremiumText.body(
                                  metal.isEmpty ? '-' : metal,
                                  color: AppColors.textSecondary,
                                ),
                                const SizedBox(height: 8),
                                PremiumText.caption(
                                  'Stone details',
                                  color: AppColors.textSecondary,
                                ),
                                PremiumText.body(
                                  stone.isEmpty ? '-' : stone,
                                  color: AppColors.textSecondary,
                                ),
                                const SizedBox(height: 8),
                                PremiumText.caption(
                                  'Dimensions',
                                  color: AppColors.textSecondary,
                                ),
                                PremiumText.body(
                                  dimensions.isEmpty ? '-' : dimensions,
                                  color: AppColors.textSecondary,
                                ),
                                const SizedBox(height: 8),
                                PremiumText.caption(
                                  'Ring size',
                                  color: AppColors.textSecondary,
                                ),
                                PremiumText.body(
                                  ringSize.isEmpty ? '-' : ringSize,
                                  color: AppColors.textSecondary,
                                ),
                                const SizedBox(height: 10),
                                PremiumText.caption(
                                  'DESIGN PHOTOS',
                                  color: AppColors.goldMuted,
                                ),
                                const SizedBox(height: 8),
                                if (images.isEmpty)
                                  PremiumText.body(
                                    'No design photos attached.',
                                    color: AppColors.textSecondary,
                                  )
                                else
                                  Wrap(
                                    spacing: 8,
                                    runSpacing: 8,
                                    children: List.generate(
                                      images.length,
                                      (idx) => _buildZoomableThumb(
                                        imageUrls: images,
                                        index: idx,
                                        heroPrefix: 'artisan-design-${widget.assignmentId}-${item['id'] ?? title}',
                                      ),
                                    )
                                        .toList(growable: false),
                                  ),
                              ],
                            ),
                          ),
                        );
                      }),
                      if (_visibleItems().isEmpty)
                        AppGlassCard(
                          child: PremiumText.body(
                            'No item details available for this assignment yet.',
                            color: AppColors.textSecondary,
                          ),
                        ),
                      if (_inquiryAttachmentUrls().isNotEmpty)
                        AppGlassCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              PremiumText.caption(
                                'INITIAL INQUIRY PHOTOS',
                                color: AppColors.goldMuted,
                              ),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: List.generate(
                                  _inquiryAttachmentUrls().length,
                                  (idx) => _buildZoomableThumb(
                                    imageUrls: _inquiryAttachmentUrls(),
                                    index: idx,
                                    heroPrefix: 'artisan-inquiry-${widget.assignmentId}',
                                  ),
                                )
                                    .toList(growable: false),
                              ),
                            ],
                          ),
                        ),
                      if (_selectedReferenceUrls().isNotEmpty) ...[
                        const SizedBox(height: 12),
                        AppGlassCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              PremiumText.caption(
                                'SELECTED REFERENCE IMAGES',
                                color: AppColors.goldMuted,
                              ),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: List.generate(
                                  _selectedReferenceUrls().length,
                                  (idx) => _buildZoomableThumb(
                                    imageUrls: _selectedReferenceUrls(),
                                    index: idx,
                                    heroPrefix: 'artisan-reference-${widget.assignmentId}',
                                  ),
                                )
                                    .toList(growable: false),
                              ),
                            ],
                          ),
                        ),
                      ],
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

class _ProgressMetaSheet extends StatefulWidget {
  const _ProgressMetaSheet({
    required this.stages,
    required this.formatStageLabel,
  });

  final List<String> stages;
  final String Function(String) formatStageLabel;

  @override
  State<_ProgressMetaSheet> createState() => _ProgressMetaSheetState();
}

class _ProgressMetaSheetState extends State<_ProgressMetaSheet> {
  static const String _defaultStage = 'other';

  late String _selectedStage;
  late TextEditingController _noteCtrl;

  @override
  void initState() {
    super.initState();
    _selectedStage = _defaultStage;
    _noteCtrl = TextEditingController();
  }

  @override
  void dispose() {
    _noteCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
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
            InputDecorator(
              decoration: const InputDecoration(labelText: 'Stage'),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  isExpanded: true,
                  value: _selectedStage,
                  items: widget.stages
                      .map(
                        (stage) => DropdownMenuItem<String>(
                          value: stage,
                          child: Text(widget.formatStageLabel(stage)),
                        ),
                      )
                      .toList(growable: false),
                  onChanged: (v) {
                    if (v == null) return;
                    setState(() => _selectedStage = v);
                  },
                ),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _noteCtrl,
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
                    final note = _noteCtrl.text.trim();
                    Navigator.of(context).pop((
                      stage: _selectedStage,
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
  }
}
