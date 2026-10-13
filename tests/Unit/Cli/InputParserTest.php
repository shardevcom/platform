<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Exception\InvalidInput;
use Shardevcom\Cli\Input\InputDefinition;
use Shardevcom\Cli\Input\InputParser;

/**
 * Acceptance for the option parser: positional binding, valued options in both
 * forms, boolean flags, shortcuts, defaults and loud failures.
 */
final class InputParserTest extends TestCase
{
    private InputParser $parser;

    protected function setUp(): void
    {
        $this->parser = new InputParser();
    }

    public function test_binds_positionals_by_declared_name(): void
    {
        $input = $this->parse(['my-app']);

        $this->assertSame('my-app', $input->argument('target'));
        $this->assertNull($input->argument('missing'));
    }

    public function test_reads_valued_option_in_both_forms(): void
    {
        $this->assertSame('12', $this->parse(['--laravel=12'])->option('laravel'));
        $this->assertSame('12.61', $this->parse(['--laravel', '12.61'])->option('laravel'));
    }

    public function test_boolean_flags_are_true_and_reject_values(): void
    {
        $this->assertTrue($this->parse(['--dry-run'])->flag('dry-run'));
        $this->assertFalse($this->parse([])->flag('dry-run'));

        $this->expectException(InvalidInput::class);
        $this->parse(['--dry-run=yes']);
    }

    public function test_shortcuts_are_equivalent_to_long_names(): void
    {
        $this->assertTrue($this->parse(['-n'])->flag('no-interaction'));
        $this->assertSame('12', $this->parse(['-d', '--laravel=12'])->option('laravel'));
    }

    public function test_defaults_apply_when_the_option_is_absent(): void
    {
        $input = $this->parse([]);

        $this->assertSame('12', $input->option('laravel'));
        $this->assertTrue($input->hasOption('laravel'));
    }

    public function test_double_dash_ends_option_parsing(): void
    {
        $input = $this->parse(['--dry-run', '--', '--laravel=13']);

        $this->assertTrue($input->flag('dry-run'));
        $this->assertSame('12', $input->option('laravel'));
        $this->assertSame(['target' => '--laravel=13'], $input->arguments());
    }

    public function test_unknown_option_fails_instead_of_being_ignored(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessage("Unknown option '--nope'");

        $this->parse(['--nope']);
    }

    public function test_valued_option_without_value_fails(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessage("Option '--laravel' requires a value.");

        $this->parse(['--laravel']);
    }

    public function test_extra_positionals_fail(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessage('Too many arguments.');

        $this->parse(['one', 'two']);
    }

    public function test_usage_line_is_derived_from_the_definition(): void
    {
        $definition = $this->definition();

        $this->assertSame(
            'platform new <target> [--laravel=<value>] [--no-interaction] [--dry-run]',
            $definition->usage('platform new')
        );
    }

    /**
     * @param list<string> $tokens
     */
    private function parse(array $tokens): \Shardevcom\Cli\Input\Input
    {
        return $this->parser->parse($tokens, $this->definition());
    }

    private function definition(): InputDefinition
    {
        return InputDefinition::create(
            arguments: ['target' => 'Directory to create'],
            options: [
                'laravel' => ['description' => 'Laravel version', 'value' => true, 'default' => '12'],
                'no-interaction' => ['description' => 'Never prompt Composer', 'shortcut' => 'n'],
                'dry-run' => ['description' => 'Dry run', 'shortcut' => 'd'],
            ]
        );
    }
}