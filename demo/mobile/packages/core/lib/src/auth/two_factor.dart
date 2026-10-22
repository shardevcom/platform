/// Pending second-factor step returned by a privileged login.
class PendingTwoFactor {
  const PendingTwoFactor({
    required this.token,
    required this.enrollment,
    this.setup,
  });

  final String token;
  final bool enrollment;
  final TwoFactorSetup? setup;
}

/// TOTP enrollment payload, including one-time plaintext recovery codes.
class TwoFactorSetup {
  const TwoFactorSetup({
    required this.secret,
    required this.recoveryCodes,
    this.otpauthUrl,
    this.qrCode,
    this.instructions,
  });

  final String secret;
  final String? otpauthUrl;
  final String? qrCode;
  final List<String> recoveryCodes;
  final String? instructions;

  factory TwoFactorSetup.fromJson(Map<String, dynamic> json) => TwoFactorSetup(
    secret: json['secret']?.toString() ?? '',
    otpauthUrl: json['otpauth_url']?.toString(),
    qrCode: json['qr_code']?.toString(),
    recoveryCodes:
        (json['recovery_codes'] as List?)
            ?.map((code) => code.toString())
            .toList() ??
        const [],
    instructions: json['instructions']?.toString(),
  );
}
