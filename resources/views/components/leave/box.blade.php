@props(['checked' => false, 'mark' => null])
{{-- A tick box on the printed form. data-checked names what is ticked. --}}
<span class="box" @if ($checked) data-checked="{{ $mark }}" @endif>{{ $checked ? '✓' : '' }}</span>
