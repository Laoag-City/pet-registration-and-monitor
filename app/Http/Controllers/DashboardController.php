<?php

namespace App\Http\Controllers;

use App\Models\DewormingRecord;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Http\Request;
use Illuminate\View\View;
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return match ($user->role) {
            User::ROLE_ADMIN => $this->adminDashboard($user),
            User::ROLE_STAFF => $this->staffDashboard($user),
            default => $this->ownerDashboard($user),
        };
    }

    private function ownerDashboard(User $user): View
    {
        $pets = Pet::query()
            ->where('owner_email', $user->email)
            ->orderBy('name')
            ->get();
        $petIds = $pets->pluck('id');
        return view('dashboard', [
            'user' => $user,
            'pets' => $pets,
            'petCount' => $pets->count(),
            'vaccinationCount' => Vaccination::whereIn('pet_id', $petIds)->count(),
            'dewormingCount' => DewormingRecord::whereIn('pet_id', $petIds)->count(),
            'careSchedules' => app(\App\Services\PetCareReminders::class)->schedules($user)
                ->filter(fn ($schedule) => $schedule['days'] <= 30),
        ]);
    }

    private function staffDashboard(User $user): View
    {
        return view('staff.dashboard', app(\App\Services\StaffDashboardAnalytics::class)->data($user));
    }

    private function adminDashboard(User $user): View
    {
        return view('admin.dashboard', $this->systemStats($user));
    }

    private function systemStats(User $user): array
    {
        return [
            'user' => $user,
            'userCount' => User::where('role', User::ROLE_USER)->count(),
            'staffCount' => User::whereIn('role', [User::ROLE_STAFF, User::ROLE_ADMIN])->count(),
            'petCount' => Pet::count(),
            'vaccinationCount' => Vaccination::count(),
            'dewormingCount' => DewormingRecord::count(),
            'recentPets' => Pet::query()->latest()->take(6)->get(),
            'recentUsers' => User::where('role', User::ROLE_USER)->latest()->take(6)->get(),
        ];
    }
}
