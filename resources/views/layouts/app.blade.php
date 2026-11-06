<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <title>@yield('title', config('app.name', 'PawID'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --ink: #122c4f; --ink-strong: #0d2340; --muted: #607089; --canvas: #f5f8fc; --line: rgba(18, 44, 79, 0.1); --brand: #122c4f; --brand-soft: #eaf3ff; --teal: #1f9d9a; --shadow-soft: 0 12px 24px rgba(15, 35, 61, 0.08); }
        body { background: var(--canvas); color: var(--ink-strong); font-family: 'Inter', sans-serif; }
        .app-sidebar { width: 252px; min-height: 100vh; background: #fff; border-right: 1px solid var(--line); }
        .brand-mark { width: 42px; height: 42px; display: grid; place-items: center; color: #fff; background: var(--brand); border-radius: 12px; font-weight: 800; overflow: hidden; }
        .brand-mark img { width: 100%; height: 100%; object-fit: cover; }
        .brand-title { color: var(--ink); font-size: 1rem; font-weight: 700; }
        .brand-subtitle, .sidebar-label { color: var(--muted); font-size: .72rem; }
        .sidebar-label { letter-spacing: .08em; font-weight: 700; text-transform: uppercase; }
        .app-nav .nav-link { display: flex; align-items: center; gap: .75rem; padding: .68rem .8rem; color: #53637b; border-radius: 8px; font-weight: 500; }
        .app-nav .nav-link:hover { color: var(--brand); background: #f4f7fc; }
        .app-nav .nav-link.active { color: var(--brand); background: var(--brand-soft); font-weight: 700; }
        .app-nav .nav-link i { width: 1.1rem; text-align: center; }
        .app-header { min-height: 76px; background: rgba(255,255,255,.94); border-bottom: 1px solid var(--line); }
        .content-wrap { max-width: 1440px; margin: 0 auto; }
        .card-light { background: #fff; border: 1px solid var(--line); border-radius: 10px; box-shadow: 0 3px 12px rgba(23,43,77,.035); }
        .page-kicker { color: var(--brand); font-size: .76rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .page-title { color: var(--ink); font-size: clamp(1.45rem, 2vw, 1.8rem); font-weight: 700; }
        .metric-label { color: var(--muted); font-size: .78rem; font-weight: 600; }
        .metric-value { color: var(--ink); font-size: 1.5rem; font-weight: 700; line-height: 1.15; }
        .table thead th { padding: .85rem 1rem; color: var(--muted); background: #f8faff; border-bottom-color: var(--line); font-size: .72rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
        .table tbody td { padding: 1rem; border-bottom-color: #edf0f5; color: #3d4d64; vertical-align: middle; }
        .table tbody tr:last-child td { border-bottom: 0; }
        .record-name { color: var(--ink); font-weight: 700; }
        .record-meta { color: var(--muted); font-size: .8rem; }
        .status-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .34rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 700; white-space: nowrap; }
        .status-current { color: #087968; background: #e5f7f2; }
        .status-due { color: #946200; background: #fff5dc; }
        .status-overdue { color: #b43b46; background: #ffeaed; }
        .status-pending { color: #56657a; background: #eef1f5; }
        .btn-brand { color: #fff; background: var(--brand); border-color: var(--brand); }
        .btn-brand:hover { color: #fff; background: #0d2340; border-color: #0d2340; }
        .user-chip { width: 36px; height: 36px; display: grid; place-items: center; background: var(--brand-soft); color: var(--brand); font-weight: 800; font-size: .8rem; border-radius: 999px; flex: 0 0 auto; }
        .mobile-menu { width: 290px; }
        .qr-preview { width: min(100%, 260px); aspect-ratio: 1; display: grid; place-items: center; background: #fff; border: 1px dashed #cfd8e6; border-radius: 8px; }
        .qr-preview i { color: var(--brand); font-size: 4.5rem; }
        .qr-detail { min-width: 0; }
        .detail-label { color: var(--muted); font-size: .72rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    </style>
    @stack('styles')
</head>
    <body class="@yield('body-class')">
    @php
        $authUser = auth()->user();
        $canManage = $authUser ? $authUser->canManageRecords() : false;
        $initials = $authUser ? collect(explode(' ', trim($authUser->name ?? '')))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('') : '';
    @endphp
    @php
        $navItems = $authUser?->isAdmin() ? [
            ['path' => 'admin/dashboard', 'route' => 'admin.dashboard', 'active' => ['dashboard', 'admin.dashboard', 'staff.dashboard'], 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
            ['path' => 'admin/users', 'route' => 'admin.users', 'active' => ['admin.users', 'admin.users.create', 'admin.users.edit', 'staff.users', 'staff.users.show'], 'label' => 'User Management', 'icon' => 'bi-people'],
            ['path' => 'admin/record-archive', 'route' => 'admin.record-archive', 'active' => ['admin.record-archive', 'admin.record-audit'], 'label' => 'Record Archive', 'icon' => 'bi-archive'],
            ['path' => 'staff/pets', 'route' => 'staff.pets', 'active' => ['staff.pets', 'staff.pets.edit', 'staff.pets.remove', 'pets.index', 'pets.show'], 'label' => 'All Pets', 'icon' => 'bi-collection'],
            ['path' => 'staff/pets/create', 'route' => 'staff.pets.create', 'active' => ['staff.pets.create'], 'label' => 'Register Pet', 'icon' => 'bi-plus-circle'],
            ['path' => 'vaccinations', 'route' => 'vaccinations.index', 'active' => ['vaccinations.*'], 'label' => 'Vaccinations', 'icon' => 'bi-shield-check'],
            ['path' => 'deworming', 'route' => 'deworming.index', 'active' => ['deworming.*'], 'label' => 'Deworming', 'icon' => 'bi-capsule'],
            ['path' => 'qrcodes', 'route' => 'qrcodes.index', 'active' => ['qrcodes.*'], 'label' => 'QR Codes', 'icon' => 'bi-qr-code'],
        ] : ($authUser?->isStaff() ? [
            ['path' => 'staff/dashboard', 'route' => 'staff.dashboard', 'active' => ['dashboard', 'staff.dashboard'], 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
            ['path' => 'staff/users', 'route' => 'staff.users', 'active' => ['staff.users', 'staff.users.show'], 'label' => 'User Management', 'icon' => 'bi-people'],
            ['path' => 'staff/pets', 'route' => 'staff.pets', 'active' => ['staff.pets', 'staff.pets.edit', 'staff.pets.remove', 'pets.index', 'pets.show'], 'label' => 'All Pets', 'icon' => 'bi-collection'],
            ['path' => 'staff/pets/create', 'route' => 'staff.pets.create', 'active' => ['staff.pets.create'], 'label' => 'Register Pet', 'icon' => 'bi-plus-circle'],
            ['path' => 'vaccinations', 'route' => 'vaccinations.index', 'active' => ['vaccinations.*'], 'label' => 'Vaccinations', 'icon' => 'bi-shield-check'],
            ['path' => 'deworming', 'route' => 'deworming.index', 'active' => ['deworming.*'], 'label' => 'Deworming', 'icon' => 'bi-capsule'],
            ['path' => 'qrcodes', 'route' => 'qrcodes.index', 'active' => ['qrcodes.*'], 'label' => 'QR Codes', 'icon' => 'bi-qr-code'],
        ] : [
            ['path' => 'dashboard', 'route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
            ['path' => 'mypets', 'route' => 'pets.index', 'active' => ['pets.index', 'pets.show'], 'label' => 'My Pets', 'icon' => 'bi-heart'],
            ['path' => 'profile', 'route' => 'profile.show', 'active' => ['profile.*'], 'label' => 'My Profile', 'icon' => 'bi-person-circle'],
            ['path' => 'notifications', 'route' => 'notifications.index', 'active' => ['notifications.*'], 'label' => 'Notifications', 'icon' => 'bi-bell'],
            ['path' => 'vaccinations', 'route' => 'vaccinations.index', 'active' => ['vaccinations.*'], 'label' => 'Vaccinations', 'icon' => 'bi-shield-check'],
            ['path' => 'deworming', 'route' => 'deworming.index', 'active' => ['deworming.*'], 'label' => 'Deworming', 'icon' => 'bi-capsule'],
            ['path' => 'qrcodes', 'route' => 'qrcodes.index', 'active' => ['qrcodes.*'], 'label' => 'QR Codes', 'icon' => 'bi-qr-code'],
        ]);
        if ($canManage) {
            $navItems[] = ['path' => 'staff/animal-health-monitoring', 'route' => 'staff.animal-health-monitoring.index', 'active' => ['staff.animal-health-monitoring.*'], 'label' => 'Animal Health Monitoring', 'icon' => 'bi-clipboard2-pulse'];
            $navItems[] = ['path' => 'staff/routine-services', 'route' => 'staff.routine-services.index', 'active' => ['staff.routine-services.*'], 'label' => 'Routine Services', 'icon' => 'bi-clipboard2-check'];
        }
    @endphp
    <div class="d-flex">
        <aside class="app-sidebar d-none d-lg-flex flex-column justify-content-between p-3">
            <div>
                <a class="d-flex align-items-center gap-2 mb-5 text-decoration-none" href="/dashboard"><span class="brand-mark"><img src="{{ asset('images/city vet.jpg') }}" alt=""></span><span><span class="brand-title d-block">PawID</span><span class="brand-subtitle d-block">Pet Records</span></span></a>
                <div class="sidebar-label px-2 mb-2">Workspace</div>
                <nav class="app-nav nav flex-column gap-1">
                    @foreach ($navItems as $item)
                        <a class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}"><i class="bi {{ $item['icon'] }}"></i>{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="d-flex align-items-center gap-2 pt-3 border-top"><span class="user-chip">{{ $initials }}</span><div class="text-truncate" style="min-width:0"><div class="small fw-semibold text-truncate">{{ $authUser?->name }}</div><div class="record-meta text-truncate">{{ $authUser?->email }}</div></div></div>
        </aside>
        <main class="flex-grow-1 min-vh-100" style="min-width:0">
            <header class="app-header px-3 px-md-4 d-flex align-items-center justify-content-end gap-3">
                <button class="btn btn-outline-secondary d-lg-none me-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNavigation" aria-label="Open navigation"><i class="bi bi-list"></i></button>
                @if ($authUser?->isOwner())
                    <a class="btn btn-outline-secondary btn-sm text-nowrap" href="{{ route('notifications.index') }}" aria-label="Notifications, {{ $careUnreadCount ?? 0 }} unread"><i class="bi bi-bell" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">Notifications</span><span class="badge bg-danger ms-1">{{ $careUnreadCount ?? 0 }}</span></a>
                @endif
                <div class="dropdown">
                    <button class="btn btn-light btn-sm border d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="user-chip">{{ $initials }}</span><span class="d-none d-md-inline fw-semibold text-truncate" style="max-width:160px">{{ $authUser?->name }}</span><i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-semibold text-truncate">{{ $authUser?->name }}</div>
                            <div class="record-meta text-truncate">{{ $authUser?->email }}</div>
                            @if ($authUser?->contact_number)<div class="record-meta">{{ $authUser->contact_number }}</div>@endif
                        </li>
                        @if ($authUser?->isOwner())<li><a class="dropdown-item" href="{{ route('profile.show') }}"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>@endif
                        <li><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button></form></li>
                    </ul>
                </div>
            </header>
            <div class="content-wrap p-3 p-md-4">
                @if (session('status'))<div class="alert alert-success d-flex align-items-center gap-2" role="alert"><i class="bi bi-check-circle-fill"></i><div>{{ session('status') }}</div></div>@endif
                @if (session('success'))<div class="alert alert-success d-flex align-items-center gap-2" role="alert"><i class="bi bi-check-circle-fill"></i><div>{{ session('success') }}</div></div>@endif
                @if (session('error'))<div class="alert alert-danger d-flex align-items-center gap-2" role="alert"><i class="bi bi-exclamation-triangle-fill"></i><div>{{ session('error') }}</div></div>@endif
                @if ($errors->any())<div class="alert alert-danger" role="alert"><div class="fw-semibold">Please review the form:</div><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </div>
        </main>
    </div>
    <div class="offcanvas offcanvas-start mobile-menu" tabindex="-1" id="mobileNavigation"><div class="offcanvas-header border-bottom"><div class="d-flex align-items-center gap-2"><span class="brand-mark"><img src="{{ asset('images/city vet.jpg') }}" alt=""></span><span class="brand-title">PawID</span></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div><div class="offcanvas-body"><div class="d-flex align-items-center gap-2 mb-4 p-3 rounded" style="background:var(--brand-soft)"><span class="user-chip">{{ $initials }}</span><div class="text-truncate" style="min-width:0"><div class="small fw-semibold text-truncate">{{ $authUser?->name }}</div><div class="record-meta text-truncate">{{ $authUser?->email }}</div></div></div><nav class="app-nav nav flex-column gap-1">@foreach ($navItems as $item)<a class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}"><i class="bi {{ $item['icon'] }}"></i>{{ $item['label'] }}</a>@endforeach</nav><form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button type="submit" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-box-arrow-right me-1"></i>Sign Out</button></form></div></div>
    @stack('scripts')
    @vite(['resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
