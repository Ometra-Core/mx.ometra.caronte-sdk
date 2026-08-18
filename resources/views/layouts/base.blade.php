<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111317">
    <title>{{ data_get($branding ?? [], 'app_name', config('app.name')) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;650;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/caronte/css/custom.css') }}" rel="stylesheet">
    <style>
        :root {
            --caronte-accent: {{ data_get($branding ?? [], 'accent', '#f7c948') }};
            --caronte-auth-background: url("{{ data_get($branding ?? [], 'background_url', '/vendor/caronte/brand/bg.png') }}");
        }
    </style>
</head>

<body class="@yield('body_class', 'caronte-auth-shell')">
    <div class="caronte-shell">
        <main class="caronte-shell__content">
            @include('caronte::partials.messages')
            @yield('content')
        </main>

        <footer class="caronte-copyright">
            <span>&copy; {{ date('Y') }}</span>
            <img
                src="{{ data_get($branding ?? [], 'footer_logo_url', '/vendor/caronte/brand/ometra-logo.png') }}"
                alt="Ometra"
            >
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
