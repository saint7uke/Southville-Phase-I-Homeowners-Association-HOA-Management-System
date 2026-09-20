<x-filament-panels::page>
    <div class="grid gap-6">
        @if (auth('homeowner')->user()?->homeowner?->profile_photo)
            <section aria-labelledby="current-photo-heading" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 id="current-photo-heading" class="mb-4 text-base font-semibold">Current profile photo</h2>
                <img
                    src="{{ route('homeowner.profile-photo', auth('homeowner')->user()->homeowner) }}"
                    alt="Profile photo of {{ auth('homeowner')->user()->full_name }}"
                    width="160"
                    height="160"
                    class="h-40 w-40 rounded-full object-cover"
                >
            </section>
        @endif

        <form wire:submit="saveProfile" aria-label="Edit homeowner profile" class="grid gap-4">
            {{ $this->profileForm }}
            <div>
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveProfile">
                    <span wire:loading.remove wire:target="saveProfile">Save profile</span>
                    <span wire:loading wire:target="saveProfile" role="status">Saving profile…</span>
                </x-filament::button>
            </div>
        </form>

        <form wire:submit="savePassword" aria-label="Change password" class="grid gap-4">
            {{ $this->passwordForm }}
            <div>
                <x-filament::button type="submit" color="danger" wire:loading.attr="disabled" wire:target="savePassword">
                    <span wire:loading.remove wire:target="savePassword">Change password and sign out</span>
                    <span wire:loading wire:target="savePassword" role="status">Changing password…</span>
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
