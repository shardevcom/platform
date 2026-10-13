<?php

declare(strict_types=1);

namespace Shardevcom\Application\ApplicationContext;

use Shardevcom\Application\Contracts\ApplicationContextBuilder;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ApplicationIdentity;
use Shardevcom\Core\Contracts\PermissionResolver;
use Symfony\Component\Console\Input\InputInterface;

/**
 * CLI-specific ApplicationContext builder.
 *
 * Builds context from console command input, using options/arguments
 * for user, tenant, and observability IDs.
 */
final class CliApplicationContextBuilder implements ApplicationContextBuilder
{
    public function __construct(
        private readonly BaseApplicationContextBuilder $base,
    ) {}

    public function fromIdentity(
        ApplicationIdentity $identity,
        array $additional = []
    ): ApplicationContext {
        return $this->base->fromIdentity($identity, $additional);
    }

    public function forGuest(array $additional = []): ApplicationContext
    {
        return $this->base->forGuest($additional);
    }

    /**
     * Build context from console input.
     *
     * Expected options:
     * - --user-id
     * - --tenant-id
     * - --application-id
     * - --device-id
     * - --locale
     * - --timezone
     * - --request-id
     * - --correlation-id
     */
    public function fromInput(InputInterface $input): ApplicationContext
    {
        $userId = $input->getOption('user-id');

        if ($userId === null) {
            return $this->base->forGuest([
                'tenantId' => $input->getOption('tenant-id'),
                'applicationId' => $input->getOption('application-id'),
                'deviceId' => $input->getOption('device-id'),
                'locale' => $input->getOption('locale'),
                'timezone' => $input->getOption('timezone'),
                'requestId' => $input->getOption('request-id'),
                'correlationId' => $input->getOption('correlation-id'),
            ]);
        }

        // For CLI, we create a synthetic identity
        $identity = new ApplicationIdentity(
            id: (string) $userId,
            kind: 'cli',
            attributes: [
                'via' => 'cli',
            ],
        );

        return $this->base->fromIdentity($identity, [
            'tenantId' => $input->getOption('tenant-id'),
            'applicationId' => $input->getOption('application-id'),
            'deviceId' => $input->getOption('device-id'),
            'locale' => $input->getOption('locale'),
            'timezone' => $input->getOption('timezone'),
            'requestId' => $input->getOption('request-id'),
            'correlationId' => $input->getOption('correlation-id'),
        ]);
    }
}