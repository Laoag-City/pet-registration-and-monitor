@extends('layouts.app')

@section('content')
  <div class="container-fluid">
    <div class="mb-4"><h1 class="h4 mb-1">Register a Pet</h1><p class="text-muted mb-0">Your pet details will be saved to the registry.</p></div>
    <div class="card card-light p-4">
      <h2 class="h6 mb-3">Pet information</h2>
      <form method="POST" action="/register-pet" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="name">Pet name *</label><input id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required autofocus>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
          <div class="col-md-6"><label class="form-label" for="species">Species *</label><select id="species" name="species" class="form-select @error('species') is-invalid @enderror" required><option value="">Select species</option><option value="Dog" @selected(old('species') === 'Dog')>Dog</option><option value="Cat" @selected(old('species') === 'Cat')>Cat</option><option value="Other" @selected(old('species') === 'Other')>Other</option></select>@error('species')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
          <div class="col-md-6"><label class="form-label" for="breed">Breed</label><input id="breed" name="breed" value="{{ old('breed') }}" class="form-control" placeholder="e.g. Golden Retriever"></div>
          <div class="col-md-6"><label class="form-label" for="sex">Sex *</label><select id="sex" name="sex" class="form-select @error('sex') is-invalid @enderror" required><option value="">Select sex</option><option value="Female" @selected(old('sex') === 'Female')>Female</option><option value="Male" @selected(old('sex') === 'Male')>Male</option></select>@error('sex')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
          <div class="col-md-6"><label class="form-label" for="date_of_birth">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" max="{{ now()->toDateString() }}" class="form-control @error('date_of_birth') is-invalid @enderror">@error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
          <div class="col-md-6"><label class="form-label" for="color">Color / markings</label><input id="color" name="color" value="{{ old('color') }}" class="form-control"></div>
          <div class="col-md-6">
            <label class="form-label" for="photo">Pet photo</label>
            <input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp"
              class="form-control @error('photo') is-invalid @enderror" onchange="var img=document.getElementById('photoPreview'); img.src = window.URL.createObjectURL(this.files[0]); img.style.display='inline-block';">
            @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <img id="photoPreview" alt="Pet photo preview" class="rounded"
              style="display:none; max-width: 180px; max-height: 180px; object-fit: cover; border: 1px solid var(--line);">
          </div>
        </div>
        <h2 class="h6 mt-4 mb-3">Owner information</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="owner_name">Owner name *</label><input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" class="form-control @error('owner_name') is-invalid @enderror" required>@error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
          <div class="col-md-6"><label class="form-label" for="owner_email">Owner email *</label><input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email') }}" class="form-control @error('owner_email') is-invalid @enderror" required>@error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
          <div class="col-md-6"><label class="form-label" for="owner_number">Owner contact number *</label><input id="owner_number" name="owner_number" value="{{ old('owner_number') }}" class="form-control @error('owner_number') is-invalid @enderror" required>@error('owner_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        </div>
        <div class="mt-4 d-flex justify-content-end gap-2"><a href="/register-pet" class="btn btn-outline-secondary">Clear</a><button type="submit" class="btn btn-brand">Register Pet</button></div>
      </form>
    </div>
  </div>
@endsection
