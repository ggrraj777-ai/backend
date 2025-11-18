@extends('adminmodule::layouts.master')

@section('title', translate('Business_Info'))

@section('content')
        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid">
                <h2 class="fs-22 mb-4 text-capitalize">{{translate('business_management')}}</h2>
                <div class="col-12 mb-3">
                    <div class="">
                        @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
                    </div>
                </div>
                <div class="card mb-3 text-capitalize">
                    <form action="{{route('admin.business.setup.trip-fare.store')."?type=".TRIP_FARE_SETTINGS}}" id="fare_and_penalty_form" method="POST">
                        @csrf
                        <div class="card-header">
                            <h5 class="d-flex align-items-center gap-2">
                                <i class="bi bi-person-fill-gear"></i>
                                {{ translate('fare_&_penalty_settings') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="mb-4">
                                        <label for="start_count_idle_fee" class="mb-2">{{ translate('start_count_idle_fee_after') }} ({{ translate('min') }})</label>
                                        <div class="input-group_tooltip">
                                            <input required type="number" class="form-control" placeholder="Ex: 5" id="start_count_idle_fee" name="idle_fee" value="{{$settings->where('key_name', 'idle_fee')->first()?->value}}">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                                    data-bs-title="{{ translate('The idle fee will be applied after the specified time (in minutes)') . '.' . translate('No fees will be charged for durations shorter than this time') }}"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-4">
                                        <label for="delay_fee" class="mb-2">{{ translate('start_count_delay_fee_after') }} ({{ translate('min') }})</label>
                                        <div class="input-group_tooltip">
                                            <input required type="number" class="form-control" placeholder="Ex: 5" id="delay_fee" name="delay_fee" value="{{$settings->firstWhere('key_name', 'delay_fee')?->value}}">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                                    data-bs-title="{{ translate('The delay fee will be applied after the specified time (in minutes)') . '. ' .translate('No fees will be charged for durations shorter than this time') }})"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Nighttime Fare Hike Section -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3"><i class="bi bi-moon-stars-fill"></i> {{ translate('Nighttime Fare Hike') }}</h6>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-4">
                                        <label for="nighttime_fare_status" class="mb-2">{{ translate('Enable Nighttime Fare Hike') }}</label>
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input" id="nighttime_fare_status" name="nighttime_fare_status" value="1" 
                                                {{ ($settings->firstWhere('key_name', 'nighttime_fare_status')?->value ?? 0) == 1 ? 'checked' : '' }}>
                                            <label class="form-check-label" for="nighttime_fare_status">{{ translate('Active') }}</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-4">
                                        <label for="nighttime_start" class="mb-2">{{ translate('Night Start Time') }}</label>
                                        <div class="input-group_tooltip">
                                            <input type="time" class="form-control" id="nighttime_start" name="nighttime_start_time" 
                                                value="{{ $settings->firstWhere('key_name', 'nighttime_start_time')?->value ?? '22:00' }}">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                                data-bs-title="{{ translate('Time when nighttime fare starts (e.g., 10:00 PM)') }}"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-4">
                                        <label for="nighttime_end" class="mb-2">{{ translate('Night End Time') }}</label>
                                        <div class="input-group_tooltip">
                                            <input type="time" class="form-control" id="nighttime_end" name="nighttime_end_time" 
                                                value="{{ $settings->firstWhere('key_name', 'nighttime_end_time')?->value ?? '06:00' }}">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                                data-bs-title="{{ translate('Time when nighttime fare ends (e.g., 6:00 AM)') }}"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-4">
                                        <label for="nighttime_percentage" class="mb-2">{{ translate('Fare Increase (%)') }}</label>
                                        <div class="input-group_tooltip">
                                            <input type="number" step="0.01" min="0" max="100" class="form-control" 
                                                placeholder="15-25" id="nighttime_percentage" name="nighttime_fare_percentage" 
                                                value="{{ $settings->firstWhere('key_name', 'nighttime_fare_percentage')?->value ?? 20 }}">
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                                data-bs-title="{{ translate('Percentage increase in fare during nighttime (recommended: 15-25%)') }}"></i>
                                        </div>
                                        <small class="text-muted">{{ translate('Recommended: 15-25%') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-3 flex-wrap justify-content-end">
                                <button type="submit" class="btn btn-primary text-uppercase">{{ translate('submit') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- End Main Content -->
@endsection

@push('script')

    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan
        $('#fare_and_penalty_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
    </script>

@endpush
