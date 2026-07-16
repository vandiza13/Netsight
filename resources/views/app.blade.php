<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NETSIGHT — NOC Dynamic Traffic & Latency Inspection System">
    <meta name="theme-color" content="#0a0e1a">
    <meta name="color-scheme" content="dark">
    <title>Netsight by Vandiza Tech</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script>
        window.APP_CONFIG = {
            env: '{{ config('app.env') }}',
            showDemoButton: {{ env('SHOW_DEMO_BUTTON', false) ? 'true' : 'false' }}
        };
    </script>
    @vite(['resources/js/app.ts', 'resources/js/assets/main.css'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
