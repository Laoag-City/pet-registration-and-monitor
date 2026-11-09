<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="description" content="PawID, a service of the Office of the City Veterinarian, City Government of Laoag, helps pet owners register pets, manage medical records, and track QR-based pet identification." />
        <title>@yield('title', 'PawID')</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-body">
        @include('components.navbar')

        <main>
            @yield('content')
        </main>

        @include('components.footer')

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const toggle = document.querySelector('[data-nav-toggle]');
                const menu = document.querySelector('[data-nav-menu]');

                if (toggle && menu) {
                    toggle.addEventListener('click', function () {
                        const isOpen = menu.classList.toggle('is-open');
                        toggle.setAttribute('aria-expanded', String(isOpen));
                    });
                }
            });
        </script>
    </body>
</html>
