/// The platform response envelope: `{status, code, message, data, meta}`.
class ApiEnvelope<T> {
  const ApiEnvelope({
    required this.status,
    required this.code,
    required this.message,
    this.data,
    this.meta,
  });

  final String status;
  final String code;
  final String message;
  final T? data;
  final Map<String, dynamic>? meta;

  factory ApiEnvelope.fromJson(
    Map<String, dynamic> json, [
    T Function(Object? data)? decode,
  ]) {
    final raw = json['data'];
    return ApiEnvelope<T>(
      status: json['status']?.toString() ?? 'success',
      code: json['code']?.toString() ?? 'OK',
      message: json['message']?.toString() ?? '',
      data: decode == null ? raw as T? : decode(raw),
      meta: json['meta'] is Map ? Map<String, dynamic>.from(json['meta'] as Map) : null,
    );
  }
}

/// Pagination block carried by `meta` on list responses.
class Pagination {
  const Pagination({
    required this.total,
    required this.perPage,
    required this.currentPage,
    required this.lastPage,
    this.count,
  });

  final int total;
  final int perPage;
  final int currentPage;
  final int lastPage;
  final int? count;

  factory Pagination.fromJson(Map<String, dynamic> json) => Pagination(
        total: _int(json['total']),
        perPage: _int(json['per_page']),
        currentPage: _int(json['current_page']),
        lastPage: _int(json['last_page']),
        count: json['count'] == null ? null : _int(json['count']),
      );

  static int _int(Object? value) => int.tryParse(value?.toString() ?? '') ?? 0;
}

/// Error carrying the platform's semantic code (`FORBIDDEN`, `VALIDATION_FAILED`,
/// ...) plus the HTTP status, so callers branch on meaning, not on messages.
class ApiError implements Exception {
  const ApiError({
    required this.message,
    required this.code,
    this.status,
    this.details,
  });

  final String message;
  final String code;
  final int? status;
  final Map<String, dynamic>? details;

  @override
  String toString() => 'ApiError($code): $message';
}
