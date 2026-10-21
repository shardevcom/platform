/// An OAuth provider enabled by the API (`GET auth/social/providers`).
class SocialProvider {
  const SocialProvider({required this.key, required this.name});

  final String key;
  final String name;

  factory SocialProvider.fromJson(Map<String, dynamic> json) => SocialProvider(
        key: json['key']?.toString() ?? '',
        name: json['name']?.toString() ?? '',
      );
}
