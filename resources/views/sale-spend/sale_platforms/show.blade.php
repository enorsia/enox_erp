@extends('layouts.app')

@section('title', 'View Sale Platform — ' . $salePlatform->name)

@section('content')
    <div id="sale-platform-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        {{-- ── BREADCRUMB TRAIL ── --}}
        @if (!empty($breadcrumbs) || true)
            <nav class="flex items-center gap-1.5 flex-wrap mb-4 text-[12px]">
                <a href="{{ route('admin.sale-platforms.index') }}"
                   class="text-slate-400 dark:text-slate-500 hover:text-accent-500 transition-colors">
                    Sale Platforms
                </a>
                @foreach ($breadcrumbs as $ancestor)
                    <svg class="w-3 h-3 text-slate-300 dark:text-slate-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                    <a href="{{ route('admin.sale-platforms.show', $ancestor->id) }}"
                       class="text-slate-400 dark:text-slate-500 hover:text-accent-500 transition-colors truncate max-w-[160px]">
                        {{ $ancestor->name }}
                    </a>
                @endforeach
                <svg class="w-3 h-3 text-slate-300 dark:text-slate-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                </svg>
                <span class="text-slate-600 dark:text-slate-300 font-medium truncate max-w-[160px]">{{ $salePlatform->name }}</span>
            </nav>
        @endif

        {{-- ── PAGE HEADER ── --}}
        <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                {{-- Platform type icon badge --}}
                <div class="w-11 h-11 rounded-xl
                        @if($salePlatform->type === 'channel') bg-blue-50 dark:bg-blue-900/20
                        @elseif($salePlatform->type === 'sub_channel') bg-purple-50 dark:bg-purple-900/20
                        @elseif($salePlatform->type === 'marketplace') bg-orange-50 dark:bg-orange-900/20
                        @else bg-teal-50 dark:bg-teal-900/20 @endif
                        flex items-center justify-center shrink-0">
                    @if($salePlatform->type === 'channel')
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
                        </svg>
                    @elseif($salePlatform->type === 'sub_channel')
                        <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                        </svg>
                    @elseif($salePlatform->type === 'marketplace')
                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
                        </svg>
                    @endif
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">{{ $salePlatform->name }}</h1>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="badge-custom badge-blue text-[10px]">{{ ucfirst(str_replace('_', ' ', $salePlatform->type)) }}</span>
                        @if ($salePlatform->is_active)
                            <span class="badge-custom badge-green text-[10px]">Active</span>
                        @else
                            <span class="badge-custom badge-red text-[10px]">Inactive</span>
                        @endif
                        @if ($salePlatform->children->isNotEmpty())
                            <span class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $salePlatform->children->count() }} {{ Str::plural('child', $salePlatform->children->count()) }}
                        </span>
                        @endif
                        @if ($siblingsCount > 0)
                            <span class="text-[11px] text-slate-400 dark:text-slate-500">
                            · {{ $siblingsCount }} {{ Str::plural('sibling', $siblingsCount) }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

            {{-- ── LEFT COLUMN ── --}}
            <div class="space-y-5">

                {{-- Basic Information --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Basic Information
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        {{-- Name --}}
                        <div class="flex items-center justify-between py-3 first:pt-0">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Name</span>
                            <span class="text-[13px] text-slate-800 dark:text-slate-100 font-medium text-right">{{ $salePlatform->name }}</span>
                        </div>

                        {{-- Slug --}}
                        <div class="flex items-center justify-between py-3">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Slug</span>
                            <code class="text-[12px] text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded font-mono">{{ $salePlatform->slug }}</code>
                        </div>

                        {{-- Type --}}
                        <div class="flex items-center justify-between py-3">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Type</span>
                            <span class="badge-custom badge-blue">{{ ucfirst(str_replace('_', ' ', $salePlatform->type)) }}</span>
                        </div>

                        {{-- Sort Order --}}
                        <div class="flex items-center justify-between py-3 last:pb-0">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Sort Order</span>
                            <span class="text-[13px] text-slate-700 dark:text-slate-200 font-mono">{{ $salePlatform->sort_order }}</span>
                        </div>
                    </div>
                </div>

                {{-- Children platforms (if any) --}}
                @if ($salePlatform->children->isNotEmpty())
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                            </svg>
                            Child Platforms
                            <span class="ml-auto text-[11px] text-slate-400 dark:text-slate-500 font-normal">
                            {{ $salePlatform->children->count() }} total
                        </span>
                        </div>

                        <div class="flex flex-col gap-2">
                            @foreach ($salePlatform->children as $child)
                                @php
                                    $childTypeColors = [
                                        'channel'     => 'badge-blue',
                                        'sub_channel' => 'badge-purple',
                                        'marketplace' => 'badge-orange',
                                        'region'      => 'badge-teal',
                                    ];
                                @endphp
                                <a href="{{ route('admin.sale-platforms.show', $child->id) }}"
                                   class="flex items-center justify-between p-3 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-accent-300 dark:hover:border-accent-600 hover:bg-accent-50 dark:hover:bg-accent-900/10 transition-colors group">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        {{-- connector dot --}}
                                        <div class="w-5 h-5 rounded flex items-center justify-center shrink-0">
                                            <div class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600 group-hover:bg-accent-400 transition-colors"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[13px] font-medium text-slate-700 dark:text-slate-200 group-hover:text-accent-600 dark:group-hover:text-accent-400 transition-colors truncate">
                                                {{ $child->name }}
                                            </p>
                                            <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">{{ $child->slug }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0 ml-2">
                                    <span class="badge-custom {{ $childTypeColors[$child->type] ?? 'badge-blue' }} text-[10px]">
                                        {{ ucfirst(str_replace('_', ' ', $child->type)) }}
                                    </span>
                                        @if ($child->is_active)
                                            <span class="badge-custom badge-green text-[10px]">Active</span>
                                        @else
                                            <span class="badge-custom badge-red text-[10px]">Inactive</span>
                                        @endif
                                        <svg class="w-3.5 h-3.5 text-slate-300 dark:text-slate-600 group-hover:text-accent-400 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                        </svg>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

            {{-- ── RIGHT COLUMN ── --}}
            <div class="space-y-5">

                {{-- Status & Metadata --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                        Status & Info
                    </div>

                    <div class="space-y-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1.5">Status</p>
                            @if ($salePlatform->is_active)
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                    <span class="text-[13px] text-emerald-600 dark:text-emerald-400 font-medium">Active</span>
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-red-400"></div>
                                    <span class="text-[13px] text-red-500 dark:text-red-400 font-medium">Inactive</span>
                                </div>
                            @endif
                        </div>

                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1.5">Created</p>
                            <p class="text-[13px] text-slate-700 dark:text-slate-200">
                                {{ $salePlatform->created_at ? $salePlatform->created_at->format('M d, Y') : 'N/A' }}
                            </p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $salePlatform->created_at ? $salePlatform->created_at->format('H:i A') : '' }}
                                · {{ $salePlatform->created_at?->diffForHumans() }}
                            </p>
                        </div>

                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1.5">Last Updated</p>
                            <p class="text-[13px] text-slate-700 dark:text-slate-200">
                                {{ $salePlatform->updated_at ? $salePlatform->updated_at->format('M d, Y') : 'N/A' }}
                            </p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $salePlatform->updated_at ? $salePlatform->updated_at->format('H:i A') : '' }}
                                · {{ $salePlatform->updated_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Module Visibility --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Module Visibility
                    </div>

                    <div class="space-y-3">
                        {{-- Show in daily sale & spend report --}}
                        <div class="flex items-start gap-3 p-3 rounded-lg border {{ $salePlatform->show_in_analytics ? 'border-blue-100 dark:border-blue-800/30 bg-blue-50 dark:bg-blue-900/10' : 'border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/20' }}">
                            <div class="mt-0.5 shrink-0">
                                @if ($salePlatform->show_in_analytics)
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </div>
                            <div>
                                <p class="text-[13px] font-medium {{ $salePlatform->show_in_analytics ? 'text-blue-700 dark:text-blue-300' : 'text-slate-500 dark:text-slate-400' }}">
                                    Show in daily sale & spend report
                                </p>
                                <p class="text-[11px] {{ $salePlatform->show_in_analytics ? 'text-blue-500 dark:text-blue-400' : 'text-slate-400' }}">
                                    {{ $salePlatform->show_in_analytics ? 'Visible in Daily Sale & Spend report, Daily Sales & Daily Returns' : 'Hidden from Daily Sale & Spend report modules' }}
                                </p>
                            </div>
                        </div>

                        {{-- Show in Ads performance --}}
                        <div class="flex items-start gap-3 p-3 rounded-lg border {{ $salePlatform->show_in_sale_tracking ? 'border-violet-100 dark:border-violet-800/30 bg-violet-50 dark:bg-violet-900/10' : 'border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/20' }}">
                            <div class="mt-0.5 shrink-0">
                                @if ($salePlatform->show_in_sale_tracking)
                                    <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </div>
                            <div>
                                <p class="text-[13px] font-medium {{ $salePlatform->show_in_sale_tracking ? 'text-violet-700 dark:text-violet-300' : 'text-slate-500 dark:text-slate-400' }}">
                                    Show in Ads performance
                                </p>
                                <p class="text-[11px] {{ $salePlatform->show_in_sale_tracking ? 'text-violet-500 dark:text-violet-400' : 'text-slate-400' }}">
                                    {{ $salePlatform->show_in_sale_tracking ? 'Visible in Ads Performance module' : 'Hidden from Ads Performance module' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Parent Platform --}}
                @if ($salePlatform->parent)
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M15 11.25l-3-3m0 0l-3 3m3-3v7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Parent Platform
                        </div>
                        <a href="{{ route('admin.sale-platforms.show', $salePlatform->parent->id) }}"
                           class="flex items-center gap-2.5 p-3 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-accent-300 dark:hover:border-accent-600 hover:bg-accent-50 dark:hover:bg-accent-900/10 transition-colors group">
                            <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-accent-400 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z"/>
                                    <path stroke-linecap="round" d="M3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25z"/>
                                    <path stroke-linecap="round" d="M13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-medium text-slate-700 dark:text-slate-200 group-hover:text-accent-600 dark:group-hover:text-accent-400 transition-colors truncate">
                                    {{ $salePlatform->parent->name }}
                                </p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">{{ $salePlatform->parent->slug }}</p>
                            </div>
                            <svg class="w-3.5 h-3.5 text-slate-300 dark:text-slate-600 group-hover:text-accent-400 transition-colors shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                            </svg>
                        </a>
                    </div>
                @endif

                {{-- Quick stats --}}
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Children</p>
                        <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $salePlatform->children->count() }}</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Siblings</p>
                        <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $siblingsCount }}</p>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── STICKY FOOTER ── --}}
        <div class="sticky-footer mt-5 -mx-5 rounded-none">
            <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                    <svg class="w-3.5 h-3.5 text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>ID: <code class="font-mono text-slate-500">{{ $salePlatform->id }}</code></span>
                </div>
                <div class="flex gap-2.5">
                    <a href="{{ route('admin.sale-platforms.index') }}"
                       class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                        Back to List
                    </a>
                    @can('general.sale_platform.edit')
                        <a href="{{ route('admin.sale-platforms.edit', $salePlatform->id) }}"
                           class="px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                        </a>
                    @endcan
                </div>
            </div>
        </div>

    </div>
@endsection