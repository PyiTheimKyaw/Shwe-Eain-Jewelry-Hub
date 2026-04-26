import 'package:flutter/foundation.dart';
import 'package:jewelry_hub/core/notifications/push_notification_manager.dart';
import 'package:jewelry_hub/core/auth/auth_token_storage.dart';
import 'package:jewelry_hub/features/auth/data/auth_api.dart';
import 'package:jewelry_hub/features/auth/models/auth_user.dart';

class AuthProvider extends ChangeNotifier {
  AuthProvider() {
    bootstrap();
  }

  bool _bootstrapComplete = false;
  bool _sessionBusy = false;
  String? _token;
  AuthUser? _user;
  String? _lastError;

  bool get isBootstrapComplete => _bootstrapComplete;

  bool get isSessionBusy => _sessionBusy;

  AuthUser? get user => _user;

  String? get token => _token;

  String? get lastError => _lastError;

  bool get isSignedIn => _user != null;

  Future<void> bootstrap() async {
    _bootstrapComplete = false;
    _lastError = null;
    notifyListeners();

    final stored = await AuthTokenStorage.read();
    if (stored == null || stored.isEmpty) {
      _token = null;
      _user = null;
      _bootstrapComplete = true;
      notifyListeners();
      return;
    }

    try {
      _user = await AuthApi.me(stored);
      _token = stored;
      try {
        await PushNotificationManager.instance.registerForSession(
          bearerToken: stored,
          role: _user?.role ?? 'unknown',
        );
      } catch (_) {
        // Keep auth bootstrap resilient if push setup fails.
      }
    } on AuthApiException {
      await AuthTokenStorage.clear();
      _token = null;
      _user = null;
    } catch (_) {
      await AuthTokenStorage.clear();
      _token = null;
      _user = null;
    }

    _bootstrapComplete = true;
    notifyListeners();
  }

  Future<bool> login({required String email, required String password}) async {
    _sessionBusy = true;
    _lastError = null;
    notifyListeners();
    try {
      final result = await AuthApi.login(email: email, password: password);
      await AuthTokenStorage.write(result.token);
      _token = result.token;
      _user = result.user;
      try {
        await PushNotificationManager.instance.registerForSession(
          bearerToken: result.token,
          role: result.user.role,
        );
      } catch (_) {
        // Login should still succeed without push registration.
      }
      _sessionBusy = false;
      notifyListeners();
      return true;
    } on AuthApiException catch (e) {
      _lastError = e.message;
      _sessionBusy = false;
      notifyListeners();
      return false;
    } catch (e) {
      _lastError = e.toString();
      _sessionBusy = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) async {
    _sessionBusy = true;
    _lastError = null;
    notifyListeners();
    try {
      final result = await AuthApi.register(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
        phone: phone,
      );
      await AuthTokenStorage.write(result.token);
      _token = result.token;
      _user = result.user;
      try {
        await PushNotificationManager.instance.registerForSession(
          bearerToken: result.token,
          role: result.user.role,
        );
      } catch (_) {
        // Register should still succeed without push registration.
      }
      _sessionBusy = false;
      notifyListeners();
      return true;
    } on AuthApiException catch (e) {
      _lastError = e.message;
      _sessionBusy = false;
      notifyListeners();
      return false;
    } catch (e) {
      _lastError = e.toString();
      _sessionBusy = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    final t = _token;
    _sessionBusy = true;
    notifyListeners();
    if (t != null) {
      try {
        try {
          await PushNotificationManager.instance.unregisterForSession(
            bearerToken: t,
          );
        } catch (_) {
          // Continue logout even if push token cleanup fails.
        }
        await AuthApi.logout(t);
      } catch (_) {
        /* still clear local session */
      }
    }
    await AuthTokenStorage.clear();
    _token = null;
    _user = null;
    _sessionBusy = false;
    _lastError = null;
    notifyListeners();
  }

  void clearLastError() {
    _lastError = null;
    notifyListeners();
  }

  Future<bool> updatePassword({
    required String currentPassword,
    required String newPassword,
    required String newPasswordConfirmation,
  }) async {
    final t = _token;
    if (t == null) return false;
    _sessionBusy = true;
    _lastError = null;
    notifyListeners();
    try {
      await AuthApi.updatePassword(
        token: t,
        currentPassword: currentPassword,
        newPassword: newPassword,
        newPasswordConfirmation: newPasswordConfirmation,
      );
      _sessionBusy = false;
      notifyListeners();
      return true;
    } on AuthApiException catch (e) {
      _lastError = e.message;
      _sessionBusy = false;
      notifyListeners();
      return false;
    } catch (e) {
      _lastError = e.toString();
      _sessionBusy = false;
      notifyListeners();
      return false;
    }
  }

  /// Permanently closes the customer account on the server and clears local session.
  Future<bool> closeAccount({required String currentPassword}) async {
    final t = _token;
    if (t == null) return false;
    _sessionBusy = true;
    _lastError = null;
    notifyListeners();
    try {
      try {
        await PushNotificationManager.instance.unregisterForSession(
          bearerToken: t,
        );
      } catch (_) {}
      await AuthApi.closeAccount(token: t, currentPassword: currentPassword);
      await AuthTokenStorage.clear();
      _token = null;
      _user = null;
      _sessionBusy = false;
      _lastError = null;
      notifyListeners();
      return true;
    } on AuthApiException catch (e) {
      _lastError = e.message;
      _sessionBusy = false;
      notifyListeners();
      return false;
    } catch (e) {
      _lastError = e.toString();
      _sessionBusy = false;
      notifyListeners();
      return false;
    }
  }
}
