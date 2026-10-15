<x-filament-panels::page>
    @php
        $days = \App\Models\Schedule::DAYS;
        $templates = $this->templates;
        $currentTemplate = $this->selectedTemplate;

        $startOfWeek = \Illuminate\Support\Carbon::now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY);

        $trMonths = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

        $activeTemplates = $templates->filter(fn ($t) => $t->is_active || $t->status === 'published');
        $draftTemplates = $templates->filter(fn ($t) => ! $t->is_active && $t->status !== 'published');
    @endphp

    <div class="schedule-calendar-ui w-full space-y-4">
        <style>
            .schedule-calendar-ui .schedule-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                flex-wrap: wrap;
                background-color: #111827;
                border: 1px solid #1f2937;
                border-radius: 1rem;
                padding: 1rem 1.25rem;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3);
            }

            .schedule-calendar-ui .schedule-toolbar-left {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                flex-wrap: wrap;
            }

            .schedule-calendar-ui .schedule-label {
                font-size: 0.75rem;
                font-weight: 700;
                color: #9ca3af;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                white-space: nowrap;
            }

            .schedule-calendar-ui .schedule-select {
                min-width: 280px;
                max-width: 440px;
                height: 42px;
                background-color: #1f2937;
                border: 1px solid #374151;
                color: #ffffff;
                border-radius: 0.5rem;
                padding: 0 1rem;
                font-size: 0.8125rem;
                font-weight: 600;
                cursor: pointer;
                outline: none;
                transition: border-color 0.15s ease;
            }

            .schedule-calendar-ui .schedule-select:focus {
                border-color: #f59e0b;
            }

            .schedule-calendar-ui .schedule-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                padding: 0.375rem 0.75rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 600;
                border: 1px solid transparent;
            }

            .schedule-calendar-ui .schedule-badge.is-active {
                background-color: rgba(16, 185, 129, 0.2);
                color: #6ee7b7;
                border-color: rgba(16, 185, 129, 0.3);
            }

            .schedule-calendar-ui .schedule-badge.is-published {
                background-color: rgba(16, 185, 129, 0.1);
                color: #34d399;
                border-color: rgba(16, 185, 129, 0.2);
            }

            .schedule-calendar-ui .schedule-badge.is-draft {
                background-color: rgba(245, 158, 11, 0.2);
                color: #fcd34d;
                border-color: rgba(245, 158, 11, 0.3);
            }

            .schedule-calendar-ui .schedule-dot {
                width: 0.375rem;
                height: 0.375rem;
                border-radius: 9999px;
            }

            .schedule-calendar-ui .schedule-badge.is-active .schedule-dot,
            .schedule-calendar-ui .schedule-badge.is-published .schedule-dot {
                background-color: #34d399;
            }

            .schedule-calendar-ui .schedule-badge.is-draft .schedule-dot {
                background-color: #fbbf24;
            }

            .schedule-calendar-ui .schedule-day-grid {
                display: grid;
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: 0.625rem;
                margin-top: 1rem;
                margin-bottom: 1.25rem;
                width: 100%;
            }

            @media (max-width: 1024px) {
                .schedule-calendar-ui .schedule-day-grid {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .schedule-calendar-ui .schedule-day-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            .schedule-calendar-ui .schedule-day-card {
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                width: 100%;
                min-height: 92px;
                padding: 1.125rem 0.5rem;
                background-color: #111827;
                border: 1px solid #1f2937;
                border-radius: 1rem;
                text-align: center;
                cursor: pointer;
                user-select: none;
                transition: background-color 0.15s ease, border-color 0.15s ease;
                outline: none;
            }

            .schedule-calendar-ui .schedule-day-card:hover {
                background-color: #1f2937;
                border-color: #374151;
            }

            .schedule-calendar-ui .schedule-day-card.is-selected {
                background-color: #f59e0b;
                border-color: #fbbf24;
                color: #020617;
                box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.25);
            }

            .schedule-calendar-ui .schedule-day-name {
                display: block;
                font-size: 0.75rem;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                line-height: 1.2;
            }

            .schedule-calendar-ui .schedule-day-date {
                display: block;
                font-size: 0.6875rem;
                font-weight: 500;
                margin-top: 0.25rem;
                opacity: 0.9;
                line-height: 1.2;
            }

            .schedule-calendar-ui .schedule-today-badge {
                position: absolute;
                top: 0.375rem;
                right: 0.375rem;
                padding: 0.125rem 0.375rem;
                font-size: 0.5625rem;
                font-weight: 700;
                text-transform: uppercase;
                border-radius: 0.25rem;
                letter-spacing: 0.025em;
            }

            .schedule-calendar-ui .schedule-day-card:not(.is-selected) .schedule-today-badge {
                background-color: rgba(245, 158, 11, 0.15);
                color: #fbbf24;
                border: 1px solid rgba(245, 158, 11, 0.3);
            }

            .schedule-calendar-ui .schedule-day-card.is-selected .schedule-today-badge {
                background-color: rgba(2, 6, 23, 0.25);
                color: #020617;
            }
        </style>

        @if (! $currentTemplate && $templates->isEmpty())
            {{-- AKTİF DÖNEM VEYA ŞABLON YOKSA SADE BOŞ DURUM --}}
            <div class="rounded-xl border border-gray-800 bg-gray-900 p-8 text-center shadow-lg space-y-3">
                <h3 class="text-base font-semibold text-white">Gösterimde olan bir yayın dönemi bulunmuyor.</h3>
                <p class="text-xs text-gray-400">Yayın akışını düzenlemek için önce bir yayın dönemi oluşturun veya var olan dönemi aktif yapın.</p>
                <div class="pt-2">
                    <a 
                        href="{{ route('filament.admin.resources.schedule-templates.index') }}" 
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold text-slate-900 hover:bg-amber-400 transition cursor-pointer"
                    >
                        <span>Yayın Dönemlerine Git</span>
                        <span>→</span>
                    </a>
                </div>
            </div>
        @else
            {{-- 1. AKIŞ SEÇİM KARTI (FULL WIDTH TOOLBAR) --}}
            <div class="schedule-toolbar">
                <div class="schedule-toolbar-left">
                    <span class="schedule-label">AKIŞ SEÇ:</span>
                    <select 
                        id="template-select"
                        wire:model.live="selectedTemplateId"
                        class="schedule-select"
                    >
                        @if($activeTemplates->isNotEmpty())
                            <optgroup label="Gösterimde / Yayında">
                                @foreach ($activeTemplates as $t)
                                    <option value="{{ $t->id }}">
                                        {{ $t->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif

                        @if($draftTemplates->isNotEmpty())
                            <optgroup label="Taslaklar">
                                @foreach ($draftTemplates as $t)
                                    <option value="{{ $t->id }}">
                                        {{ $t->name }}
                                    </option>
                                @endforeach
                        @endif
                    </select>

                    @if($currentTemplate)
                        <button type="button"
                                wire:click="toggleShowProgramNames"
                                style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(255,255,255,0.12); cursor: pointer; transition: all 0.15s ease; {{ ($currentTemplate->show_program_names ?? true) ? 'background: rgba(52, 211, 153, 0.15); color: #34d399; border-color: rgba(52, 211, 153, 0.3);' : 'background: rgba(148, 163, 184, 0.15); color: #94a3b8; border-color: rgba(148, 163, 184, 0.3);' }}"
                                title="Public /yayin-akisi sayfasında program adlarının gösterimini açıp kapatın">
                            <span>Program İsimleri:</span>
                            <span style="padding: 2px 6px; border-radius: 9999px; font-size: 0.6875rem; font-weight: 800; {{ ($currentTemplate->show_program_names ?? true) ? 'background: #34d399; color: #020617;' : 'background: #64748b; color: #f8fafc;' }}">
                                {{ ($currentTemplate->show_program_names ?? true) ? 'AÇIK' : 'KAPALI' }}
                            </span>
                        </button>
                    @endif
                </div>

                @if($currentTemplate)
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($currentTemplate->is_active)
                            <span class="schedule-badge is-active">
                                <span class="schedule-dot"></span>
                                <span>Gösterimde</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-500/10 text-amber-300 border border-amber-500/30">
                                <span>⚠️</span>
                                <span>Bu dönem şu anda gösterimde değil.</span>
                            </span>

                            <button 
                                type="button" 
                                wire:click="mountAction('activate_current_template')" 
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 px-3 py-1 text-xs font-bold text-white shadow transition cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Bu Dönemi Gösterime Al</span>
                            </button>

                            <span class="schedule-badge {{ $currentTemplate->status === 'published' ? 'is-published' : 'is-draft' }}">
                                <span class="schedule-dot"></span>
                                <span>{{ $currentTemplate->status === 'published' ? 'Yayında' : 'Taslak' }}</span>
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ÖZEL YAYIN / İSTİSNA BİLGİ BANNER'I --}}
            @php
                $dayException = $this->exceptionForSelectedDay;
            @endphp
            @if($dayException)
                <div class="rounded-xl border border-purple-500/30 bg-purple-950/40 p-4 shadow-lg flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-500/20 text-purple-400 border border-purple-500/30">
                            ⚡
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold uppercase tracking-wider text-purple-400">Bu gün için özel yayın akışı kullanıyor</span>
                                <span class="inline-flex items-center rounded-full bg-purple-500/20 px-2 py-0.5 text-[10px] font-semibold text-purple-300 border border-purple-500/30">
                                    {{ $dayException->override_type === 'replace_all' ? 'Tüm Akış Değiştirildi' : 'Mevcut Akışa Eklendi' }}
                                </span>
                                <span class="inline-flex items-center rounded-full {{ $this->showExceptionItems ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border-amber-500/30' }} px-2 py-0.5 text-[10px] font-semibold border">
                                    {{ $this->showExceptionItems ? 'Gösterilen: Özel Yayın Akışı' : 'Gösterilen: Normal Şablon Akışı' }}
                                </span>
                            </div>
                            <p class="text-xs text-purple-200/80 mt-0.5">
                                {{ $dayException->name }} — Ziyaretçiler public sitede bu tarihte ({{ \Illuminate\Support\Carbon::parse($dayException->exception_date)->format('d.m.Y') }}) özel yayın akışını görecektir.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 flex-wrap">
                        <button 
                            type="button" 
                            wire:click="toggleExceptionView" 
                            class="inline-flex items-center gap-1.5 rounded-lg border border-purple-500/40 bg-purple-500/20 px-3 py-1.5 text-xs font-bold text-purple-200 hover:bg-purple-500/30 transition cursor-pointer"
                        >
                            <span>{{ $this->showExceptionItems ? 'Normal Akışı Gör' : 'Özel Akışı Düzenle' }}</span>
                        </button>
                        <button 
                            type="button" 
                            wire:click="removeSelectedDayException({{ $dayException->id }})" 
                            wire:confirm="Bu tarihteki özel yayın akışını kaldırmak ve normal haftalık şablona dönmek istediğinizden emin misiniz?"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-bold text-red-400 hover:bg-red-500/20 transition cursor-pointer"
                        >
                            <span>Özel Akışı Kaldır (Normal Akışa Dön)</span>
                        </button>
                    </div>
                </div>
            @endif

            {{-- 2. YATAY 7 GÜN KARTLARI GRİDİ (DESKTOP: 7 EŞİT SÜTUN) --}}
            <div class="schedule-day-grid">
                @foreach ($days as $idx => $d)
                    @php
                        $isDaySelected = (int) $this->selectedDay === (int) $idx;
                        $cDate = $startOfWeek->copy()->addDays((int) $idx);
                        $isToday = $cDate->isToday();
                        $dayDate = $cDate->format('j') . ' ' . $trMonths[$cDate->month - 1];
                    @endphp
                    <button 
                        wire:click="selectDay({{ $idx }})" 
                        type="button" 
                        class="schedule-day-card {{ $isDaySelected ? 'is-selected' : '' }}"
                    >
                        @if($isToday)
                            <span class="schedule-today-badge">Bugün</span>
                        @endif

                        <span class="schedule-day-name">{{ $d }}</span>
                        <span class="schedule-day-date">{{ $dayDate }}</span>
                    </button>
                @endforeach
            </div>

            {{-- 3. FİLAMENT YAYIN TABLOSU --}}
            <div class="w-full">
                {{ $this->table }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
