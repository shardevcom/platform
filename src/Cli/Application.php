<?php

declare(strict_types=1);

namespace Shardevcom\Cli;

use Shardevcom\Cli\Command\AddCommand;
use Shardevcom\Cli\Command\BuildCommand;
use Shardevcom\Cli\Command\CommandInterface;
use Shardevcom\Cli\Command\ConfigCommand;
use Shardevcom\Cli\Command\DemoCommand;
use Shardevcom\Cli\Command\DevCommand;
use Shardevcom\Cli\Command\DoctorCommand;
use Shardevcom\Cli\Command\GuardCommand;
use Shardevcom\Cli\Command\HelpCommand;
use Shardevcom\Cli\Command\InstallCommand;
use Shardevcom\Cli\Command\ListCommand;
use Shardevcom\Cli\Command\NewCommand;
use Shardevcom\Cli\Command\PackageCommand;
use Shardevcom\Cli\Command\PluginCommand;
use Shardevcom\Cli\Command\RemoveCommand;
use Shardevcom\Cli\Command\StatusCommand;
use Shardevcom\Cli\Command\VersionCommand;
use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\InputParser;

/**
 * Boundary: composes the CLI (router, parser, context) and turns any failure
 * into an exit code plus a printed message.
 *
 * `run()` never calls exit() — bin/platform does — so the whole CLI can be
 * exercised from a test with a fake context.
 */
final class Application
{
    private readonly CommandRouter $router;

    private readonly InputParser $parser;

    public function __construct(private readonly Context $context)
    {
        $this->parser = new InputParser();
        $this->router = new CommandRouter($this->commands());
    }

    /**
     * @param list<string> $argv full argv, including the program name
     */
    public function run(array $argv): int
    {
        $tokens = array_slice(array_values($argv), 1);
        $output = $this->context->output;
        $commandName = $tokens[0] ?? '';

        try {
            ['command' => $command, 'arguments' => $arguments] = $this->router->resolve($tokens);
            $commandName = $command->getName();

            if ($this->wantsHelp($arguments)) {
                $output->writeln($command->getHelp());

                return 0;
            }

            $input = $this->parser->parse($arguments, $command->getDefinition());

            return $command->execute($input, $this->context);
        } catch (InvalidInput $exception) {
            $output->errorln('Error: '.$exception->getMessage());

            $definition = $exception->definition();

            if ($definition !== null) {
                $output->errorln('Usage: platform '.$definition->usage($commandName === '' ? '<command>' : $commandName));
            }

            return $exception->exitCode();
        } catch (CommandFailed $exception) {
            $output->errorln('Error: '.$exception->getMessage());

            return $exception->exitCode();
        }
    }

    public function router(): CommandRouter
    {
        return $this->router;
    }

    public function context(): Context
    {
        return $this->context;
    }

    /**
     * @param list<string> $arguments
     */
    private function wantsHelp(array $arguments): bool
    {
        return in_array('--help', $arguments, true) || in_array('-h', $arguments, true);
    }

    /**
     * @return list<CommandInterface>
     */
    private function commands(): array
    {
        $commands = [
            new VersionCommand(),
            new ListCommand(),
            new NewCommand(),
            new AddCommand(),
            new RemoveCommand(),
            new InstallCommand(),
            new BuildCommand(),
            new DevCommand(),
            new StatusCommand(),
            new DoctorCommand($this->context),
            new PackageCommand(),
            new GuardCommand(),
            new ConfigCommand(),
            new DemoCommand(),
            new PluginCommand(),
        ];

        $help = new HelpCommand(static fn (): array => $commands);

        return [...$commands, $help];
    }
}