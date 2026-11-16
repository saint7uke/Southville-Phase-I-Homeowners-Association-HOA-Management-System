@props(['label', 'icon' => 'heroicon-o-eye', 'href' => null, 'labelKey' => null])
@php($tag = $href === null ? 'button' : 'a')
<{{ $tag }} {{ $attributes->class(['hoa-record-icon', 'hoa-system-action-button'])->merge(['data-hoa-record-action' => 'true', 'aria-label' => $label, 'data-i18n-aria-label' => $labelKey, 'title' => $label, 'data-hoa-record-tooltip' => $label, 'href' => $href, 'type' => $href === null ? 'button' : null]) }}>
    @svg($icon, '', ['aria-hidden' => 'true'])
    <span @if($labelKey) data-i18n="{{ $labelKey }}" @endif>{{ $label }}</span>
</{{ $tag }}>
