<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Storage as FirebaseStorage;

/**
 * Firebase Storage Service
 * Handles document uploads to Firebase Storage
 */
class FirebaseStorageService
{
    protected $storage;
    protected $bucket;

    public function __construct()
    {
        try {
            // Initialize Firebase
            $firebaseCredentials = config('services.firebase.credentials');
            $storageBucket = config('services.firebase.storage_bucket');

            if ($firebaseCredentials && $storageBucket) {
                $factory = (new Factory)->withServiceAccount($firebaseCredentials);
                $this->storage = $factory->createStorage();
                $this->bucket = $this->storage->getBucket($storageBucket);
            }
        } catch (\Exception $e) {
            \Log::error('Firebase Storage initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload document to Firebase Storage
     * 
     * @param UploadedFile $file
     * @param string $path Base path (e.g., 'driver/documents/license')
     * @param string|null $driverId
     * @return array ['url' => 'https://...', 'path' => 'storage/path']
     */
    public function uploadDocument(
        UploadedFile $file,
        string $path,
        ?string $driverId = null
    ): array {
        try {
            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = $driverId 
                ? "{$driverId}_" . Str::uuid() . ".{$extension}"
                : Str::uuid() . ".{$extension}";
            
            $storagePath = "{$path}/{$filename}";

            // Upload to Firebase
            if ($this->bucket) {
                $fileContents = file_get_contents($file->getRealPath());
                $object = $this->bucket->upload($fileContents, [
                    'name' => $storagePath,
                    'metadata' => [
                        'contentType' => $file->getMimeType(),
                        'metadata' => [
                            'uploadedAt' => now()->toDateTimeString(),
                            'uploadedBy' => auth()->user()->id ?? 'system',
                            'originalName' => $file->getClientOriginalName(),
                        ]
                    ]
                ]);

                // Get public URL
                $url = $object->signedUrl(new \DateTime('+50 years'));

                return [
                    'url' => $url,
                    'path' => $storagePath,
                    'filename' => $filename,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }

            // Fallback to local storage if Firebase not configured
            return $this->uploadToLocalStorage($file, $path, $filename);
        } catch (\Exception $e) {
            \Log::error('Firebase upload failed: ' . $e->getMessage());
            // Fallback to local storage
            return $this->uploadToLocalStorage($file, $path, $filename ?? Str::uuid() . '.' . $file->getClientOriginalExtension());
        }
    }

    /**
     * Fallback: Upload to local storage
     */
    private function uploadToLocalStorage(UploadedFile $file, string $path, string $filename): array
    {
        $storagePath = "{$path}/{$filename}";
        $file->storeAs("public/{$path}", $filename);
        
        return [
            'url' => Storage::url("{$path}/{$filename}"),
            'path' => $storagePath,
            'filename' => $filename,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'storage' => 'local', // Indicate fallback
        ];
    }

    /**
     * Delete document from Firebase Storage
     */
    public function deleteDocument(string $path): bool
    {
        try {
            if ($this->bucket) {
                $object = $this->bucket->object($path);
                $object->delete();
                return true;
            }

            // Fallback to local storage
            Storage::delete("public/{$path}");
            return true;
        } catch (\Exception $e) {
            \Log::error('Firebase delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get document download URL
     */
    public function getDownloadUrl(string $path, int $expiresInMinutes = 60): ?string
    {
        try {
            if ($this->bucket) {
                $object = $this->bucket->object($path);
                $expiresAt = new \DateTime("+{$expiresInMinutes} minutes");
                return $object->signedUrl($expiresAt);
            }

            // Fallback to local storage URL
            return Storage::url($path);
        } catch (\Exception $e) {
            \Log::error('Get download URL failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if Firebase is configured
     */
    public function isConfigured(): bool
    {
        return $this->bucket !== null;
    }

    /**
     * Upload driver photo
     */
    public function uploadDriverPhoto(UploadedFile $file, string $driverId): array
    {
        return $this->uploadDocument($file, 'drivers/photos', $driverId);
    }

    /**
     * Upload license document
     */
    public function uploadLicense(UploadedFile $file, string $driverId, string $side = 'front'): array
    {
        return $this->uploadDocument($file, "drivers/licenses/{$side}", $driverId);
    }

    /**
     * Upload RC book
     */
    public function uploadRCBook(UploadedFile $file, string $driverId, string $side = 'front'): array
    {
        return $this->uploadDocument($file, "drivers/rc_books/{$side}", $driverId);
    }

    /**
     * Upload Aadhar card
     */
    public function uploadAadhar(UploadedFile $file, string $driverId, string $side = 'front'): array
    {
        return $this->uploadDocument($file, "drivers/aadhar/{$side}", $driverId);
    }
}

