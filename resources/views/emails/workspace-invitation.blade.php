@component('mail::message')
# You've been invited

You've been invited to join **{{ $workspaceName }}** as a **{{ $role }}**.

@component('mail::button', ['url' => $acceptUrl])
Accept Invitation
@endcomponent

This invitation expires on {{ $expiresAt->toFormattedDateString() }}.

If you were not expecting this invitation, you may ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
