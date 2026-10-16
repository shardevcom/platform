/// The platform guards the mobile client can run as.
///
/// Each guard maps to an independent API surface (`/admin/api/v1`,
/// `/employee/api/v1`, `/api/v1`). A Passport token is only accepted by the
/// guard that issued it, so the app is built once per guard (a flavor) and
/// never offers a platform selector at login.
enum Guard {
  web('web', ''),
  admin('admin', 'admin/'),
  employee('employee', 'employee/');

  const Guard(this.id, this.prefix);

  /// Stable identifier shared with the API `plugin.json` and the web client.
  final String id;

  /// Path prefix of this guard's versioned REST surface ('' for `web`).
  final String prefix;

  /// Base path of the guard's versioned REST surface.
  String get apiBase => '/${prefix}api/v1';

  /// Resolves a guard from its [id], defaulting to [Guard.admin].
  static Guard fromId(String id) {
    for (final guard in Guard.values) {
      if (guard.id == id) {
        return guard;
      }
    }
    return Guard.admin;
  }

  /// Human label used by the shell and the login screen.
  String get label {
    switch (this) {
      case Guard.web:
        return 'Web';
      case Guard.admin:
        return 'Admin';
      case Guard.employee:
        return 'Employee';
    }
  }
}
