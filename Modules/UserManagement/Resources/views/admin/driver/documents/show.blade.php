@extends('adminmodule::layouts.master')

@section('title', translate('Verify Driver Documents'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <!-- Driver Info Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                @if($driver->profile_image)
                                    <img src="{{ asset('storage/'.$driver->profile_image) }}" 
                                         alt="{{ $driver->first_name }}" 
                                         class="rounded-circle me-3"
                                         width="80" height="80"
                                         style="object-fit: cover;">
                                @endif
                                <div>
                                    <h3 class="mb-1">{{ $driver->first_name }} {{ $driver->last_name }}</h3>
                                    <p class="mb-1 text-muted">{{ $driver->email }}</p>
                                    <p class="mb-0"><strong>Phone:</strong> {{ $driver->phone }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="mb-2">
                                <strong>Verification Status:</strong>
                                @if($driver->document_verification_status === 'approved')
                                    <span class="badge bg-success fs-6">✓ Approved</span>
                                @elseif($driver->document_verification_status === 'rejected')
                                    <span class="badge bg-danger fs-6">✗ Rejected</span>
                                @elseif($driver->document_verification_status === 'partial')
                                    <span class="badge bg-warning fs-6">⚠ Partial</span>
                                @else
                                    <span class="badge bg-secondary fs-6">⏳ Pending</span>
                                @endif
                            </div>
                            <form action="{{ route('admin.driver.documents.approve-all', $driver->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success" 
                                        onclick="return confirm('Approve all documents and activate this driver?')">
                                    <i class="bi bi-check-circle"></i> Approve All & Activate
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Cards -->
            <div class="row g-4">
                @php
                    $documentTypes = [
                        'driving_license' => ['icon' => 'card-checklist', 'title' => 'Driving License', 'color' => 'primary'],
                        'rc_book' => ['icon' => 'file-earmark-text', 'title' => 'RC Book', 'color' => 'info'],
                        'aadhar_card' => ['icon' => 'person-badge', 'title' => 'Aadhar Card', 'color' => 'warning'],
                        'photo' => ['icon' => 'camera', 'title' => 'Driver Photo', 'color' => 'success']
                    ];
                @endphp

                @foreach($documentTypes as $type => $info)
                    @php
                        $doc = $documents->get($type)?->first();
                    @endphp
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header bg-{{ $info['color'] }} text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-{{ $info['icon'] }}"></i>
                                    {{ $info['title'] }}
                                </h5>
                            </div>
                            <div class="card-body">
                                @if($doc)
                                    <!-- Document Number -->
                                    @if($doc->document_number)
                                        <div class="mb-3">
                                            <strong>Document Number:</strong>
                                            <span class="badge bg-dark">{{ $doc->document_number }}</span>
                                        </div>
                                    @endif

                                    <!-- Expiry Date -->
                                    @if($doc->expiry_date)
                                        <div class="mb-3">
                                            <strong>Expiry Date:</strong>
                                            <span class="badge {{ \Carbon\Carbon::parse($doc->expiry_date)->isPast() ? 'bg-danger' : 'bg-info' }}">
                                                {{ \Carbon\Carbon::parse($doc->expiry_date)->format('M d, Y') }}
                                            </span>
                                        </div>
                                    @endif

                                    <!-- Document Images -->
                                    <div class="row mb-3">
                                        @if($doc->front_image_url)
                                            <div class="col-md-{{ $doc->back_image_url ? '6' : '12' }}">
                                                <label class="form-label fw-bold">Front Side:</label>
                                                <div class="position-relative">
                                                    <img src="{{ $doc->front_image_url }}" 
                                                         alt="Front" 
                                                         class="img-fluid rounded border"
                                                         style="cursor: pointer; max-height: 300px; width: 100%; object-fit: contain;"
                                                         onclick="viewImageModal('{{ $doc->front_image_url }}', '{{ $info['title'] }} - Front')">
                                                    <a href="{{ route('admin.driver.documents.download', $doc->id) }}" 
                                                       class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        @endif

                                        @if($doc->back_image_url)
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Back Side:</label>
                                                <div class="position-relative">
                                                    <img src="{{ $doc->back_image_url }}" 
                                                         alt="Back" 
                                                         class="img-fluid rounded border"
                                                         style="cursor: pointer; max-height: 300px; width: 100%; object-fit: contain;"
                                                         onclick="viewImageModal('{{ $doc->back_image_url }}', '{{ $info['title'] }} - Back')">
                                                    <a href="{{ route('admin.driver.documents.download', $doc->id) }}" 
                                                       class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Verification Status -->
                                    <div class="mb-3">
                                        <strong>Status:</strong>
                                        @if($doc->verification_status === 'approved')
                                            <span class="badge bg-success">✓ Approved</span>
                                        @elseif($doc->verification_status === 'rejected')
                                            <span class="badge bg-danger">✗ Rejected</span>
                                        @else
                                            <span class="badge bg-warning">⏳ Pending</span>
                                        @endif
                                    </div>

                                    <!-- Rejection Reason -->
                                    @if($doc->rejection_reason)
                                        <div class="alert alert-danger mb-3">
                                            <strong>Rejection Reason:</strong><br>
                                            {{ $doc->rejection_reason }}
                                        </div>
                                    @endif

                                    <!-- Action Buttons -->
                                    @if($doc->verification_status !== 'approved')
                                        <div class="d-flex gap-2">
                                            <form action="{{ route('admin.driver.documents.approve', $doc->id) }}" method="POST" class="flex-fill">
                                                @csrf
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-danger flex-fill" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#rejectModal{{ $doc->id }}">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="rejectModal{{ $doc->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject {{ $info['title'] }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="{{ route('admin.driver.documents.reject', $doc->id) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                                                                <textarea name="rejection_reason" class="form-control" rows="3" required 
                                                                          placeholder="Enter reason for rejection..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Reject Document</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        {{ $info['title'] }} not uploaded yet
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Image View Modal -->
    <div class="modal fade" id="imageViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageViewModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imageViewModalImage" src="" alt="" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";
        
        function viewImageModal(imageUrl, title) {
            document.getElementById('imageViewModalImage').src = imageUrl;
            document.getElementById('imageViewModalLabel').innerText = title;
            new bootstrap.Modal(document.getElementById('imageViewModal')).show();
        }
    </script>
@endpush

