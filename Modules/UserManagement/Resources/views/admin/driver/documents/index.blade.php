@extends('adminmodule::layouts.master')

@section('title', translate('Driver Document Verification'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fs-22 text-capitalize">{{ translate('Driver Document Verification') }}</h2>
                <div class="btn-group">
                    <a href="{{ route('admin.driver.documents.index', ['status' => 'pending']) }}" 
                       class="btn btn-sm {{ $status === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">
                        Pending
                    </a>
                    <a href="{{ route('admin.driver.documents.index', ['status' => 'partial']) }}" 
                       class="btn btn-sm {{ $status === 'partial' ? 'btn-warning' : 'btn-outline-warning' }}">
                        Partial
                    </a>
                    <a href="{{ route('admin.driver.documents.index', ['status' => 'approved']) }}" 
                       class="btn btn-sm {{ $status === 'approved' ? 'btn-success' : 'btn-outline-success' }}">
                        Approved
                    </a>
                    <a href="{{ route('admin.driver.documents.index', ['status' => 'rejected']) }}" 
                       class="btn btn-sm {{ $status === 'rejected' ? 'btn-danger' : 'btn-outline-danger' }}">
                        Rejected
                    </a>
                    <a href="{{ route('admin.driver.documents.index', ['status' => 'all']) }}" 
                       class="btn btn-sm {{ $status === 'all' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                        All
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Driver ID</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Documents</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($drivers as $driver)
                                    <tr>
                                        <td>#{{ substr($driver->id, 0, 8) }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @if($driver->profile_image)
                                                    <img src="{{ asset('storage/'.$driver->profile_image) }}" 
                                                         alt="{{ $driver->first_name }}" 
                                                         class="rounded-circle me-2"
                                                         width="40" height="40"
                                                         style="object-fit: cover;">
                                                @else
                                                    <div class="bg-secondary rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px;">
                                                        <span class="text-white">{{ substr($driver->first_name, 0, 1) }}</span>
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="fw-bold">{{ $driver->first_name }} {{ $driver->last_name }}</div>
                                                    <small class="text-muted">{{ $driver->email }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $driver->phone }}</td>
                                        <td>
                                            <span class="badge bg-info">{{ $driver->document_count }} uploaded</span>
                                        </td>
                                        <td>
                                            @if($driver->document_verification_status === 'approved')
                                                <span class="badge bg-success">✓ Approved</span>
                                            @elseif($driver->document_verification_status === 'rejected')
                                                <span class="badge bg-danger">✗ Rejected</span>
                                            @elseif($driver->document_verification_status === 'partial')
                                                <span class="badge bg-warning">⚠ Partial</span>
                                            @else
                                                <span class="badge bg-secondary">⏳ Pending</span>
                                            @endif
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($driver->created_at)->format('M d, Y') }}</td>
                                        <td>
                                            <a href="{{ route('admin.driver.documents.show', $driver->id) }}" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View Documents
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            No drivers found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-3">
                        {{ $drivers->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

