<select name="scope_type" aria-label="Scope type">
    @foreach ($scopeTypes as $class => $label)
        <option value="{{ $class }}" @selected(($selectedType ?? request('scope_type')) === $class)>{{ $label }}</option>
    @endforeach
    <option value="global" @selected(($selectedType ?? request('scope_type')) === 'global')>Global (Feature::globally())</option>
    <option value="raw" @selected(($selectedType ?? request('scope_type')) === 'raw')>Scope string, as stored</option>
</select>
<input name="scope_key" value="{{ $selectedKey ?? request('scope_key') }}" placeholder="ID or scope string" aria-label="Scope ID">
