<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\GuardClientScaffolder;
use Shardevcom\Cli\Workspace\GuardDefinition;
use Tests\Unit\Cli\Support\FakeFilesystem;

/**
 * Beyond the guard enum, every client keeps core code that enumerates the
 * guards (a `Platform` union, login demo emails/labels); a new guard must grow
 * all of them or the client stops compiling.
 */
final class GuardClientScaffolderTest extends TestCase
{
    private const ROOT = '/ws';

    public function test_web_extends_the_platform_union_and_login_presets(): void
    {
        $filesystem = $this->filesystem();

        (new GuardClientScaffolder($filesystem))->scaffold(ComponentType::Web, self::ROOT.'/web', GuardDefinition::create('partner'));

        $this->assertStringContainsString(
            'export type Platform = "web" | "admin" | "employee" | "partner"',
            $filesystem->read(self::ROOT.'/web/packages/core/src/types.ts')
        );

        $login = $filesystem->read(self::ROOT.'/web/packages/core/src/pages/LoginPage.tsx');
        $this->assertStringContainsString('partner: "partner@example.com",', $login);
        $this->assertStringContainsString('partner: "Partner",', $login);
    }

    public function test_desktop_adds_the_login_demo_email_case(): void
    {
        $filesystem = $this->filesystem();

        (new GuardClientScaffolder($filesystem))->scaffold(ComponentType::Desktop, self::ROOT.'/desktop', GuardDefinition::create('partner'));

        $login = $filesystem->read(self::ROOT.'/desktop/packages/core/src/main/java/dev/shardev/core/ui/LoginView.java');
        $this->assertStringContainsString('case PARTNER -> "partner@example.com";', $login);
        $this->assertStringContainsString('case ADMIN -> "admin@example.com";', $login);
    }

    public function test_mobile_adds_the_login_demo_email_case(): void
    {
        $filesystem = $this->filesystem();

        (new GuardClientScaffolder($filesystem))->scaffold(ComponentType::Mobile, self::ROOT.'/mobile', GuardDefinition::create('partner'));

        $login = $filesystem->read(self::ROOT.'/mobile/packages/core/lib/src/pages/login_page.dart');
        $this->assertStringContainsString('case Guard.partner:', $login);
        $this->assertStringContainsString("return 'partner@example.com';", $login);
    }

    private function filesystem(): FakeFilesystem
    {
        return new FakeFilesystem([
            self::ROOT.'/web/packages/plugin-kit/src/types.ts' => 'export type Guard = "web" | "admin" | "employee"'."\n",
            self::ROOT.'/web/packages/core/src/types.ts' => 'export type Platform = "web" | "admin" | "employee"'."\n",
            self::ROOT.'/web/packages/core/src/pages/LoginPage.tsx' => <<<'TS'
                const DEMO_EMAIL: Record<Guard, string> = {
                  admin: "admin@example.com",
                  employee: "employee@example.com",
                  web: "user@example.com",
                }

                const GUARD_LABEL: Record<Guard, string> = {
                  admin: "Admin",
                  employee: "Employee",
                  web: "Web",
                }
                TS,
            self::ROOT.'/desktop/packages/plugin-kit/src/main/java/dev/shardev/pluginkit/Guard.java' => <<<'JAVA'
                public enum Guard {
                    WEB("web", ""),
                    ADMIN("admin", "admin/"),
                    EMPLOYEE("employee", "employee/");
                }
                JAVA,
            self::ROOT.'/desktop/packages/core/src/main/java/dev/shardev/core/ui/LoginView.java' => <<<'JAVA'
                public final class LoginView {
                    private static String demoEmail(Guard guard) {
                        return switch (guard) {
                            case WEB -> "user@example.com";
                            case ADMIN -> "admin@example.com";
                            case EMPLOYEE -> "employee@example.com";
                        };
                    }
                }
                JAVA,
            self::ROOT.'/mobile/packages/plugin_kit/lib/src/guard.dart' => <<<'DART'
                enum Guard {
                  web('web', ''),
                  admin('admin', 'admin/'),
                  employee('employee', 'employee/');

                  const Guard(this.id, this.prefix);
                }
                DART,
            self::ROOT.'/mobile/packages/core/lib/src/pages/login_page.dart' => <<<'DART'
                class LoginPage {
                  static String _demoEmail(Guard guard) {
                    switch (guard) {
                      case Guard.web:
                        return 'user@example.com';
                      case Guard.admin:
                        return 'admin@example.com';
                      case Guard.employee:
                        return 'employee@example.com';
                    }
                  }
                }
                DART,
        ]);
    }
}
