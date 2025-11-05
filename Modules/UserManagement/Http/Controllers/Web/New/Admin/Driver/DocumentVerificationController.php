<?php

namespace Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver;

use App\Http\Controllers\BaseController;
use App\Services\FirebaseStorageService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentVerificationController extends BaseController
{
    protected $firebaseStorage;

    public function __construct(FirebaseStorageService $firebaseStorage)
    {
        $this->firebaseStorage = $firebaseStorage;
    }

    /**
     * Display list of drivers pending document verification
     */
    public function index(?Request $request = null, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        // Ensure request is not null
        $request = $request ?? request();
        
        $status = $request->get('status', 'pending');

        $drivers = DB::table('users')
            ->select('users.*', DB::raw('COUNT(driver_documents.id) as document_count'))
            ->leftJoin('driver_documents', 'users.id', '=', 'driver_documents.driver_id')
            ->where('users.user_type', 'driver')
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('users.document_verification_status', $status);
            })
            ->groupBy('users.id')
            ->orderBy('users.created_at', 'desc')
            ->paginate(20);

        return view('usermanagement::admin.driver.documents.index', compact('drivers', 'status'));
    }

    /**
     * Show driver documents for verification
     */
    public function show(string $driverId): View
    {
        $driver = DB::table('users')
            ->where('id', $driverId)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            Toastr::error('Driver not found');
            return redirect()->back();
        }

        $documents = DB::table('driver_documents')
            ->where('driver_id', $driverId)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('document_type');

        return view('usermanagement::admin.driver.documents.show', compact('driver', 'documents'));
    }

    /**
     * Approve specific document
     */
    public function approveDocument(Request $request, string $documentId): RedirectResponse
    {
        $document = DB::table('driver_documents')->where('id', $documentId)->first();

        if (!$document) {
            Toastr::error('Document not found');
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            DB::table('driver_documents')
                ->where('id', $documentId)
                ->update([
                    'verification_status' => 'approved',
                    'verified_by' => auth()->user()->id,
                    'verified_at' => now(),
                    'rejection_reason' => null,
                    'updated_at' => now(),
                ]);

            // Update driver overall status
            $this->updateDriverVerificationStatus($document->driver_id);

            DB::commit();
            Toastr::success('Document approved successfully');
            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error('Failed to approve document: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Reject specific document
     */
    public function rejectDocument(Request $request, string $documentId): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $document = DB::table('driver_documents')->where('id', $documentId)->first();

        if (!$document) {
            Toastr::error('Document not found');
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            DB::table('driver_documents')
                ->where('id', $documentId)
                ->update([
                    'verification_status' => 'rejected',
                    'verified_by' => auth()->user()->id,
                    'verified_at' => now(),
                    'rejection_reason' => $request->rejection_reason,
                    'updated_at' => now(),
                ]);

            // Update driver overall status
            $this->updateDriverVerificationStatus($document->driver_id);

            DB::commit();
            Toastr::success('Document rejected');
            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error('Failed to reject document: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Approve all driver documents
     */
    public function approveAll(string $driverId): RedirectResponse
    {
        DB::beginTransaction();
        try {
            DB::table('driver_documents')
                ->where('driver_id', $driverId)
                ->where('verification_status', 'pending')
                ->update([
                    'verification_status' => 'approved',
                    'verified_by' => auth()->user()->id,
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('users')
                ->where('id', $driverId)
                ->update([
                    'document_verification_status' => 'approved',
                    'documents_verified_at' => now(),
                    'is_active' => true,
                ]);

            DB::commit();
            Toastr::success('All documents approved and driver activated');
            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error('Failed to approve: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Download document
     */
    public function downloadDocument(string $documentId): \Illuminate\Http\Response|RedirectResponse
    {
        $document = DB::table('driver_documents')->where('id', $documentId)->first();

        if (!$document) {
            Toastr::error('Document not found');
            return redirect()->back();
        }

        try {
            // Get download URL from Firebase
            $path = $document->firebase_front_path;
            $downloadUrl = $this->firebaseStorage->getDownloadUrl($path, 60);

            if ($downloadUrl) {
                return redirect($downloadUrl);
            }

            Toastr::error('Document URL not available');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Download failed: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Update driver's overall verification status
     */
    private function updateDriverVerificationStatus(string $driverId): void
    {
        $documents = DB::table('driver_documents')
            ->where('driver_id', $driverId)
            ->whereNull('deleted_at')
            ->get();

        $requiredTypes = ['driving_license', 'rc_book', 'aadhar_card', 'photo'];
        $uploadedTypes = $documents->pluck('document_type')->toArray();
        $allUploaded = empty(array_diff($requiredTypes, $uploadedTypes));

        $approved = $documents->where('verification_status', 'approved');
        $allApproved = $approved->count() === count($requiredTypes);
        $anyRejected = $documents->where('verification_status', 'rejected')->count() > 0;

        if ($anyRejected) {
            $status = 'rejected';
            $isActive = false;
        } elseif ($allApproved) {
            $status = 'approved';
            $isActive = true;
        } elseif ($allUploaded) {
            $status = 'partial';
            $isActive = false;
        } else {
            $status = 'pending';
            $isActive = false;
        }

        DB::table('users')
            ->where('id', $driverId)
            ->update([
                'document_verification_status' => $status,
                'documents_verified_at' => $allApproved ? now() : null,
                'is_active' => $isActive,
            ]);
    }
}

