import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:jewelry_hub/core/config/api_config.dart';
import 'package:jewelry_hub/features/auth/models/auth_user.dart';

class AuthApiException implements Exception {
  AuthApiException(this.message);
  final String message;

  @override
  String toString() => message;
}

class AuthApi {
  AuthApi._();

  static Map<String, String> _jsonHeaders({String? bearer}) {
    final h = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (bearer != null && bearer.isNotEmpty) {
      h['Authorization'] = 'Bearer $bearer';
    }
    return h;
  }

  static String _messageFromBody(String body) {
    try {
      final decoded = json.decode(body);
      if (decoded is! Map<String, dynamic>) {
        return 'Request failed';
      }
      final msg = decoded['message'];
      if (msg is String && msg.isNotEmpty) {
        return msg;
      }
      final errors = decoded['errors'];
      if (errors is Map<String, dynamic>) {
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty) {
            final first = value.first;
            if (first is String) {
              return first;
            }
          }
        }
      }
    } catch (_) {
      /* use fallback */
    }
    return 'Request failed';
  }

  static Future<({String token, AuthUser user})> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/auth/register');
    final body = <String, dynamic>{
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
    };
    final res = await http.post(
      uri,
      headers: _jsonHeaders(),
      body: json.encode(body),
    );
    if (res.statusCode != 201) {
      throw AuthApiException(_messageFromBody(res.body));
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final token = map['token'] as String?;
    final userMap = map['user'] as Map<String, dynamic>?;
    if (token == null || userMap == null) {
      throw AuthApiException('Invalid register response');
    }
    return (token: token, user: AuthUser.fromJson(userMap));
  }

  static Future<({String token, AuthUser user})> login({
    required String email,
    required String password,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/auth/login');
    final res = await http.post(
      uri,
      headers: _jsonHeaders(),
      body: json.encode({'email': email, 'password': password}),
    );
    if (res.statusCode != 200) {
      throw AuthApiException(_messageFromBody(res.body));
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    final token = map['token'] as String?;
    final userMap = map['user'] as Map<String, dynamic>?;
    if (token == null || userMap == null) {
      throw AuthApiException('Invalid login response');
    }
    return (token: token, user: AuthUser.fromJson(userMap));
  }

  static Future<void> logout(String token) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/auth/logout');
    final res = await http.post(
      uri,
      headers: _jsonHeaders(bearer: token),
    );
    if (res.statusCode != 200) {
      throw AuthApiException(_messageFromBody(res.body));
    }
  }

  static Future<AuthUser> me(String token) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/auth/me');
    final res = await http.get(
      uri,
      headers: _jsonHeaders(bearer: token),
    );
    if (res.statusCode != 200) {
      throw AuthApiException(_messageFromBody(res.body));
    }
    final map = json.decode(res.body) as Map<String, dynamic>;
    return AuthUser.fromJson(map);
  }

  static Future<void> updatePassword({
    required String token,
    required String currentPassword,
    required String newPassword,
    required String newPasswordConfirmation,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/auth/password');
    final res = await http.put(
      uri,
      headers: _jsonHeaders(bearer: token),
      body: json.encode({
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': newPasswordConfirmation,
      }),
    );
    if (res.statusCode != 200) {
      throw AuthApiException(_messageFromBody(res.body));
    }
  }

  static Future<void> closeAccount({
    required String token,
    required String currentPassword,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiV1}/auth/account');
    final res = await http.delete(
      uri,
      headers: _jsonHeaders(bearer: token),
      body: json.encode({'current_password': currentPassword}),
    );
    if (res.statusCode != 200) {
      throw AuthApiException(_messageFromBody(res.body));
    }
  }
}
