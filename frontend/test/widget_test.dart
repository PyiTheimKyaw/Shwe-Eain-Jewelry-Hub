import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jewelry_hub/main.dart';

void main() {
  setUpAll(() {
    FlutterSecureStorage.setMockInitialValues({});
  });

  testWidgets('JewelryHubApp builds (auth gate → login)', (WidgetTester tester) async {
    await tester.pumpWidget(const JewelryHubApp());
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    await tester.pump();
    expect(find.text('Sign in'), findsWidgets);
  });
}
