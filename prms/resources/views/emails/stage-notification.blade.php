@component('mail::message')

{{ $body }}

@if($recordUrl)
@component('mail::button', ['url' => $recordUrl])
View Record
@endcomponent
@endif

{{ config('app.name') }}
@endcomponent
