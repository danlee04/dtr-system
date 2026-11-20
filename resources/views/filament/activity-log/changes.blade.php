@use('App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable')
{{-- One activity's changes: each field, before and after. Every value is someone's data, so it is escaped. --}}
@include('filament.entry-grid')

<div class="dtr-grid">
    @if ($changes === [])
        <p class="dtr-grid-hint">No field values were recorded for this change.</p>
    @else
        <div class="dtr-grid-wrap is-full">
            <table class="dtr-grid-table">
                <thead>
                    <tr>
                        <th scope="col" class="dtr-grid-start">Field</th>
                        <th scope="col" class="dtr-grid-start">Before</th>
                        <th scope="col" class="dtr-grid-start">After</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($changes as $field => ['old' => $old, 'new' => $new])
                        <tr>
                            <th scope="row" class="dtr-grid-name">{{ ActivityLogsTable::label($field) }}</th>
                            @foreach ([$old, $new] as $value)
                                <td class="dtr-grid-start">{{ match (true) { is_bool($value) => $value ? 'Yes' : 'No', $value === null => '—', is_scalar($value) => $value, default => json_encode($value) } }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
