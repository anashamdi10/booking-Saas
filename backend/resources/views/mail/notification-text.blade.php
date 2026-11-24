{{ $heading }}

@foreach ($lines as $line)
{{ $line }}
@endforeach
@if (! empty($actionUrl))

{{ $actionText }}: {{ $actionUrl }}
@endif

--
{{ $footer }}
