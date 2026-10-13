<?php

declare(strict_types=1);

namespace Shardevcom\Cli;

use Shardevcom\Cli\Command\CommandInterface;
use Shardevcom\Cli\Command\GroupCommandInterface;
use Shardevcom\Cli\Command\HelpCommand;
use Shardevcom\Cli\Exception\InvalidInput;

/**
 * Boundary: resolves tokens to a command and runs it.
 *
 * Registration is explicit (one line per command) rather than a filesystem
 * scan: the router must not depend on the autoloader's classmap to know what
 * the CLI can do. Names may be multi-word ('package make') and are matched
 * greedily, longest first.
 */
final class CommandRouter
{
    /** @var array<string, CommandInterface> */
    private array $index = [];

    /**
     * @param list<CommandInterface> $commands
     */
    public function __construct(private readonly array $commands = [])
    {
        foreach ($commands as $command) {
            $this->register($command);
        }
    }

    public function register(CommandInterface $command): void
    {
        $this->index[$command->getName()] = $command;

        foreach ($command->getAliases() as $alias) {
            $this->index[$alias] = $command;
        }

        if ($command instanceof GroupCommandInterface) {
            foreach ($command->subCommands() as $name => $subCommand) {
                $this->index[$command->getName().' '.$name] = $subCommand;

                foreach ($subCommand->getAliases() as $alias) {
                    $this->index[$command->getName().' '.$alias] = $subCommand;
                }
            }
        }
    }

    /**
     * @param list<string> $tokens tokens WITHOUT the program name
     *
     * @return array{command: CommandInterface, arguments: list<string>}
     */
    public function resolve(array $tokens): array
    {
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));

        if ($tokens === [] || $this->isHelpToken($tokens[0])) {
            return [
                'command' => new HelpCommand(fn (): array => $this->commands),
                'arguments' => $tokens,
            ];
        }

        for ($length = min(count($tokens), 3); $length >= 1; $length--) {
            $name = implode(' ', array_slice($tokens, 0, $length));

            if (isset($this->index[$name])) {
                return ['command' => $this->index[$name], 'arguments' => array_slice($tokens, $length)];
            }
        }

        throw InvalidInput::create("Unknown command '{$tokens[0]}'. Run 'platform --help' to see available commands.");
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->index);
    }

    /**
     * @return list<CommandInterface>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    public function has(string $name): bool
    {
        return isset($this->index[$name]);
    }

    private function isHelpToken(string $token): bool
    {
        return in_array($token, ['--help', '-h', 'help'], true);
    }
}