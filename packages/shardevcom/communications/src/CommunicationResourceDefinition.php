<?php

declare(strict_types=1);

namespace Shardevcom\Communications;

use Shardevcom\Resources\ResourceDefinition;

/**
 * Permission vocabulary for the communications admin surface. There is no
 * generic repository: the endpoints use purpose-built use cases, but token
 * scopes still derive from this definition.
 */
final class CommunicationResourceDefinition
{
    public static function definition(): ResourceDefinition
    {
        return ResourceDefinition::make('communications')
            ->withActions(['view', 'update', 'send'])
            ->withFilters(['event_key', 'channel', 'status', 'recipient_id', 'recipient_guard'])
            ->withFields([
                'id',
                'event_key',
                'channel',
                'status',
                'recipient_type',
                'recipient_id',
                'recipient_guard',
                'locale',
                'created_at',
            ]);
    }
}
