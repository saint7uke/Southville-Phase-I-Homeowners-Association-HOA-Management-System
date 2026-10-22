<?php

namespace App\Filament\Pages;

use App\Actions\Projects\ManageProject;
use App\Models\Project;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function canAccess(): bool
    {
        return OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_projects', true);
    }

    public function table(Table $table): Table
    {
        abort_unless(self::canAccess(), 403);

        return $table->query(Project::query())->columns([TextColumn::make('title')->searchable()->sortable(), TextColumn::make('project_date')->date()->sortable(), TextColumn::make('completion_status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->badge(), TextColumn::make('published_at')->dateTime()->placeholder(__('Unpublished'))])
            ->filters([TrashedFilter::make()])->recordActions([
                Action::make('edit')->schema($this->fields())->fillForm(fn (Project $record) => [...$record->only(['title', 'description', 'project_date', 'completion_status']), 'publish' => $record->published_at !== null])->visible(fn (Project $record) => ! $record->trashed())->action(fn (Project $record, array $data) => $this->save($record->id, $data)),
                Action::make('archive')->color('danger')->requiresConfirmation()->visible(fn (Project $record) => ! $record->trashed())->action(fn (Project $record) => app(ManageProject::class)->archive($record->id, false, app(AuthenticatedActor::class)->user())),
                Action::make('restore')->requiresConfirmation()->visible(fn (Project $record) => $record->trashed())->action(fn (Project $record) => app(ManageProject::class)->archive($record->id, true, app(AuthenticatedActor::class)->user())),
            ]);
    }

    private function fields(): array
    {
        return [TextInput::make('title')->required()->maxLength(160), Textarea::make('description')->required()->maxLength(10000), DatePicker::make('project_date')->required()->maxDate(today()), Select::make('completion_status')->options(array_combine(['Planned', 'In Progress', 'Completed'], ['Planned', 'In Progress', 'Completed']))->default('Planned')->required(), Toggle::make('publish')->default(false)->helperText(__('Only completed projects are shown publicly.')), FileUpload::make('images')->multiple()->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxFiles(5)->maxSize(5120)->storeFiles(false)];
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
        Notification::make()->title(__('Project saved'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label(__('Add project'))->schema($this->fields())->action(fn (array $data) => $this->save(null, $data))];
    }
}
