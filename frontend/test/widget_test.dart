import 'package:flutter_test/flutter_test.dart';
import 'package:jewelry_hub/main.dart';

void main() {
  testWidgets('JewelryHubApp builds', (WidgetTester tester) async {
    await tester.pumpWidget(const JewelryHubApp());
    await tester.pump();
    expect(find.text('Jewelry Hub'), findsOneWidget);
  });
}
