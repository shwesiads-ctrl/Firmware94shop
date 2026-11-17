@props(['dark' => false])
<div class="flex items-center gap-1 text-xs {{ $dark ? 'text-slate-200' : 'text-slate-500' }}">
    @foreach (config('app.supported_locales') as $locale)
        <a href="{{ route('locale.switch', $locale) }}"
           class="px-1.5 py-0.5 rounded {{ app()->getLocale() === $locale ? ($dark ? 'bg-white/20' : 'bg-slate-200') : '' }}">
            {{ strtoupper($locale) }}
        </a>
    @endforeach
</div>
