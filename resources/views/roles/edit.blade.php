@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Role: {{ ucfirst($role->name) }}</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Update role name and reassign permissions</p>
            </div>
        </div>

        <form id="role-edit-form" method="POST" action="{{ route('admin.roles.update', $role->id) }}" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="page" value="{{ request('page') }}">

            <!-- ── Role Name ── -->
            <div class="section-card mb-5">
                <div class="section-title">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                    Role Details
                </div>
                <p class="section-desc">Update the role name.</p>
                <div class="max-w-sm">
                    <label for="name" class="f-label">Role Name <span class="f-required">*</span></label>
                    <input id="name" type="text" name="name"
                           class="f-input @error('name') border-red-400 @enderror"
                           value="{{ old('name', $role->name) }}" placeholder="e.g. Editor, Manager" />
                    @error('name')
                        <p class="f-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- ── Permissions ── -->
            <div class="section-card">
                <div class="section-title">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Assign Permissions
                </div>
                <p class="section-desc">Select the permissions for this role.</p>

                @error('permissions')
                    <p class="f-error mb-3">{{ $message }}</p>
                @enderror
                <p id="permissions-error-client" class="f-error mb-3 hidden">Select at least one permission.</p>

                @if (!empty($nested) && count($nested))
                    <div class="space-y-3">
                        @foreach ($nested as $moduleIndex => $models)
                            @php $colId = 'mod_' . Str::slug($moduleIndex); @endphp
                            <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                                <button type="button" onclick="toggleModule('{{ $colId }}')"
                                        class="w-full flex items-center justify-between px-4 py-3 bg-slate-50 dark:bg-slate-800/50 text-left">
                                    <span class="text-[13px] font-semibold text-slate-700 dark:text-slate-200 capitalize">{{ $moduleIndex }} Module</span>
                                    <svg id="{{ $colId }}-icon" class="w-4 h-4 text-slate-400 transition-transform duration-200"
                                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div id="{{ $colId }}" class="px-4 py-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach ($models as $model => $modelPermissions)
                                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-white dark:bg-slate-900/40 model-box">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-[12px] font-semibold text-accent-400 capitalize">{{ $model }}</span>
                                                <label class="flex items-center gap-1.5 cursor-pointer">
                                                    <input type="checkbox" class="select-all-model w-3.5 h-3.5 accent-accent-400" title="Select all" />
                                                    <span class="text-[10px] text-slate-400">All</span>
                                                </label>
                                            </div>
                                            <hr class="border-slate-200 dark:border-slate-700 mb-2" />
                                            @foreach ($modelPermissions as $perm)
                                                @php
                                                    $parts   = explode('.', $perm->name);
                                                    $action  = ucfirst(end($parts));
                                                    $checked = is_array(old('permissions'))
                                                        ? in_array($perm->id, old('permissions'))
                                                        : in_array($perm->id, $rolePermissions);
                                                @endphp
                                                <label class="flex items-center gap-2 py-0.5 cursor-pointer group">
                                                    <input class="permission-checkbox w-3.5 h-3.5 accent-accent-400"
                                                           type="checkbox" name="permissions[]"
                                                           value="{{ $perm->id }}" id="perm_{{ $perm->id }}"
                                                           {{ $checked ? 'checked' : '' }} />
                                                    <span class="text-[12px] text-slate-600 dark:text-slate-300 group-hover:text-slate-800 dark:group-hover:text-slate-100">{{ $action }}</span>
                                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 ml-auto font-mono">{{ $perm->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-400 dark:text-slate-500">No permissions available.</p>
                @endif
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
                        <a href="{{ route('admin.roles.index') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit" id="updateRoleBtn"
                                class="px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>
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

    document.querySelectorAll('.model-box').forEach(function(box) {
        var selectAll  = box.querySelector('.select-all-model');
        var checkboxes = box.querySelectorAll('.permission-checkbox');
        if (!checkboxes.length) { if (selectAll) selectAll.style.display = 'none'; return; }
        selectAll.checked = Array.from(checkboxes).every(function(c) { return c.checked; });
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(c) { c.checked = selectAll.checked; });
        });
        checkboxes.forEach(function(c) {
            c.addEventListener('change', function() {
                selectAll.checked = Array.from(checkboxes).every(function(x) { return x.checked; });
            });
        });
    });

    document.getElementById('role-edit-form').addEventListener('submit', function(e) {
        var name  = document.getElementById('name').value.trim();
        var perms = document.querySelectorAll('.permission-checkbox:checked');
        var valid = true;
        if (name.length < 3) { document.getElementById('name').classList.add('border-red-400'); valid = false; }
        else document.getElementById('name').classList.remove('border-red-400');
        if (perms.length === 0) { document.getElementById('permissions-error-client').classList.remove('hidden'); valid = false; }
        else document.getElementById('permissions-error-client').classList.add('hidden');
        if (!valid) e.preventDefault();
        else {
            var btn = document.getElementById('updateRoleBtn');
            btn.disabled = true;
            btn.innerHTML = window.loader || 'Saving...';
        }
    });

    document.querySelectorAll('.permission-checkbox').forEach(function(c) {
        c.addEventListener('change', function() {
            if (document.querySelectorAll('.permission-checkbox:checked').length > 0)
                document.getElementById('permissions-error-client').classList.add('hidden');
        });
    });
</script>
@endpush
