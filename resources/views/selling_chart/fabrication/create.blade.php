@extends('layouts.app')

@section('title', 'Create Fabrication')

@section('content')
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">
        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Create Fabrication</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Add a new fabrication lookup name</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.selling_chart.fabrication.store') }}" id="fabricationForm">
            @csrf

            <div class="space-y-5">
                <!-- ── Fabrication Details ── -->
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                        Fabrication Information
                    </div>
                    <p class="section-desc">Enter a unique fabrication name.</p>

                    <div class="grid grid-cols-1 gap-4">
                        <!-- Name -->
                        <div>
                            <label class="f-label">Fabrication Name <span class="f-required">*</span></label>
                            <input type="text" name="name" class="f-input @error('name') border-red-400 @enderror"
                                   value="{{ old('name') }}" placeholder="e.g. Cotton Weave" required />
                            <p class="f-hint mt-1">Must be unique across all fabrication records.</p>
                            @error('name')
                                <p class="f-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <div class="toggle-track on" id="statusToggle" onclick="toggleSwitch('statusToggle')">
                                    <div class="toggle-thumb"></div>
                                </div>
                                <span class="text-sm text-slate-600 dark:text-slate-300 font-medium">Active status</span>
                                <input type="checkbox" name="status" class="hidden" id="statusCheckbox" checked>
                            </label>
                            <p class="f-hint mt-1">Enable to activate this fabrication immediately.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STICKY FOOTER ── -->
            <div class="sticky-footer mt-5 -mx-5 rounded-none">
                <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Fields marked <span class="text-red-400 mx-1">*</span> are required
                    </div>
                    <div class="flex gap-2.5">
                        <a href="{{ route('admin.selling_chart.fabrication.index') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                                class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Create Fabrication
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('js')
<script>
    document.getElementById('statusToggle').addEventListener('click', function() {
        var cb = document.getElementById('statusCheckbox');
        cb.checked = this.classList.contains('on');
    });
</script>
@endpush
