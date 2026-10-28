@extends('layouts.app')

@section('title', 'Sayfa Bulunamadı (404) - Dost TV')
@section('description', 'Aradığınız sayfa bulunamadı veya taşınmış olabilir. Dost TV canlı yayınını izleyebilir, yayın akışına göz atabilir veya arama yapabilirsiniz.')

@section('content')
@php
    $popularPrograms = \Illuminate\Support\Facades\Cache::remember('error_404_popular_programs', 3600, function () {
        return \App\Models\Program::query()
            ->with('categories')
            ->where('is_active', true)
            ->where('show_on_public', true)
            ->where('status', '!=', 'archived')
            ->orderByRaw('is_featured DESC, sort_order ASC')
            ->limit(4)
            ->get();
    });
@endphp

<div class="relative py-12 sm:py-20">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        {{-- Hero 404 Kutusu --}}
        <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-b from-slate-900/80 to-slate-950/90 p-8 sm:p-12 text-center shadow-2xl backdrop-blur-sm">
            {{-- Durum Rozeti --}}
            <div class="inline-flex items-center gap-2 rounded-full border border-rose-500/30 bg-rose-500/10 px-4 py-1.5 text-xs font-semibold tracking-wide text-rose-400">
                <span class="inline-block h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                Hata Kodu: 404
            </div>

            <h1 class="mt-5 text-3xl font-extrabold tracking-tight text-white sm:text-5xl">
                Aradığınız Sayfa Bulunamadı
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                Ulaşmaya çalıştığınız içerik taşınmış, adı güncellenmiş veya yayından kaldırılmış olabilir.
                Aşağıdaki arama çubuğunu veya hızlı bağlantıları kullanarak dilediğiniz içeriğe hemen ulaşabilirsiniz.
            </p>

            {{-- Arama Formu --}}
            <div class="mx-auto mt-8 max-w-xl">
                <form action="{{ route('search.index') }}" method="GET" class="relative flex items-center">
                    <input
                        type="text"
                        name="q"
                        placeholder="Program, sunucu veya konu ara..."
                        class="w-full rounded-2xl border border-white/15 bg-white/5 py-3.5 pl-5 pr-28 text-sm text-white placeholder-slate-400 backdrop-blur-md transition focus:border-rose-500 focus:bg-white/10 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                        required
                    />
                    <button
                        type="submit"
                        class="absolute right-2 inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-rose-600/30 transition hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <span>Ara</span>
                    </button>
                </form>
            </div>

            {{-- Hızlı Eylem Butonları --}}
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a
                    href="{{ route('live.tv') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/25 transition hover:bg-rose-500"
                >
                    <span class="inline-block h-2 w-2 rounded-full bg-white animate-ping"></span>
                    Canlı Yayın İzle
                </a>

                <a
                    href="{{ route('schedule.index') }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-5 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white"
                >
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Yayın Akışı
                </a>

                <a
                    href="{{ route('programs.index') }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-5 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white"
                >
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Tüm Programlar
                </a>

                <a
                    href="{{ route('home') }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-slate-900/60 px-5 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"
                >
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Ana Sayfa
                </a>
            </div>
        </div>

        {{-- Öne Çıkan Programlar Rafı --}}
        @if ($popularPrograms->isNotEmpty())
            <div class="mt-14 sm:mt-18">
                <div class="flex items-center justify-between border-b border-white/10 pb-4">
                    <div>
                        <h2 class="text-xl font-bold text-white sm:text-2xl">Öne Çıkan Programlar</h2>
                        <p class="text-xs text-slate-400 mt-1">Dost TV ekranlarının en çok takip edilen programları</p>
                    </div>
                    <a href="{{ route('programs.index') }}" class="text-xs font-semibold text-rose-400 transition hover:text-rose-300">
                        Tümünü Gör &rarr;
                    </a>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4 sm:gap-6">
                    @foreach ($popularPrograms as $program)
                        <a href="{{ route('programs.show', $program) }}" class="group block">
                            <x-site.program-card
                                :title="$program->name"
                                :cover-image="$program->cover_image"
                                :categories="$program->categories->pluck('name')->toArray()"
                            />
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- İletişim & Yardım Notu --}}
        <div class="mt-12 text-center text-xs text-slate-400">
            Aradığınız sayfayı bulamadınız mı?
            <a href="{{ url('/iletisim') }}" class="text-rose-400 underline underline-offset-4 hover:text-rose-300">
                Bizimle İletişime Geçin
            </a>
        </div>
    </div>
</div>
@endsection
