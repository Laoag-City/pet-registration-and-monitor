@php
    $originalSpecies = $pet->species ?? '';
    $speciesSelection = old('species', \App\Support\PetSpecies::selection($originalSpecies));
    $specifiedSpecies = old('species_other', \App\Support\PetSpecies::selection($originalSpecies) === 'Others' ? $originalSpecies : '');
@endphp
<div class="col-md-6">
    <label for="species" class="form-label">Species *</label>
    <select id="species" name="species" class="form-select @error('species') is-invalid @enderror" required onchange="const other=this.value==='Others'; document.getElementById('species-other-group').hidden=!other; document.getElementById('species_other').required=other; document.getElementById('species_other').disabled=!other;">
        <option value="">Select Species</option>
        @foreach(['Canine', 'Feline', 'Others'] as $option)<option value="{{ $option }}" @selected($speciesSelection === $option)>{{ $option }}</option>@endforeach
    </select>
    @error('species')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-6" id="species-other-group" @if($speciesSelection !== 'Others') hidden @endif>
    <label for="species_other" class="form-label">Specify Species *</label>
    <input id="species_other" name="species_other" value="{{ $specifiedSpecies }}" maxlength="60" class="form-control @error('species_other') is-invalid @enderror" @required($speciesSelection === 'Others') @disabled($speciesSelection !== 'Others')>
    @error('species_other')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
