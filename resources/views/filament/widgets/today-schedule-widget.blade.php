<x-filament-widgets::widget>
    <div class="fi-wi-card p-6 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-500/10 text-rose-500">
                    <x-heroicon-o-tv class="w-5 h-5" />
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Bugünün Yayın Akışı</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $formattedDate }}</p>
                </div>
            </div>

            <a href="{{ $scheduleCalendarUrl }}" class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline inline-flex items-center gap-1">
                Yayın Akışını Aç &rarr;
            </a>
        </div>

        {{-- Body --}}
        @if($hasBroadcasts)
            <div class="mt-4 overflow-x-auto">
                <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @foreach($broadcasts as $item)
                        <div @class([
                            'py-2.5 px-3 flex flex-wrap items-center justify-between gap-3 rounded-lg transition-colors',
                            'bg-rose-500/5 dark:bg-rose-500/10 border border-rose-500/20' => $item['status_key'] === 'now_playing',
                            'bg-blue-500/5 dark:bg-blue-500/10 border border-blue-500/20' => $item['status_key'] === 'next_upcoming',
                            'hover:bg-slate-50 dark:hover:bg-slate-800/50' => !in_array($item['status_key'], ['now_playing', 'next_upcoming']),
                        ])>
                            {{-- Saat & Başlık --}}
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="shrink-0 w-28 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <span>{{ $item['start_time'] }}</span>
                                    @if($item['end_time'])
                                        <span class="text-slate-400 font-normal"> - {{ $item['end_time'] }}</span>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <span class="text-sm font-semibold text-slate-900 dark:text-white truncate block">
                                        {{ $item['display_title'] }}
                                    </span>
                                </div>
                            </div>

                            {{-- Rozetler & Durum --}}
                            <div class="flex items-center gap-2 shrink-0">
                                @if($item['is_live'])
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-red-600 text-white shadow-xs">
                                        Canlı
                                    </span>
                                @endif

                                @if($item['is_repeat'])
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        Tekrar
                                    </span>
                                @endif

                                @if(!empty($item['is_package']))
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                        Paket
                                    </span>
                                @endif

                                {{-- Zaman Durumu Rozeti --}}
                                @if($item['status_key'] === 'now_playing')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-500 text-white shadow-xs animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                        Şu Anda
                                    </span>
                                @elseif($item['status_key'] === 'next_upcoming')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
                                        Sıradaki
                                    </span>
                                @elseif($item['status_key'] === 'finished')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-slate-400 bg-slate-100 dark:bg-slate-800">
                                        Tamamlandı
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60">
                                        Bekleyen
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="py-8 text-center text-slate-500 dark:text-slate-400 text-sm">
                <p>Bugün için yayın akışı bulunmuyor.</p>
                <a href="{{ $scheduleCalendarUrl }}" class="mt-2 inline-flex items-center text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline">
                    Yayın Akışı Planla &rarr;
                </a>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
