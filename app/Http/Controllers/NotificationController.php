<?php

namespace App\Http\Controllers;

use App\Services\PetCareReminders;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, PetCareReminders $reminders): View
    {
        $schedules = $reminders->schedules($request->user());

        return view('notifications.index', [
            'notifications' => $reminders->activeNotifications($request->user(), $schedules)->paginate(15),
            'schedules' => $schedules->keyBy('key'),
        ]);
    }

    public function read(Request $request, string $notification, PetCareReminders $reminders): RedirectResponse
    {
        $reminders->activeNotifications($request->user())->whereKey($notification)->firstOrFail()->markAsRead();

        return redirect()->route('notifications.index')->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request, PetCareReminders $reminders): RedirectResponse
    {
        $reminders->activeNotifications($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('success', 'All current notifications marked as read.');
    }
}
