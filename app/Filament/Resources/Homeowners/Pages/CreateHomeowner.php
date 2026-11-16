<?php

namespace App\Filament\Resources\Homeowners\Pages;

use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\Homeowners\HomeownerResource;
use App\Support\PropertyAddress;
use App\Support\PropertyPhase;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class CreateHomeowner extends CreateRecord
{
    use HasSpecificFeedback;
    use MapsDomainValidation;

    protected static string $resource = HomeownerResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['house_number'] = null;
        $data['street'] = null;
        $data['phase'] = PropertyAddress::DEFAULT_PHASE;
        Validator::make($data, ['phase_suffix' => PropertyPhase::rules()])->validate();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return $this->runValidatedDomainAction(fn (): Model => parent::handleRecordCreation($data), 'data');
    }
}
