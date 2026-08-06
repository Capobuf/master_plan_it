<tr><td class="px-6 py-4 text-sm font-medium text-gray-800 dark:text-white" style="padding-left: {{ 24 + ($center['depth'] * 28) }}px">{{ $center['name'] }}</td><td class="px-6 py-4"><x-ui.badge :color="$center['active'] ? 'success' : 'gray'">{{ $center['active'] ? 'Attivo' : 'Inattivo' }}</x-ui.badge></td><td class="px-6 py-4 text-right"><div class="flex justify-end gap-3 text-sm font-medium">
    @if($abilities['update'])
        <a href="{{ route('operational.cost-centers.edit',$center['id']) }}" class="text-brand-500">Modifica</a>
    @endif
    @if($abilities['viewRevisions'])
        <a href="{{ route('operational.cost-centers.history',$center['id']) }}" class="text-brand-500">Storico</a>
    @endif
</div></td></tr>
@foreach($center['children'] as $child)
    @include('operational.cost-centers._row', ['center' => $child])
@endforeach
