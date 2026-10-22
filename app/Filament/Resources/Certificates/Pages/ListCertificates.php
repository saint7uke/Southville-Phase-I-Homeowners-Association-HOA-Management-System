<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Services\ButawReceiptSource;
use App\Services\CertificateTemplates;
use App\Services\CohabitationCertificateSource;
use App\Services\PaymentReceiptResolver;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class ListCertificates extends ListRecords
{
    use MapsDomainValidation;

    protected static string $resource = CertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New document'))->icon('heroicon-o-plus')
                ->extraAttributes(['class' => 'hoa-receipt-primary-action']),
            $this->monthlyReceiptsAction(),
            ActionGroup::make($this->documentSetupActions())
                ->label(__('Document setup'))->icon('heroicon-o-cog-6-tooth')->button()->outlined()->color('gray')
                ->visible(fn () => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_settings', true)),
        ];
    }

    protected function documentSetupActions(): array
    {
        return [
            Action::make('reviewButawSource')->label(__('Review original Resibo Butaw'))->url(route('certificates.butaw.reference'))->openUrlInNewTab()
                ->visible(fn () => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_settings', true) && OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_payments', true)),
            Action::make('confirmButawSource')->label(__('Confirm original Resibo Butaw source'))->requiresConfirmation()
                ->visible(fn () => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_settings', true) && OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_payments', true) && ! app(ButawReceiptSource::class)->isConfirmed())
                ->modalDescription(__('One-time setup for this document version. You do not need to repeat this for each certificate or receipt.'))
                ->modalSubmitActionLabel(__('Confirm source'))
                ->schema([Toggle::make('reviewed')->label(__('I reviewed the original uploaded PDF and its blank-field mapping.'))->required()->accepted(),
                    Textarea::make('note')->label(__('Approval evidence / note'))->required()->minLength(10)->maxLength(2000)])
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(ButawReceiptSource::class)->approve($data, app(AuthenticatedActor::class)->user()));
                    Notification::make()->success()->title(__('Original receipt source confirmed.'))->send();
                }),
            Action::make('reviewResidencySource')->label(__('Review uploaded Residency document'))->url(route('certificates.residency-reference'))->openUrlInNewTab()
                ->visible(fn () => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_settings', true)),
            Action::make('confirmResidencySource')->label(__('Confirm official Residency source'))->requiresConfirmation()
                ->visible(fn () => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_settings', true) && ! app(CertificateTemplates::class)->isResidencySourceConfirmed())
                ->modalDescription(__('One-time setup for this document version. You do not need to repeat this for each certificate or receipt.').' '.__('The original document and its printed 2026 year remain unchanged. Only confirm if the HOA authorizes JOSEPHINE ALARZAR / SHOAi PRESIDENT.'))
                ->modalSubmitActionLabel(__('Confirm source'))
                ->schema([
                    Toggle::make('reviewed')->label(__('I reviewed the uploaded document and its original layout.'))->required()->accepted(),
                    Toggle::make('confirmed')->label(__('The HOA authorizes the signatory printed on this document.'))->required()->accepted(),
                    Textarea::make('note')->label(__('Approval evidence / note'))->required()->minLength(10)->maxLength(2000),
                ])->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(CertificateTemplates::class)->confirmResidencySource($data, app(AuthenticatedActor::class)->user()));
                    Notification::make()->success()->title(__('Official Residency source confirmed'))->send();
                }),
            Action::make('setupCohabitationSource')->label(fn (): string => __('Original Cohabitation document').' — '.__(app(CohabitationCertificateSource::class)->setupStatus()['label']))
                ->modalHeading(__('Original Cohabitation document'))
                ->modalSubmitAction(fn (Action $action): Action|bool => app(CohabitationCertificateSource::class)->setupStatus()['state'] === 'ready'
                    ? $action->extraAttributes(['class' => 'hoa-receipt-primary-action']) : false)
                ->visible(fn () => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_settings', true)
                    && ! app(CohabitationCertificateSource::class)->isConfirmed())
                ->modalSubmitActionLabel(__('Confirm source'))
                ->modalCancelActionLabel(__('Close'))
                ->schema([
                    Placeholder::make('source_notice')->hiddenLabel()->content(fn () => new HtmlString(view('filament.resources.certificates.cohabitation-source-status', [
                        'status' => app(CohabitationCertificateSource::class)->setupStatus(), 'showReviewLink' => true,
                    ])->render())),
                    Placeholder::make('printed_signatory')->label(__('Printed signatory'))->content(function (): string {
                        $layout = app(CohabitationCertificateSource::class)->manifest();

                        return $layout['printed_signatory_name'].' / '.$layout['printed_signatory_title'].' / '.$layout['printed_year'];
                    })->visible(fn (): bool => app(CohabitationCertificateSource::class)->setupStatus()['state'] === 'ready'),
                    Toggle::make('reviewed')->label(__('I reviewed the uploaded document and its original layout.'))->accepted()->required()
                        ->visible(fn (): bool => app(CohabitationCertificateSource::class)->setupStatus()['state'] === 'ready'),
                    Toggle::make('confirmed')->label(__('The HOA authorizes the signatory printed on this document.'))->accepted()->required()
                        ->visible(fn (): bool => app(CohabitationCertificateSource::class)->setupStatus()['state'] === 'ready'),
                    Textarea::make('note')->label(__('Approval evidence / note'))->required()->minLength(10)->maxLength(2000)
                        ->visible(fn (): bool => app(CohabitationCertificateSource::class)->setupStatus()['state'] === 'ready'),
                ])->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(CohabitationCertificateSource::class)->confirm($data, app(AuthenticatedActor::class)->user()));
                    Notification::make()->success()->title(__('Original Cohabitation source confirmed.'))->send();
                }),
        ];
    }

    protected function monthlyReceiptsAction(): Action
    {
        return Action::make('findPaymentReceipt')->label(__('Monthly receipts'))->icon('heroicon-o-document-text')->color('gray')->outlined()
            ->visible(fn () => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_payments'))
            ->modalHeading(__('Saved monthly dues receipts'))
            ->schema([
                Placeholder::make('receipt_history_description')->label(__('About monthly receipts'))
                    ->content(__('This section lists receipt PDFs that have already been generated. Recording a payment does not automatically create a Resibo Butaw PDF.')),
                Placeholder::make('receipt_history_empty')->label(__('No generated receipts yet'))
                    ->visible(fn (): bool => ! $this->hasSavedMonthlyReceipts())
                    ->content(fn () => ! app(ButawReceiptSource::class)->schemaReady()
                        ? __(ButawReceiptSource::SETUP_MESSAGE)
                        : new HtmlString('<p>'.e(__('Record or verify an eligible monthly Cash payment, then preview and generate its receipt from New document.')).'</p><p><a href="'.e(CertificateResource::getUrl('create', ['document' => 'butaw'])).'">'.e(__('Create monthly dues receipt')).'</a></p>')),
                Select::make('document_id')->label(__('Monthly dues receipt'))->searchable()->live()->options(function (): array {
                    OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');
                    if (! Schema::hasTable('payment_documents')) {
                        return [];
                    }

                    return app(PaymentReceiptResolver::class)->historyQuery()->limit(100)->get()
                        ->mapWithKeys(fn ($document) => [$document->id => ($document->snapshot['or_number'] ?? $document->id).' | '.($document->snapshot['payer_name'] ?? '').' | '.($document->snapshot['values']['month'] ?? '')
                            .' | '.($document->payment?->latestReceiptDocument?->id === $document->id ? __('Current receipt') : __('Historical version')).' | '.$document->created_at->format('Y-m-d H:i:s')])->all();
                })->visible(fn (): bool => $this->hasSavedMonthlyReceipts()),
                Placeholder::make('document_links')->label(__('Saved PDF'))->content(fn (Get $get) => $get('document_id') ? new HtmlString('<a target="_blank" rel="noopener" href="'.e(route('certificates.butaw.download', ['paymentDocument' => (int) $get('document_id'), 'view' => 1])).'">'.e(__('View / Print')).'</a> &nbsp; <a href="'.e(route('certificates.butaw.download', (int) $get('document_id'))).'">'.e(__('Download')).'</a>') : __('Select a receipt.'))
                    ->visible(fn (): bool => $this->hasSavedMonthlyReceipts()),
            ])->modalSubmitAction(false)->modalCancelActionLabel(__('Close'));
    }

    protected function hasSavedMonthlyReceipts(): bool
    {
        OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');

        return Schema::hasTable('payment_documents') && app(PaymentReceiptResolver::class)->historyQuery()->exists();
    }
}
