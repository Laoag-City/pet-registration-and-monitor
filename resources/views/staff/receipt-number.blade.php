<div class="col-12 col-md-6">
    <label for="receipt_number" class="form-label">Receipt Number</label>
    <input type="text" id="receipt_number" name="receipt_number" maxlength="100"
        value="{{ old('receipt_number', $pet->receipt_number ?? '') }}"
        placeholder="Enter receipt number (e.g., OR-2026-00125)"
        class="form-control @error('receipt_number') is-invalid @enderror">
    @error('receipt_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
