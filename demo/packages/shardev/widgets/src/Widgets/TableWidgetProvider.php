<?php

declare(strict_types=1);

namespace Shardevcom\Widgets\Widgets;

use Shardevcom\Widgets\Contracts\WidgetProvider;
use Shardevcom\Widgets\Contracts\WidgetVisibility;
use Shardevcom\Core\ApplicationContext;

/**
 * Table widget provider.
 *
 * Returns tabular data for dashboards.
 */
final class TableWidgetProvider implements WidgetProvider
{
    public function key(): string
    {
        return 'table';
    }

    public function visibility(): WidgetVisibility
    {
        return WidgetVisibility::ADMIN;
    }

    public function resolve(ApplicationContext $context): array
    {
        return [
            'type' => 'table',
            'title' => 'Recent Users',
            'columns' => [
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                ['key' => 'email', 'label' => 'Email', 'sortable' => true],
                ['key' => 'role', 'label' => 'Role', 'sortable' => true],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
            ],
            'rows' => [
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'Admin', 'created_at' => '2024-01-15'],
                ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'role' => 'User', 'created_at' => '2024-01-20'],
                ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com', 'role' => 'Editor', 'created_at' => '2024-02-01'],
            ],
            'pagination' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 3,
            ],
        ];
    }
}