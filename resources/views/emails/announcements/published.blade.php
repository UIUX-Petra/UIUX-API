<x-mail::message>

# {{ $title }}

{!! nl2br(e($detail)) !!}

Terima kasih,<br>
{{ config('app.name') }}

</x-mail::message>