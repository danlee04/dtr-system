{{-- Beside the photo on their profile: their name, and their position and office, as on HR's page of them. --}}
<div class="dtr-profile-identity">
    <p class="dtr-profile-identity-name">{{ $employee->full_name }}</p>
    @if (filled($employee->position) || $employee->office)
        <p class="dtr-person-role">{{ collect([$employee->position, $employee->office?->name])->filter()->implode(' · ') }}</p>
    @endif
    <p class="dtr-profile-identity-hint">Click the photo to change it.</p>
</div>
