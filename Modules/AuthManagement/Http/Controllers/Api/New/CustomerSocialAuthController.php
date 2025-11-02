<?php

namespace Modules\AuthManagement\Http\Controllers\Api\New;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Service\Interface\CustomerServiceInterface;

class CustomerSocialAuthController extends Controller
{
    protected CustomerServiceInterface $customerService;

    public function __construct(CustomerServiceInterface $customerService)
    {
        $this->customerService = $customerService;
    }

    public function firebaseLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required',
            'fcm_token' => 'sometimes',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $idTokenString = $request->input('id_token');
        $auth = app('firebase.auth');
        if ($auth === false) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }

        try {
            $verifiedToken = $auth->verifyIdToken($idTokenString);
        } catch (\Throwable $e) {
            return response()->json(responseFormatter(DEFAULT_401), 403);
        }

        $claims = $verifiedToken->claims()->all();
        $uid = $claims['user_id'] ?? ($claims['uid'] ?? null);
        $email = $claims['email'] ?? null;
        $emailVerified = (bool)($claims['email_verified'] ?? false);
        $phone = $claims['phone_number'] ?? null;
        $name = $claims['name'] ?? null;
        $picture = $claims['picture'] ?? null;

        if (!$email && !$phone) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['error_code' => 400, 'message' => translate('email_or_phone_required')]]), 403);
        }

        // Find or create customer
        $user = null;
        if ($email) {
            $user = $this->customerService->findOneBy(criteria: ['email' => $email, 'user_type' => CUSTOMER]);
        }
        if (!$user && $phone) {
            $user = $this->customerService->findOneBy(criteria: ['phone' => $phone, 'user_type' => CUSTOMER]);
        }

        if (!$user) {
            $firstName = $name ? explode(' ', trim($name))[0] : 'User';
            $lastName = $name ? trim(substr($name, strlen($firstName))) : 'Firebase';
            $attributes = [
                'first_name' => $firstName ?: 'User',
                'last_name' => $lastName ?: 'Firebase',
                'email' => $email,
                'phone' => $phone,
                'profile_image' => 'def.png',
                'password' => bcrypt(rand(1000000, 9999999)),
                'user_type' => CUSTOMER,
            ];
            $user = $this->customerService->create($attributes);
        }

        // Update phone/email verification where applicable
        $updates = [];
        if ($phone && !$user->phone_verified_at) {
            $updates['phone_verified_at'] = now();
        }
        if ($request->filled('fcm_token')) {
            $updates['fcm_token'] = $request->input('fcm_token');
        }
        if (!empty($updates)) {
            $user = $this->customerService->update(id: $user->id, data: $updates);
        }

        $payload = [
            'token' => $user->createToken(CUSTOMER_PANEL_ACCESS)->accessToken,
            'is_active' => $user->is_active,
            'is_phone_verified' => is_null($user->phone_verified_at) ? 0 : 1,
            'is_profile_verified' => $user->isProfileVerified(),
        ];

        return response()->json(responseFormatter(AUTH_LOGIN_200, $payload), 200);
    }
}
