<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.$farmName : $farmName }}
</title>

<meta name="theme-color" content="#166534" />

<link rel="icon" href="{{ asset('ems-logo.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('ems-logo.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
