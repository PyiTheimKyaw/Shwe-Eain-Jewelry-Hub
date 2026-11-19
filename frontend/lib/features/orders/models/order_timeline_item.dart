import 'package:jewelry_hub/features/orders/models/order_progress_event.dart';

enum OrderTimelineItemKind { milestone, artisanUpdate }

class OrderTimelineItem {
  const OrderTimelineItem._({
    required this.kind,
    required this.title,
    required this.caption,
    required this.at,
    this.progressEvent,
  });

  final OrderTimelineItemKind kind;
  final String title;
  final String? caption;
  final DateTime? at;
  final OrderProgressEvent? progressEvent;

  factory OrderTimelineItem.milestone({
    required String title,
    String? caption,
    DateTime? at,
  }) {
    return OrderTimelineItem._(
      kind: OrderTimelineItemKind.milestone,
      title: title,
      caption: caption,
      at: at,
    );
  }

  factory OrderTimelineItem.artisanUpdate(OrderProgressEvent event) {
    final stageLabel = event.stage.replaceAll('_', ' ');
    return OrderTimelineItem._(
      kind: OrderTimelineItemKind.artisanUpdate,
      title: 'Workshop update · $stageLabel',
      caption: event.note,
      at: event.createdAt,
      progressEvent: event,
    );
  }
}
