@component('mail::message')
    # Hello {{ $name }},

    @if ($isSettingPassword)
        You recently requested to set a password for your account on {{ config('app.name') }}.
        Please click the button below to verify your email and confirm this change.
    @else
        Thank you for registering with {{ config('app.name') }}!
        Please click the button below to verify your email address and complete your registration.
    @endif

    @component('mail::button', ['url' => $url])
        @if ($isSettingPassword)
            Verify Email & Set Password
        @else
            Verify Email Address
        @endif
    @endcomponent

    This verification link will expire in {{ $expires_in_hours }} hours.

    If you did not request this, no further action is required.

    Thanks,<br>
    {{ config('app.name') }}
@endcomponent
