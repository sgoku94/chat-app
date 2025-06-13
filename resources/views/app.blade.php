<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat App</title>
    @php
        $manifest = json_decode(file_get_contents(public_path('asset-manifest.json')), true);
        $cssFile = $manifest['files']['main.css'] ?? '/static/css/main.css';
        $jsFile = $manifest['files']['main.js'] ?? '/static/js/main.js';
    @endphp
    <link rel="stylesheet" href="{{ asset($cssFile) }}">
    <link rel="stylesheet" href="{{ asset('static/css/reset.css') }}">
</head>
<body>
    <div id="root"></div>
    <script>
        window.Laravel = {
            csrfToken: '{{ csrf_token() }}'
        };
    </script>
    <script src="{{ asset($jsFile) }}"></script>
</body>
</html>