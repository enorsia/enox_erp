@extends('layouts.app')

@section('title', 'View Role')

@section('content')
    <div class="p-5 lg:p-6">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Role: {{ ucfirst($role->name) }}</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Assigned permissions for this role</p>
            </div>
            <div class="flex gap-2.5">
                @can('authentication.roles.create')
                    <a href="{{ route('admin.roles.create') }}"
                       class="flex items-center gap-2 px-4 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                        </svg>
                        Create Role
                    </a>
                @endcan
                <a href="{{ route('admin.roles.index') }}"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Roles
                </a>
            </div>
        </div>

        <!-- Permissions -->
        @if (!empty($nested) && count($nested))
            <div class="space-y-3">
                @foreach ($nested as $moduleIndex => $models)
                    @php $colId = 'view_mod_' . Str::slug($moduleIndex); @endphp
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                        <!-- Module header -->
                        <button type="button" onclick="toggleModule('{{ $colId }}')"
                                class="w-full flex items-center justify-between px-5 py-3.5 bg-slate-50 dark:bg-slate-800/50 text-left">
                            <span class="text-[13px] font-semibold text-slate-700 dark:text-slate-200 capitalize">{{ $moduleIndex }} Module</span>
                            <svg id="{{ $colId }}-icon" class="w-4 h-4 text-slate-400 transition-transform duration-200"
                                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <!-- Module body -->
                        <div id="{{ $colId }}" class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach ($models as $model => $modelPermissions)
                                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50 dark:bg-slate-900/40">
                                    <p class="text-[12px] font-semibold text-accent-400 capitalize mb-2">{{ $model }} Model</p>
                                    <hr class="border-slate-200 dark:border-slate-700 mb-2" />
                                    @foreach ($modelPermissions as $perm)
                                        @php
                                            $parts  = explode('.', $perm->name);
                                            $action = ucfirst(end($parts));
                                        @endphp
                                        <div class="flex items-center justify-between py-0.5">
                                            <span class="text-[12px] text-slate-600 dark:text-slate-300 flex items-center gap-1.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-accent-400 flex-shrink-0"></span>
                                                {{ $action }}
                                            </span>
                                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ $perm->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                <p class="text-sm text-slate-400 dark:text-slate-500">No permissions assigned to this role.</p>
            </div>
        @endif

    </div>
@endsection

@push('js')
<script>
    function toggleModule(id) {
        var panel = document.getElementById(id);
        var icon  = document.getElementById(id + '-icon');
        var hidden = panel.classList.toggle('hidden');
        if (icon) icon.style.transform = hidden ? 'rotate(-90deg)' : '';
    }
</script>
@endpush
