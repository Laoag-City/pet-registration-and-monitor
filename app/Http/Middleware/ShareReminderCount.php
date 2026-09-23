<?php

namespace App\Http\Middleware;

use App\Services\PetCareReminders;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareReminderCount
{
    public function handle(Request $request, Closure $next): Response
    {
        // Read once in authenticated request middleware; rendering Blade never generates reminders.
        $count = $request->user()?->isOwner()
            ? app(PetCareReminders::class)->activeNotifications($request->user())->whereNull('read_at')->count()
            : 0;
        View::share('careUnreadCount', $count);

        return $next($request);
    }
}
