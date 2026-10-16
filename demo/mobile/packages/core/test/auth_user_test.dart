import 'package:flutter_test/flutter_test.dart';
import 'package:shardev_core/shardev_core.dart';

void main() {
  group('AuthUser.can', () {
    test('grants an exact ability', () {
      const user = AuthUser(id: '1', abilities: ['products.view']);
      expect(user.can('products.view'), isTrue);
      expect(user.can('products.delete'), isFalse);
    });

    test('grants a resource wildcard', () {
      const user = AuthUser(id: '1', abilities: ['products.*']);
      expect(user.can('products.create'), isTrue);
      expect(user.can('brands.create'), isFalse);
    });

    test('grants everything with the global wildcard', () {
      const user = AuthUser(id: '1', abilities: ['*']);
      expect(user.can('anything.at.all'), isTrue);
    });
  });

  test('ApiError carries the semantic code and status', () {
    const error = ApiError(message: 'Forbidden', code: 'FORBIDDEN', status: 403);
    expect(error.code, 'FORBIDDEN');
    expect(error.status, 403);
    expect(error.toString(), contains('FORBIDDEN'));
  });

  test('ApiEnvelope decodes data and meta', () {
    final envelope = ApiEnvelope<Map<String, dynamic>>.fromJson({
      'status': 'success',
      'code': 'OK',
      'message': 'ok',
      'data': {'id': '1'},
      'meta': {'request_id': 'r-1'},
    });

    expect(envelope.data, {'id': '1'});
    expect(envelope.meta?['request_id'], 'r-1');
  });
}
