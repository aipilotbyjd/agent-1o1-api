<?php

namespace App\Enums\Notifications;

/**
 * The fixed catalogue of events a user can subscribe to.
 *
 * Adding a case here is what makes an event toggleable; nothing outside this
 * enum may be stored as a notification preference.
 */
enum NotificationEvent: string
{
    case MemberInvited = 'workspace.member_invited';
    case MemberJoined = 'workspace.member_joined';
    case MemberRemoved = 'workspace.member_removed';
    case MemberRoleChanged = 'workspace.member_role_changed';
    case RunApprovalRequested = 'run.approval_requested';
    case TrialEnding = 'billing.trial_ending';
    case PaymentFailed = 'billing.payment_failed';

    /**
     * Delivery defaults applied when a user has no saved preference for an event.
     */
    public const DEFAULT_IN_APP = true;

    public const DEFAULT_EMAIL = false;

    public function label(): string
    {
        return match ($this) {
            self::MemberInvited => 'Member invited',
            self::MemberJoined => 'Member joined',
            self::MemberRemoved => 'Member removed',
            self::MemberRoleChanged => 'Member role changed',
            self::RunApprovalRequested => 'Run approval requested',
            self::TrialEnding => 'Trial ending',
            self::PaymentFailed => 'Payment failed',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MemberInvited => 'Someone was invited to the workspace.',
            self::MemberJoined => 'An invited member accepted and joined the workspace.',
            self::MemberRemoved => 'A member was removed from the workspace.',
            self::MemberRoleChanged => "A member's role was changed.",
            self::RunApprovalRequested => 'A workflow run is waiting on your approval to continue.',
            self::TrialEnding => 'Your trial is about to end.',
            self::PaymentFailed => 'A payment attempt failed.',
        };
    }

    /**
     * @return array{key: string, label: string, description: string, defaults: array{in_app: bool, email: bool}}
     */
    public function toCatalogEntry(): array
    {
        return [
            'key' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'defaults' => [
                'in_app' => self::DEFAULT_IN_APP,
                'email' => self::DEFAULT_EMAIL,
            ],
        ];
    }

    /**
     * Every toggleable event, ready to render as a notification settings screen.
     *
     * @return array<int, array{key: string, label: string, description: string, defaults: array{in_app: bool, email: bool}}>
     */
    public static function catalog(): array
    {
        return array_map(
            static fn (self $event): array => $event->toCatalogEntry(),
            self::cases(),
        );
    }
}
