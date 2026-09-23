@php $labels = ['Select Owner', 'Select Pet', ($type === 'vaccinations' ? 'Vaccination' : 'Deworming').' Details', 'Review and Save']; @endphp
<ol class="row g-2 list-unstyled mb-4" aria-label="Record creation steps">
    @foreach ($labels as $index => $label)
        <li class="col-6 col-lg-3">
            <div class="rounded border p-3 h-100 {{ $step === $index + 1 ? 'bg-white border-primary fw-semibold' : 'text-muted' }}" @if ($step === $index + 1) aria-current="step" @endif>
                <span class="me-1">{{ $index + 1 }}.</span> {{ $label }}
            </div>
        </li>
    @endforeach
</ol>
