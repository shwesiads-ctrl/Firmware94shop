<div class="grid md:grid-cols-2 gap-4">
    @foreach ($categories as $category)
        <div class="f94-card">
            <h3 class="font-semibold text-slate-800">{{ $category->name }}</h3>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($category->services as $service)
                    <li class="flex justify-between"><span>{{ $service->name }}</span><span class="text-slate-400">{{ $service->base_price }} USD</span></li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
