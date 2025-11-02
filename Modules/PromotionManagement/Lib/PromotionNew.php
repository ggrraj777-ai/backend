<?php


use Illuminate\Support\Facades\Http;
use Modules\UserManagement\Entities\AppNotification;
use Illuminate\Support\Facades\Cache;

if (!function_exists('sendDeviceNotification')) {
    function sendDeviceNotification($fcm_token, $title, $description, $status, $image = null, $ride_request_id = null, $type = null, $action = null, $user_id = null, $user_name = null, array $notificationData = []): bool|string
    {
        if ($user_id) {
            AppNotification::create([
                'user_id' => $user_id,
                'ride_request_id' => $ride_request_id,
                'title' => $title ?? 'Title Not Found',
                'description' => $description ?? 'Description Not Found',
                'type' => $type,
                'action' => $action,
            ]);
        }
        $imageUrl = $image ? asset("storage/app/public/push-notification/$image") : null;

        $postData = [
            'message' => [
                'token' => $fcm_token,
                'data' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    'status' => (string)$status,
                    "ride_request_id" => (string)$ride_request_id,
                    "type" => (string)$type,
                    "user_name" => (string)$user_name,
                    "title_loc_key" => (string)$ride_request_id,
                    "body_loc_key" => (string)$type,
                    "image" => (string)$imageUrl,
                    "action" => (string)$action,
                    "reward_type" => (string)($notificationData['reward_type'] ?? null),
                    "reward_amount" => (string)($notificationData['reward_amount'] ?? 0),
                    "next_level" => (string)($notificationData['next_level'] ?? null),
                    "sound" => "notification.wav",
                    "android_channel_id" => "hexaride"
                ],
                'notification' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "image" => (string)$imageUrl,
                ],
                "android" => [
                    'priority' => 'high',
                    "notification" => [
                        "channel_id" => "hexaride",
                        "sound" => "notification.wav",
                        "icon" => "notification_icon",
                    ]
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "notification.wav"
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ]
        ];
        return sendNotificationToHttp($postData);
    }
}

if (!function_exists('sendTopicNotification')) {
    function sendTopicNotification($topic, $title, $description, $image = null, $ride_request_id = null, $type = null, $sentBy = null, $tripReferenceId = null, $route = null): bool|string
    {

        $image = asset('storage/app/public/push-notification') . '/' . $image;
        $postData = [
            'message' => [
                'topic' => $topic,
                'data' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "ride_request_id" => (string)$ride_request_id,
                    "type" => (string)$type,
                    "title_loc_key" => (string)$ride_request_id,
                    "body_loc_key" => (string)$type,
                    "image" => (string)$image,
                    "sound" => "notification.wav",
                    "android_channel_id" => "hexaride",
                    "sent_by" => (string)$sentBy,
                    "trip_reference_id" => (string)$tripReferenceId,
                    "route" => (string)$route,
                ],
                'notification' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "image" => (string)$image,
                ],
                "android" => [
                    'priority' => 'high',
                    "notification" => [
                        "channelId" => "hexaride"
                    ]
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "notification.wav"
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ]
        ];
        return sendNotificationToHttp($postData);
    }
}

/**
 * @param string $url
 * @param string $postdata
 * @param array $header
 * @return bool|string
 */
function sendCurlRequest(string $url, string $postdata, array $header): string|bool
{
    $ch = curl_init();
    $timeout = 120;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    // Get URL content
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

function sendNotificationToHttp(array|null $data): bool|string|null
{
    $key = Cache::rememberForever('server_key', function () {
        return json_decode(businessConfig('server_key')->value);
    });

    $accessTokenData = Cache::get('firebase_access_token');

    if ($accessTokenData && isset($accessTokenData['access_token']) && isset($accessTokenData['expires_at'])) {
        $expiresAt = $accessTokenData['expires_at'];
        if ($expiresAt > time()) {
            $accessToken = $accessTokenData['access_token'];
        } else {
            $accessToken = fetchAndCacheAccessToken($key);
        }
    } else {
        $accessToken = fetchAndCacheAccessToken($key);
    }

    if (!$accessToken) {
        return false;
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . $key->project_id . '/messages:send';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/json',
    ];
    try {
        $response = Http::withHeaders($headers)->async()->post($url, $data)->wait();
        if ($response->successful()) {
            return true;
        }
        return false;
    } catch (\Exception $exception) {
        return false;
    }
}

function fetchAndCacheAccessToken($key)
{
    $accessTokenData = getAccessToken($key);

    if ($accessTokenData['status'] && isset($accessTokenData['data'])) {
        $expiresAt = time() + 3600;
        $data = [
            'access_token' => $accessTokenData['data'],
            'expires_at' => $expiresAt
        ];

        Cache::put('firebase_access_token', $data, 55);
        return $accessTokenData['data'];
    }

    return false;
}

function getAccessToken($key): array|string
{
    $jwtToken = [
        'iss' => $key->client_email,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => time() + 3600,
        'iat' => time(),
    ];
    $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $jwtPayload = base64_encode(json_encode($jwtToken));
    $unsignedJwt = $jwtHeader . '.' . $jwtPayload;
    openssl_sign($unsignedJwt, $signature, $key->private_key, OPENSSL_ALGO_SHA256);
    $jwt = $unsignedJwt . '.' . base64_encode($signature);

    $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);
    if ($response->failed()) {
        return [
            'status' => false,
            'data' => $response->json()
        ];

    }
    return [
        'status' => true,
        'data' => $response->json('access_token')
    ];
}
