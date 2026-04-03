// Firebase options for `shwe-eain-jewelry-hub`.
// Android / iOS keys come from `android/app/google-services.json` and
// `ios/Runner/GoogleService-Info.plist`. Web block matches the Firebase Console Web app.
// Use the same web `apiKey` for `FIREBASE_WEB_API_KEY` in `backend/.env` (Filament chat).

import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show defaultTargetPlatform, kIsWeb, TargetPlatform;

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      return web;
    }
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      case TargetPlatform.macOS:
        return macos;
      default:
        return ios;
    }
  }

  static const FirebaseOptions web = FirebaseOptions(
    apiKey: 'AIzaSyAsJuVZ_KjStlxXwN0RHqpMOWDEZB9k9fk',
    appId: '1:885710177863:web:ef5eb17a2127003d649362',
    messagingSenderId: '885710177863',
    projectId: 'shwe-eain-jewelry-hub',
    authDomain: 'shwe-eain-jewelry-hub.firebaseapp.com',
    storageBucket: 'shwe-eain-jewelry-hub.firebasestorage.app',
    measurementId: 'G-8715QGPHCV',
  );

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyBxFDjfhHKvGzhf9qAaom5grdRhLB5M6q4',
    appId: '1:885710177863:android:59d390f3936560b3649362',
    messagingSenderId: '885710177863',
    projectId: 'shwe-eain-jewelry-hub',
    authDomain: 'shwe-eain-jewelry-hub.firebaseapp.com',
    storageBucket: 'shwe-eain-jewelry-hub.firebasestorage.app',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyDPbAGU9kPkODGulUZtZM5ztrM8XYwewPo',
    appId: '1:885710177863:ios:f433fce197248ec2649362',
    messagingSenderId: '885710177863',
    projectId: 'shwe-eain-jewelry-hub',
    authDomain: 'shwe-eain-jewelry-hub.firebaseapp.com',
    storageBucket: 'shwe-eain-jewelry-hub.firebasestorage.app',
    iosBundleId: 'com.shweeain.hub.jewelryHub',
  );

  static const FirebaseOptions macos = ios;
}
