<?php

namespace App\Filament\Pages;

use App\Actions\Projects\ManageProject;
use App\Models\Project;
use App\Support\ActionFeedback;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

final class Projects extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.projects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function canAccess(): bool
    {
        return OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_projects', true);
    }

    public function table(Table $table): Table
    {
        abort_unless(self::canAccess(), 403);

        return $table->query(Project::query())->columns([TextColumn::make('title')->searchable()->sortable(), TextColumn::make('project_date')->date()->sortable(), TextColumn::make('completion_status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->badge(), TextColumn::make('publication')->label(__('Publication'))->state(fn (Project $record): string => $record->completion_status === 'Completed' && $record->published_at?->lte(now()) && ! $record->trashed() ? __('Published') : __('Unpublished'))->badge(), TextColumn::make('published_at')->dateTime()->placeholder(__('Unpublished'))])
            ->filters([TrashedFilter::make()])->recordActions([
                Action::make('edit')->modalSubmitActionLabel(__('Save Project'))->schema($this->fields())->fillForm(fn (Project $record) => [...$record->only(['title', 'description', 'project_date', 'completion_status']), 'publish' => $record->completion_status === 'Completed' && $record->published_at !== null])->visible(fn (Project $record) => ! $record->trashed())->action(fn (Project $record, array $data) => $this->save($record->id, $data)),
                Action::make('archive')->color('danger')->requiresConfirmation()->visible(fn (Project $record) => ! $record->trashed())->action(fn (Project $record) => ActionFeedback::after('Project Archived Successfully!', fn () => app(ManageProject::class)->archive($record->id, false, app(AuthenticatedActor::class)->user()))),
                Action::make('restore')->requiresConfirmation()->visible(fn (Project $record) => $record->trashed())->action(fn (Project $record) => ActionFeedback::after('Project Restored Successfully!', fn () => app(ManageProject::class)->archive($record->id, true, app(AuthenticatedActor::class)->user()))),
            ]);
    }

    private function fields(): array
    {
        return [TextInput::make('title')->required()->maxLength(160), Textarea::make('description')->required()->maxLength(10000), DatePicker::make('project_date')->required()->maxDate(fn (Get $get) => $get('completion_status') === 'Planned' ? null : today()),
            Select::make('completion_status')->options(array_combine(['Planned', 'In Progress', 'Completed'], ['Planned', 'In Progress', 'Completed']))->default('Planned')->required()->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($state !== 'Completed') {
                        $set('publish', false);
                    }
                    $set('confirm_unpublish', false);
                }),
            Toggle::make('publish')->default(false)->disabled(fn (Get $get): bool => $get('completion_status') !== 'Completed')->dehydrated()
                ->helperText(__('Planned and In Progress projects are saved privately. Only Completed projects can be published.')),
            Checkbox::make('confirm_unpublish')->label(__('I confirm that saving this unfinished project will remove it from the public website.'))
                ->visible(fn (Get $get, ?Project $record): bool => $record?->published_at !== null && $get('completion_status') !== 'Completed')->required()->accepted(),
            FileUpload::make('images')->multiple()->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxFiles(5)->maxSize(15360)->helperText(__('Up to five images, 15 MB per image.'))->storeFiles(false)];
    }

    private function save(?int $id, array $data): void
    {
        $images = $data['images'] ?? [];
        unset($data['images']);
        try {
            app(ManageProject::class)->save($id, $data, $images, app(AuthenticatedActor::class)->user());
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('Please review the form'))->body(collect($exception->errors())->flatten()->first())->send();
            $path = $this->getMountedActionSchema()?->getStatePath();
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => [($path ? $path.'.' : '').$field => $messages])->all());
        }
        Notification::make()->title(__($id === null ? 'Project Created Successfully!' : 'Project Updated Successfully!'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label(__('Add project'))->modalSubmitActionLabel(__('Save Project'))->schema($this->fields())->action(fn (array $data) => $this->save(null, $data))];
    }
}
