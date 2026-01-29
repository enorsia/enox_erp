@extends('master.app')

@section('content')
    <div class="top_title">
       @include('master.breadcrumb', [
            'title' => 'Roles',
        ])
        <div>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-outline-secondary">
                Create <span><i class="bi bi-plus-lg me-0"></i></span>
            </a>
        </div>
    </div>

    <div class="card shadow-sm mt-3" style="overflow: hidden;">
        <div class="card-body">
            <h5 class="fw-semibold mb-3">Assigned Permissions</h5>
            @if (!empty($nested) && count($nested))
                <div class="accordion" id="rolePermissionsAccordion">
                    @foreach ($nested as $moduleIndex => $models)
                        @php
                            $module = $moduleIndex;
                            $collapseId = 'collapseModule' . $loop->index;
                            $headingId = 'headingModule' . $loop->index;
                        @endphp

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="{{ $headingId }}">
                                <button class="accordion-button fw-medium {{ !$loop->first ? 'collapsed' : '' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                    aria-controls="{{ $collapseId }}">
                                    {{ ucfirst($module) }} Module
                                </button>
                            </h2>

                            <div id="{{ $collapseId }}"
                                class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                aria-labelledby="{{ $headingId }}" data-bs-parent="#rolePermissionsAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        @foreach ($models as $model => $modelPermissions)
                                            <div class="col-lg-6">
                                                <div class="border rounded-3 bg-light-subtle p-3 shadow-sm h-100">
                                                    <strong
                                                        class="text-capitalize text-primary mb-2 d-block">{{ $model }}
                                                        Model</strong>
                                                    <hr class="my-2">
                                                    @foreach ($modelPermissions as $perm)
                                                        @php
                                                            $parts = explode('.', $perm->name);
                                                            $action = ucfirst(end($parts));
                                                        @endphp
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-2">
                                                            <span>{{ $action }}</span>
                                                            <small class="text-muted">{{ $perm->name }}</small>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-muted">No permissions assigned to this role.</div>
            @endif
        </div>
    </div>
@endsection
