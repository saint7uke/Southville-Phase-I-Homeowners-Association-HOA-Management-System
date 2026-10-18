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

        @if (in_array(auth('homeowner')->user()?->homeowner?->ownership_type, ['Owner', 'Co-owner'], true))
            <section aria-labelledby="my-tenants-heading" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-4">
                    <h2 id="my-tenants-heading" class="text-base font-semibold">My tenants</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Tenants currently linked to your registered property. Contact the HOA to correct a relationship.</p>
                </div>

                @if ($tenantSummaries === [])
                    <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-400">No tenants are currently linked to your profile.</p>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($tenantSummaries as $tenant)
                            <article class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-semibold text-gray-950 dark:text-white">{{ $tenant['name'] }}</h3>
                                    <span class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">{{ $tenant['status'] }}</span>
                                </div>
                                <dl class="mt-4 grid gap-2 text-sm">
                                    <div><dt class="text-gray-500 dark:text-gray-400">Contact</dt><dd class="font-medium">{{ $tenant['contact'] }}</dd></div>
                                    <div><dt class="text-gray-500 dark:text-gray-400">Lease period</dt><dd class="font-medium">{{ $tenant['lease_start'] }} – {{ $tenant['lease_end'] }}</dd></div>
                                </dl>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @php($profileErrors = collect($errors->getMessages())->filter(fn ($messages, $field) => str_starts_with($field, 'profileData.')))
        <form
            wire:submit="saveProfile"
            aria-label="Edit homeowner profile"
            class="grid gap-4"
            data-profile-validation-form
            data-availability-url="{{ route('homeowner.profile.availability') }}"
            novalidate
        >
            @if ($profileErrors->isNotEmpty())
                <div class="hoa-validation-summary" data-profile-error-summary role="alert" tabindex="-1">
                    <strong>Please correct the following profile fields.</strong>
                    <ul>
                        @foreach ($profileErrors as $messages)
                            @foreach ($messages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif
            {{ $this->profileForm }}
            <div>
                <x-filament::button type="submit" data-profile-submit wire:loading.attr="disabled" wire:target="saveProfile">
                    <span wire:loading.remove wire:target="saveProfile">Save profile</span>
                    <span wire:loading wire:target="saveProfile" role="status">Saving profile…</span>
                </x-filament::button>
            </div>
        </form>

        @php($passwordErrors = collect($errors->getMessages())->filter(fn ($messages, $field) => str_starts_with($field, 'passwordData.')))
        <form wire:submit="savePassword" aria-label="Change password" class="grid gap-4" data-password-validation-form novalidate>
            @if ($passwordErrors->isNotEmpty())
                <div class="hoa-validation-summary" data-password-error-summary role="alert" tabindex="-1">
                    <strong>Please correct the following password fields.</strong>
                    <ul>
                        @foreach ($passwordErrors as $messages)
                            @foreach ($messages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif
            {{ $this->passwordForm }}
            <div>
                <x-filament::button type="submit" color="danger" data-password-submit wire:loading.attr="disabled" wire:target="savePassword">
                    <span wire:loading.remove wire:target="savePassword">Change password and sign out</span>
                    <span wire:loading wire:target="savePassword" role="status">Changing password…</span>
                </x-filament::button>
            </div>
        </form>
    </div>

    @vite('resources/js/homeowner-profile-validation.js')
</x-filament-panels::page>
