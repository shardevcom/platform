<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Command\CommandInterface;
use Shardevcom\Cli\Command\DoctorCommand;
use Shardevcom\Cli\Command\ListCommand;
use Shardevcom\Cli\Command\NewCommand;
use Shardevcom\Cli\Command\PackageCommand;
use Shardevcom\Cli\Command\VersionCommand;
use Shardevcom\Cli\CommandRouter;
use Shardevcom\Cli\Context;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\Input;
use Shardevcom\Cli\Input\InputDefinition;
use Tests\Unit\Cli\Support\FakeFilesystem;
use Tests\Unit\Cli\Support\RecordingPrinter;
use Tests\Unit\Cli\Support\FakeProcessRunner;

/**
 * Acceptance for the router: commands are resolved by name, alias and nested
 * name without any switch statement.
 */
final class CommandRouterTest extends TestCase
{
    public function test_resolves_top_level_commands_by_name(): void
    {
        $router = $this->router();

        $this->assertInstanceOf(NewCommand::class, $router->resolve(['new', 'app'])['command']);
        $this->assertInstanceOf(DoctorCommand::class, $router->resolve(['doctor'])['command']);
    }

    public function test_resolves_aliases_to_the_same_command(): void
    {
        $router = $this->router();

        $this->assertSame($router->resolve(['version'])['command'], $router->resolve(['--version'])['command']);
        $this->assertSame($router->resolve(['-V'])['command'], $router->resolve(['version'])['command']);
        $this->assertInstanceOf(
            ListCommand::class,
            $router->resolve(['package', 'list'])['command']
        );
    }

    public function test_resolves_nested_command_and_returns_leftover_arguments(): void
    {
        $resolved = $this->router()->resolve(['package', 'make', 'billing', '--dry-run']);

        $this->assertSame('package make', $resolved['command']->getName());
        $this->assertSame(['billing', '--dry-run'], $resolved['arguments']);
    }

    public function test_bare_invocation_and_help_resolve_to_help(): void
    {
        $router = $this->router();

        $this->assertSame('help', $router->resolve([])['command']->getName());
        $this->assertSame('help', $router->resolve(['--help'])['command']->getName());
    }

    public function test_unknown_command_fails_with_a_pointer_to_help(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessage("Unknown command 'nope'");

        $this->router()->resolve(['nope']);
    }

    public function test_registering_a_group_exposes_its_subcommands(): void
    {
        $router = $this->router();

        $this->assertTrue($router->has('package'));
        $this->assertTrue($router->has('package make'));
        $this->assertInstanceOf(PackageCommand::class, $router->resolve(['package'])['command']);
    }

    public function test_every_command_declares_a_summary_and_help(): void
    {
        foreach ($this->router()->commands() as $command) {
            $this->assertInstanceOf(CommandInterface::class, $command);
            $this->assertNotSame('', $command->getSummary(), $command->getName());
            $this->assertStringContainsString('Usage:', $command->getHelp(), $command->getName());
        }
    }

    public function test_help_lists_every_command_except_itself(): void
    {
        $help = $this->router()->resolve([])['command']->getHelp();

        $this->assertStringContainsString('new', $help);
        $this->assertStringContainsString('doctor', $help);
        $this->assertStringContainsString('package', $help);
        $this->assertStringNotContainsString('Show this help', $help);
    }

    private function router(): CommandRouter
    {
        $recorder = RecordingPrinter::create();

        $context = new Context(
            $recorder->printer(),
            new FakeFilesystem(),
            new FakeProcessRunner(),
            '/tmp',
            '/tmp/framework',
        );

        return new CommandRouter([
            new VersionCommand(),
            new ListCommand(),
            new NewCommand(),
            new DoctorCommand($context),
            new PackageCommand(),
            new HelpStub(),
        ]);
    }
}

/**
 * Stand-in for the real help command, which needs the command list itself.
 */
final class HelpStub implements CommandInterface
{
    public function getName(): string
    {
        return 'help';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getSummary(): string
    {
        return 'Show this help';
    }

    public function getDefinition(): InputDefinition
    {
        return InputDefinition::create();
    }

    public function getHelp(): string
    {
        return 'Usage: platform help';
    }

    public function execute(Input $input, Context $context): int
    {
        return 0;
    }
}