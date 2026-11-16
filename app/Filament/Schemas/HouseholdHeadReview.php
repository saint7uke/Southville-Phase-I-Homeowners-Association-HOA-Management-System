<?php

declare(strict_types=1);

namespace App\Filament\Schemas;

use App\Actions\Households\ManageHouseholdHead;
use App\Models\Household;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class HouseholdHeadReview
{
    public static function data(Household $household, ?int $selected = null): array
    {
        $selected ??= $household->head_member_id;
        $members = $household->members()->withTrashed()->orderBy('id')->get();
        $relationships = [];
        foreach ($members->filter(fn ($member): bool => ! $member->trashed() && $member->ended_at === null) as $member) {
            $relationships[$member->id] = $member->id === $selected ? 'Head'
                : (($member->relationship === 'Head' || $member->id === $household->head_member_id) ? null : $member->relationship);
        }

        return ['head_member_id' => $selected, 'head_revision' => app(ManageHouseholdHead::class)->revision($household, $members),
            'relationships' => $relationships, 'review_confirmed' => false];
    }

    public static function fields(Closure $household, ?Closure $enabled = null): array
    {
        $enabled ??= fn (): bool => true;

        return [
            Hidden::make('head_revision')->dehydrated($enabled),
            Select::make('head_member_id')->label(__('Household head'))
                ->options(fn (): array => $household()->members()->whereNull('ended_at')->whereIn('residency_status', ManageHouseholdHead::RESIDENCY)->pluck('full_name', 'id')->all())
                ->required($enabled)->disabled(fn (Get $get): bool => ! $enabled($get))->dehydrated($enabled)->live()
                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                    $roles = $get('relationships') ?? [];
                    foreach ($roles as $id => $role) {
                        $roles[$id] = (int) $id === (int) $state ? 'Head' : ($role === 'Head' ? null : $role);
                    }
                    $set('relationships', $roles);
                    $set('review_confirmed', false);
                }),
            Group::make()->visible($enabled)->schema(function (Get $get) use ($household): array {
                return $household()->members()->whereNull('ended_at')->orderBy('id')->get()->map(fn ($member) => Select::make('relationships.'.$member->id)->label(__('Relationship for :name', ['name' => $member->full_name]))
                    ->options((int) $get('head_member_id') === $member->id ? ['Head' => __('Head')]
                        : array_combine(ManageHouseholdHead::RELATIONSHIPS, array_map(__(...), ManageHouseholdHead::RELATIONSHIPS)))
                    ->required()->disabled((int) $get('head_member_id') === $member->id)->dehydrated(true)
                )->all();
            }),
            Checkbox::make('review_confirmed')->label(__('I reviewed every current member’s relationship to the selected head.'))
                ->accepted()->visible($enabled)->dehydrated($enabled),
        ];
    }
}
