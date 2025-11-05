<?php

namespace App\Swagger;

/**
 * @OA\Info(
 *     title="DriveMond API",
 *     version="1.0.0",
 *     description="OpenAPI documentation for DriveMond."
 * )
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Local Dev Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="apiKey",
 *     in="header",
 *     name="Authorization",
 *     description="Use format: Bearer {token}"
 * )
 */
class OpenApi
{
    /**
     * @OA\Get(
     *     path="/api/health",
     *     summary="Health check",
     *     tags={"System"},
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function healthExample() {}
}
