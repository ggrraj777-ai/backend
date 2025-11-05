<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Services\FirebaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    protected $firebaseStorage;

    public function __construct(FirebaseStorageService $firebaseStorage)
    {
        $this->firebaseStorage = $firebaseStorage;
    }

    /**
     * Upload driving license
     * 
     * @OA\Post(
     *   path="/api/v1/driver/documents/license/upload",
     *   tags={"Driver Documents"},
     *   summary="Upload driving license (front and back)",
     *   security={{"sanctum":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         required={"front_image","license_number"},
     *         @OA\Property(property="front_image", type="string", format="binary"),
     *         @OA\Property(property="back_image", type="string", format="binary"),
     *         @OA\Property(property="license_number", type="string"),
     *         @OA\Property(property="expiry_date", type="string", format="date")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function uploadLicense(Request $request): JsonResponse
    {
        $request->validate([
            'front_image' => 'required|image|mimes:jpeg,jpg,png,pdf|max:5120',
            'back_image' => 'nullable|image|mimes:jpeg,jpg,png,pdf|max:5120',
            'license_number' => 'required|string|max:50',
            'expiry_date' => 'nullable|date|after:today',
        ]);

        return $this->uploadDriverDocument(
            documentType: 'driving_license',
            documentNumber: $request->license_number,
            frontImage: $request->file('front_image'),
            backImage: $request->file('back_image'),
            expiryDate: $request->expiry_date,
            metadata: [
                'license_number' => $request->license_number,
            ]
        );
    }

    /**
     * Upload RC book
     */
    public function uploadRCBook(Request $request): JsonResponse
    {
        $request->validate([
            'front_image' => 'required|image|mimes:jpeg,jpg,png,pdf|max:5120',
            'back_image' => 'nullable|image|mimes:jpeg,jpg,png,pdf|max:5120',
            'rc_number' => 'required|string|max:50',
            'expiry_date' => 'nullable|date|after:today',
        ]);

        return $this->uploadDriverDocument(
            documentType: 'rc_book',
            documentNumber: $request->rc_number,
            frontImage: $request->file('front_image'),
            backImage: $request->file('back_image'),
            expiryDate: $request->expiry_date,
            metadata: [
                'rc_number' => $request->rc_number,
                'vehicle_number' => $request->vehicle_number ?? null,
            ]
        );
    }

    /**
     * Upload Aadhar card
     */
    public function uploadAadhar(Request $request): JsonResponse
    {
        $request->validate([
            'front_image' => 'required|image|mimes:jpeg,jpg,png,pdf|max:5120',
            'back_image' => 'required|image|mimes:jpeg,jpg,png,pdf|max:5120',
            'aadhar_number' => 'required|string|size:12',
        ]);

        return $this->uploadDriverDocument(
            documentType: 'aadhar_card',
            documentNumber: $request->aadhar_number,
            frontImage: $request->file('front_image'),
            backImage: $request->file('back_image'),
            metadata: [
                'aadhar_number' => $request->aadhar_number,
            ]
        );
    }

    /**
     * Upload driver photo
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png|max:5120',
        ]);

        return $this->uploadDriverDocument(
            documentType: 'photo',
            frontImage: $request->file('photo'),
            metadata: [
                'type' => 'profile_photo',
            ]
        );
    }

    /**
     * Generic document upload handler
     */
    private function uploadDriverDocument(
        string $documentType,
        ?string $documentNumber = null,
        ?UploadedFile $frontImage = null,
        ?UploadedFile $backImage = null,
        ?string $expiryDate = null,
        array $metadata = []
    ): JsonResponse {
        $driverId = auth()->user()->id;

        DB::beginTransaction();
        try {
            // Upload front image to Firebase
            $frontData = null;
            if ($frontImage) {
                $frontData = $this->firebaseStorage->uploadDocument(
                    $frontImage,
                    "drivers/{$driverId}/{$documentType}/front",
                    $driverId
                );
            }

            // Upload back image to Firebase
            $backData = null;
            if ($backImage) {
                $backData = $this->firebaseStorage->uploadDocument(
                    $backImage,
                    "drivers/{$driverId}/{$documentType}/back",
                    $driverId
                );
            }

            // Check if document already exists
            $existing = DB::table('driver_documents')
                ->where('driver_id', $driverId)
                ->where('document_type', $documentType)
                ->first();

            $documentData = [
                'driver_id' => $driverId,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'front_image_url' => $frontData['url'] ?? null,
                'back_image_url' => $backData['url'] ?? null,
                'firebase_front_path' => $frontData['path'] ?? null,
                'firebase_back_path' => $backData['path'] ?? null,
                'expiry_date' => $expiryDate,
                'verification_status' => 'pending',
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ];

            if ($existing) {
                // Update existing document
                DB::table('driver_documents')
                    ->where('id', $existing->id)
                    ->update($documentData);
                $documentId = $existing->id;
            } else {
                // Create new document record
                $documentData['id'] = Str::uuid();
                $documentData['created_at'] = now();
                DB::table('driver_documents')->insert($documentData);
                $documentId = $documentData['id'];
            }

            // Update user document verification status
            $this->updateDriverVerificationStatus($driverId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => ucfirst(str_replace('_', ' ', $documentType)) . ' uploaded successfully',
                'document_id' => $documentId,
                'verification_status' => 'pending',
                'urls' => [
                    'front' => $frontData['url'] ?? null,
                    'back' => $backData['url'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Document upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Document upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get driver's uploaded documents
     */
    public function getDocuments(): JsonResponse
    {
        $driverId = auth()->user()->id;

        $documents = DB::table('driver_documents')
            ->where('driver_id', $driverId)
            ->whereNull('deleted_at')
            ->get();

        return response()->json([
            'success' => true,
            'documents' => $documents,
            'verification_status' => auth()->user()->document_verification_status ?? 'pending',
        ]);
    }

    /**
     * Delete document
     */
    public function deleteDocument(string $documentId): JsonResponse
    {
        $driverId = auth()->user()->id;

        $document = DB::table('driver_documents')
            ->where('id', $documentId)
            ->where('driver_id', $driverId)
            ->first();

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found',
            ], 404);
        }

        // Delete from Firebase
        if ($document->firebase_front_path) {
            $this->firebaseStorage->deleteDocument($document->firebase_front_path);
        }
        if ($document->firebase_back_path) {
            $this->firebaseStorage->deleteDocument($document->firebase_back_path);
        }

        // Soft delete
        DB::table('driver_documents')
            ->where('id', $documentId)
            ->update(['deleted_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * Update driver's overall document verification status
     */
    private function updateDriverVerificationStatus(string $driverId): void
    {
        $documents = DB::table('driver_documents')
            ->where('driver_id', $driverId)
            ->whereNull('deleted_at')
            ->get();

        // Required documents
        $requiredTypes = ['driving_license', 'rc_book', 'aadhar_card', 'photo'];
        $uploadedTypes = $documents->pluck('document_type')->toArray();

        $allUploaded = empty(array_diff($requiredTypes, $uploadedTypes));
        $allApproved = $documents->where('verification_status', 'approved')->count() === count($requiredTypes);
        $anyRejected = $documents->where('verification_status', 'rejected')->count() > 0;

        if ($anyRejected) {
            $status = 'rejected';
        } elseif ($allApproved) {
            $status = 'approved';
        } elseif ($allUploaded) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        DB::table('users')
            ->where('id', $driverId)
            ->update(['document_verification_status' => $status]);
    }
}

