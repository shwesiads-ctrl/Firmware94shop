<div class="f94-card">
    @if ($newSecret)
        <div class="mb-3 text-sm bg-amber-50 text-amber-800 border border-amber-200 rounded-lg p-3">
            Copy this now — it will not be shown again: <code class="font-mono">{{ $newSecret }}</code>
        </div>
    @endif

    <div class="flex gap-2 mb-4">
        <input type="text" wire:model="label" placeholder="Key label" class="flex-1 rounded-lg border-slate-300">
        <button wire:click="generate" class="f94-btn-primary">Generate key</button>
    </div>

    <table class="w-full text-sm">
        <thead class="text-left text-slate-400"><tr><th class="py-2">Label</th><th>Public key</th><th>Active</th></tr></thead>
        <tbody>
            @foreach ($credentials as $credential)
                <tr class="border-t border-slate-100">
                    <td class="py-2">{{ $credential->label }}</td>
                    <td class="font-mono text-xs">{{ $credential->public_key }}</td>
                    <td>{{ $credential->is_active ? 'Yes' : 'No' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
