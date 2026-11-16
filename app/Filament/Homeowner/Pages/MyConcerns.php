<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Pages;

use App\Actions\Complaints\CreateComplaint;
use App\Actions\ContactMessages\CreateResidentInquiry;
use App\Services\ResidentConcerns;
use App\Validation\CaseAttachmentRules;
use App\Validation\ComplaintSubmissionValidator;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Livewire\WithPagination;

final class MyConcerns extends Page
{
    use WithPagination;

    protected string $view = 'filament.homeowner.pages.my-concerns';

    protected static ?string $navigationLabel = 'My Concerns';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Requests & Concerns';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public static function canAccess(): bool
    {
        return CreateResidentInquiry::eligible(Filament::auth()->user());
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    protected function getViewData(): array
    {
        $service = app(ResidentConcerns::class);
        $actor = Filament::auth()->user();

        return [
            'concerns' => $service->listing($actor, $this->typeFilter, $this->statusFilter, $this->getPage()),
        ];
    }

    public function viewConcernAction(): Action
    {
        return Action::make('viewConcern')->label(__('View details'))
            ->modalHeading(new HtmlString('<span data-i18n="View details">'.e(__('View details')).'</span>'))->modalWidth('2xl')
            ->mountUsing(function (array $arguments): void {
                abort_unless(is_string($arguments['concern'] ?? null), 404);
                app(ResidentConcerns::class)->detail(Filament::auth()->user(), (string) ($arguments['concern'] ?? ''));
            })
            ->modalContent(fn (array $arguments) => view('filament.homeowner.concern-details', [
                'detail' => app(ResidentConcerns::class)->detail(Filament::auth()->user(), (string) ($arguments['concern'] ?? '')),
            ]))
            ->modalSubmitAction(false)->modalCancelActionLabel(__('Close'))
            ->modalCancelAction(fn (Action $action) => $action->label(new HtmlString('<span data-i18n="Close">'.e(__('Close')).'</span>')))
            ->stickyModalHeader()->stickyModalFooter()->closeModalByEscaping()->modalAutofocus();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('submitConcern')->label(new HtmlString('<span data-i18n="Submit Concern">'.e(__('Submit Concern')).'</span>'))->modalWidth('2xl')->schema([
            Select::make('kind')->label(__('Type'))->options(['Complaint' => __('Complaint'), 'Inquiry' => __('Inquiry')])->default('Complaint')->required()->live(),
            Select::make('category')->options(array_combine(ComplaintSubmissionValidator::CATEGORIES, array_map(__(...), ComplaintSubmissionValidator::CATEGORIES)))
                ->visible(fn (Get $get) => $get('kind') === 'Complaint')->required(fn (Get $get) => $get('kind') === 'Complaint')->live()
                ->afterStateUpdated(fn (Set $set) => $set('category_other', null)),
            TextInput::make('category_other')->label(__('Please Specify'))->maxLength(160)
                ->visible(fn (Get $get) => $get('kind') === 'Complaint' && $get('category') === 'Others')
                ->required(fn (Get $get) => $get('kind') === 'Complaint' && $get('category') === 'Others'),
            TextInput::make('subject')->maxLength(160)->visible(fn (Get $get) => $get('kind') === 'Inquiry')->required(fn (Get $get) => $get('kind') === 'Inquiry'),
            Textarea::make('message')->label(__('Description'))->required()->minLength(fn (Get $get) => $get('kind') === 'Complaint' ? 20 : 1)->maxLength(5000),
            FileUpload::make('attachments')->label(__('Supporting attachments'))->visible(fn (Get $get) => $get('kind') === 'Complaint')
                ->multiple()->maxFiles(3)->storeFiles(false)->acceptedFileTypes(CaseAttachmentRules::MIME_TYPES)->maxSize(5120),
        ])->action(function (array $data): void {
            $actor = Filament::auth()->user();
            abort_unless(self::canAccess(), 403);
            RateLimiter::attempt('resident-concern:'.$actor->id, 5, fn () => true, 60)
                || abort(429, __('Too many requests. Please try again later.'));
            if ($data['kind'] === 'Complaint') {
                app(CreateComplaint::class)->handle($actor->homeowner, [
                    'category' => $data['category'], 'category_other' => $data['category_other'] ?? null,
                    'subject' => $data['category'] === 'Others' ? trim($data['category_other'] ?? '') : $data['category'],
                    'description' => $data['message'], 'priority' => 'Medium',
                ], $data['attachments'] ?? [], $actor);
                $title = 'Complaint Submitted Successfully!';
            } else {
                app(CreateResidentInquiry::class)->handle($actor, $data);
                $title = 'Inquiry Submitted Successfully!';
            }
            $this->resetPage();
            Notification::make()->success()->title(__($title))->send();
        })];
    }
}
