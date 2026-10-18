@props(['name', 'id' => null, 'value' => '1', 'checked' => false, 'required' => false])

<label {{ $attributes->class(['hoa-checkbox-label']) }}>
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $id ?? $name }}"
        value="{{ $value }}"
        class="hoa-checkbox-input"
        data-auth-checkbox
        @checked($checked)
        @required($required)
    >
    <span>{{ $slot }}</span>
</label>
