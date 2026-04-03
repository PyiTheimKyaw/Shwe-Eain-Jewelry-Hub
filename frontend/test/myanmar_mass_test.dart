import 'package:flutter_test/flutter_test.dart';
import 'package:jewelry_hub/core/units/myanmar_mass.dart';

void main() {
  group('MyanmarMassBreakdown.fromGrams', () {
    test('exactly one mass kyat', () {
      final b = MyanmarMassBreakdown.fromGrams(16.606);
      expect(b.kyat, 1);
      expect(b.pae, 0);
      expect(b.yway, closeTo(0, 1e-6));
    });

    test('zero grams', () {
      final b = MyanmarMassBreakdown.fromGrams(0);
      expect(b.kyat, 0);
      expect(b.pae, 0);
      expect(b.yway, closeTo(0, 1e-9));
    });

    test('negative grams treated as zero', () {
      final b = MyanmarMassBreakdown.fromGrams(-5);
      expect(b.kyat, 0);
      expect(b.pae, 0);
    });

    test('extension asBurmeseMass', () {
      final b = 16.606.asBurmeseMass;
      expect(b.kyat, 1);
    });
  });
}
