<?php

namespace Modules\ApiWebhookManager\Enums;

enum ApiScope: string
{
    // Contact Scopes
    case CONTACTS_READ = 'contacts:read';
    case CONTACTS_WRITE = 'contacts:write';
    case CONTACTS_DELETE = 'contacts:delete';

    // Message Scopes
    case MESSAGES_SEND = 'messages:send';
    case MESSAGES_READ = 'messages:read';

    // Group Scopes
    case GROUPS_READ = 'groups:read';
    case GROUPS_WRITE = 'groups:write';
    case GROUPS_DELETE = 'groups:delete';

    // Template Scopes
    case TEMPLATES_READ = 'templates:read';
    case TEMPLATES_SYNC = 'templates:sync';

    // Metadata Scopes
    case SOURCES_READ = 'sources:read';
    case SOURCES_WRITE = 'sources:write';
    case STATUSES_READ = 'statuses:read';
    case STATUSES_WRITE = 'statuses:write';

    // Account Scopes
    case ACCOUNT_READ = 'account:read';

    // Wildcard
    case ALL = '*';

    public function label(): string
    {
        return match ($this) {
            self::CONTACTS_READ => 'Read Contacts',
            self::CONTACTS_WRITE => 'Create & Update Contacts',
            self::CONTACTS_DELETE => 'Delete Contacts',
            self::MESSAGES_SEND => 'Send Messages',
            self::MESSAGES_READ => 'Read Messages',
            self::GROUPS_READ => 'Read Groups',
            self::GROUPS_WRITE => 'Create & Update Groups',
            self::GROUPS_DELETE => 'Delete Groups',
            self::TEMPLATES_READ => 'Read Templates',
            self::TEMPLATES_SYNC => 'Sync Templates from WhatsApp',
            self::SOURCES_READ => 'Read Sources',
            self::SOURCES_WRITE => 'Manage Sources',
            self::STATUSES_READ => 'Read Statuses',
            self::STATUSES_WRITE => 'Manage Statuses',
            self::ACCOUNT_READ => 'Read Account Information',
            self::ALL => 'Full Access',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CONTACTS_READ => 'List and view contact details',
            self::CONTACTS_WRITE => 'Create new contacts and update existing ones',
            self::CONTACTS_DELETE => 'Delete contacts from the system',
            self::MESSAGES_SEND => 'Send WhatsApp messages to contacts',
            self::MESSAGES_READ => 'View sent message history',
            self::GROUPS_READ => 'List and view contact groups',
            self::GROUPS_WRITE => 'Create and update contact groups',
            self::GROUPS_DELETE => 'Delete contact groups',
            self::TEMPLATES_READ => 'View WhatsApp message templates',
            self::TEMPLATES_SYNC => 'Synchronize templates from WhatsApp Business API',
            self::SOURCES_READ => 'View contact sources',
            self::SOURCES_WRITE => 'Create and manage contact sources',
            self::STATUSES_READ => 'View contact statuses',
            self::STATUSES_WRITE => 'Create and manage contact statuses',
            self::ACCOUNT_READ => 'View account info, usage stats, and limits',
            self::ALL => 'Grants access to all API endpoints and operations',
        };
    }

    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getResourceScopes(string $resource): array
    {
        return array_filter(self::all(), function ($scope) use ($resource) {
            return str_starts_with($scope, $resource.':');
        });
    }

    public function getResource(): string
    {
        if ($this === self::ALL) {
            return '*';
        }

        return explode(':', $this->value)[0];
    }

    public function getAction(): string
    {
        if ($this === self::ALL) {
            return '*';
        }

        return explode(':', $this->value)[1];
    }

    public static function fromString(string $scope): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $scope) {
                return $case;
            }
        }

        return null;
    }

    public static function groupedByResource(): array
    {
        $grouped = [];

        foreach (self::cases() as $scope) {
            if ($scope === self::ALL) {
                $grouped['admin'][] = $scope;

                continue;
            }

            $resource = $scope->getResource();
            $grouped[$resource][] = $scope;
        }

        return $grouped;
    }
}
