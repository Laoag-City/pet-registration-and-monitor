<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Support\PetQrReference;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;
use Linkxtr\QrCode\Facades\QrCode;

class QRCodeController extends Controller
{
    public function scan(?string $reference = null): Response
    {
        try {
            $pet = $reference ? PetQrReference::resolve($reference) : null;
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);

            return response()->view('qrcodes.scan-unavailable', [], 503)
                ->header('Cache-Control', 'no-store')
                ->header('Referrer-Policy', 'no-referrer')
                ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        $response = $pet
            ? response()->view('qrcodes.scan', ['ownerName' => $pet->owner->name, 'petName' => $pet->name])
            : response()->view('qrcodes.scan-not-found', [], 404);

        return $response->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function index(): View
    {
        $user = auth()->user();
        $pets = Pet::query()
            ->when(! $user->canManageRecords(), fn ($q) => $q->where('owner_email', $user->email))
            ->orderBy('name')
            ->get();
        return view('qrcodes', ['pets' => $pets]);
    }

    public function show(Pet $pet): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeOwner($pet);
        $qrSvg = QrCode::format('svg')
            ->size(180)
            ->margin(1)
            ->errorCorrection('M')
            ->generate(PetQrReference::url($pet));

        $pdf = Pdf::loadView('qrcodes.pdf', ['pet' => $pet, 'qrSvg' => $qrSvg]);

        return $pdf->download('pet-credential.pdf');
    }

    public function preview(Pet $pet): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeOwner($pet);

        return $this->qrResponse($pet, inline: true);
    }

    private function authorizeOwner(Pet $pet): void
    {
        $user = auth()->user();
        abort_unless($user->canManageRecords() || $pet->owner_email === $user->email, 403, 'You do not have access to this pet record.');
    }

    private function qrResponse(Pet $pet, bool $inline): \Symfony\Component\HttpFoundation\Response
    {
        $svg = QrCode::format('svg')
            ->size(300)
            ->margin(2)
            ->errorCorrection('M')
            ->generate(PetQrReference::url($pet));

        $response = response($svg)->header('Content-Type', 'image/svg+xml');

        if (! $inline) {
            $response->header('Content-Disposition', 'attachment; filename="qr-'.$pet->code.'.svg"');
        }

        return $response;
    }
}
