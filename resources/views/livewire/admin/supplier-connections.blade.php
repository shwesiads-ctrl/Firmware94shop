<div class="f94-card">
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400"><tr><th class="py-2">Supplier</th><th>Mode</th><th></th><th>Result</th></tr></thead>
        <tbody>
            @foreach ($suppliers as $supplier)
                <tr class="border-t border-slate-100">
                    <td class="py-2">{{ $supplier->name }}</td>
                    <td>{{ $supplier->mode }}</td>
                    <td><button wire:click="test({{ $supplier->id }})" class="text-f94-teal text-xs">Test connection</button></td>
                    <td class="text-xs text-slate-500">{{ isset($results[$supplier->id]) ? json_encode($results[$supplier->id]) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
