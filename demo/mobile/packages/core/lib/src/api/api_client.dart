import 'package:dio/dio.dart';
import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

import 'envelope.dart';

/// Thin REST adapter over one guard's versioned surface.
///
/// Mirrors the web `apiRequest`: builds the platform query shape
/// (`filter[sku]`, `sort[]`), unwraps the `{status, code, message, data, meta}`
/// envelope and turns a non-2xx into an [ApiError] carrying the semantic code.
class ApiClient {
  ApiClient({
    required this.guard,
    required String host,
    Dio? dio,
    String? Function()? token,
  })  : _token = token ?? (() => null),
        _dio = dio ?? Dio(BaseOptions(baseUrl: '$host${guard.apiBase}')) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          options.headers['Accept'] = 'application/json';
          final token = _token();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
  }

  final Guard guard;
  final Dio _dio;
  final String? Function() _token;

  /// Performs a request and returns the envelope's `data`.
  Future<T> data<T>(
    String path, {
    String method = 'GET',
    Map<String, Object?>? query,
    Object? body,
  }) async {
    try {
      final response = await _dio.request<Object?>(
        '$path${_buildQuery(query)}',
        data: body,
        options: Options(
          method: method,
          contentType: body == null ? null : Headers.jsonContentType,
        ),
      );

      final envelope = response.data;
      if (envelope is Map && envelope.containsKey('data')) {
        return envelope['data'] as T;
      }
      return envelope as T;
    } on DioException catch (error) {
      throw _toApiError(error);
    }
  }

  /// Full envelope, for callers that need `meta` (pagination, details).
  Future<ApiEnvelope<T>> envelope<T>(
    String path, {
    String method = 'GET',
    Map<String, Object?>? query,
    Object? body,
  }) async {
    try {
      final response = await _dio.request<Object?>(
        '$path${_buildQuery(query)}',
        data: body,
        options: Options(
          method: method,
          contentType: body == null ? null : Headers.jsonContentType,
        ),
      );

      final raw = response.data;
      if (raw is Map<String, dynamic>) {
        return ApiEnvelope<T>.fromJson(raw);
      }
      return ApiEnvelope<T>(status: 'success', code: 'OK', message: '', data: raw as T?);
    } on DioException catch (error) {
      throw _toApiError(error);
    }
  }

  static String _buildQuery(Map<String, Object?>? query) {
    if (query == null || query.isEmpty) {
      return '';
    }

    final params = <String, List<String>>{};

    void append(String key, Object? value) {
      if (value == null || value == '') {
        return;
      }
      if (value is List) {
        for (final item in value) {
          append('$key[]', item);
        }
      } else if (value is Map) {
        value.forEach((k, v) => append('$key[$k]', v));
      } else {
        (params[key] ??= []).add(value.toString());
      }
    }

    query.forEach(append);

    final encoded = params.entries
        .expand(
          (entry) => entry.value.map(
            (value) => '${Uri.encodeQueryComponent(entry.key)}=${Uri.encodeQueryComponent(value)}',
          ),
        )
        .join('&');

    return encoded.isEmpty ? '' : '?$encoded';
  }

  static ApiError _toApiError(DioException error) {
    final response = error.response;
    final status = response?.statusCode;
    final data = response?.data;

    if (data is Map) {
      final meta = data['meta'];
      final details = meta is Map ? meta['details'] : null;
      return ApiError(
        message: data['message']?.toString() ?? error.message ?? 'Request failed',
        code: data['code']?.toString() ?? 'HTTP_${status ?? 0}',
        status: status,
        details: details is Map ? Map<String, dynamic>.from(details) : null,
      );
    }

    return ApiError(
      message: error.message ?? 'Request failed',
      code: 'HTTP_${status ?? 0}',
      status: status,
    );
  }
}
