@php
    $simple = $action->isConfirmationRequired()
        && ! $this->mountedActionHasSchema(mountedAction: $action)
        && ! $action->hasModalContent()
        && ! $action->hasModalContentFooter()
        && count($action->getExtraModalFooterActions()) === 0;
    $id = "fi-{$this->getId()}-action-{$action->getNestingIndex()}";
@endphp
<div @if($simple)
    data-hoa-action-confirm="{{ json_encode([...\App\Support\ActionConfirmation::content($action),
        'id' => $id, 'component' => $this->getId(),
        'method' => $action->getLivewireCallMountedActionName(),
        'cancelParents' => $action->getParentActionsToCancelOnClose()]) }}"
    @endif>
    @include('hoa-filament-actions::action-modal', ['action' => $action])
</div>
