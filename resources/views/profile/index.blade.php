@extends('master.app')
@push('css')
    <style>
        .profile {
            font-size: 15px;
        }

        .media-body h6 {
            font-size: 13px;
        }
    </style>
@endpush
@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Profile Details',
            'icon' => 'bi bi-people',
            'sub_title' => [
                'Access Controller' => '',
                'Manage Admin User' => '',
                'Show' => '',
            ],
        ])
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="mb-3 card">
                <div class="card-body">
                    <div>
                        <img src="{{ $user->avatar ? cloudflareImage($user->avatar, 200) : cloudflareImage('eca4fbfc-baba-4ac2-0966-e8a13d097700', 200) }}"
                            class="rounded mx-auto d-block img-thumbnail" style="width: 200px;height:200px;" alt="User Avatar">
                        <h3 class=" border-gray pb-2 mb-0 profile">Profile :</h3>
                        <div class="media pt-3">
                            <div class="media-body pb-3 mb-0 lh-125  border-gray">
                                <h6>Name : {{ $user->name ?? '' }}</h6>
                            </div>
                        </div>
                        <div class="media pt-3">
                            <div class="media-body pb-3 mb-0 lh-125  border-gray">
                                <h6>E-mail : {{ $user->email ?? '' }}</h6>
                            </div>
                        </div>
                        <div class="media pt-3">
                            <div class="media-body pb-3 mb-0 lh-125  border-gray">
                                <h6>Designation : {{ $user->designation ?? 'N/A' }}</h6>
                            </div>
                        </div>
                        <div class="media pt-3">
                            <div class="media-body pb-3 mb-0 lh-125  border-gray">
                                <h6>Role :
                                    @if ($user?->roles?->isNotEmpty())
                                        <span class="badge bg-primary">
                                            {{ strtoupper($user->roles->first()->name) }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning">
                                            No role found 😢
                                        </span>
                                    @endif
                                </h6>
                            </div>
                        </div>
                        <div class="media pt-3">
                            <div class="media-body pb-3 mb-0 lh-125 border-gray">
                                <h6>Status :
                                    @if ($user->status == true)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="mb-3 card">
                <div class="card-body">
                    <h5 class=" border-gray pb-2 mb-0 profile">User others Information:</h5>
                    <table class="table">
                        <tbody>
                            <tr>
                                <td class="p-3">Join At :
                                    {{ $user->created_at ? $user->created_at->diffForHumans() : 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="p-3">Last Modify At :
                                    {{ $user->updated_at ? $user->updated_at->diffForHumans() : 'No modify' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
