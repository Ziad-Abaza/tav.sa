<?php

namespace Modules\ApiWebhookManager\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use Modules\ApiWebhookManager\Enums\ErrorCode;

class ApiResponse
{
    protected static function requestId(): string
    {
        return Str::uuid()->toString();
    }

    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        $response['meta'] = array_merge([
            'request_id' => self::requestId(),
            'timestamp' => now()->toIso8601String(),
        ], $meta);

        return response()->json($response, $status);
    }

    public static function error(
        ErrorCode $code,
        ?string $message = null,
        mixed $details = null,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code->value,
                'message' => $message ?? $code->message(),
            ],
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        $response['meta'] = array_merge([
            'request_id' => self::requestId(),
            'timestamp' => now()->toIso8601String(),
        ], $meta);

        return response()->json($response, $code->httpStatus());
    }

    public static function validationError(array $errors, ?string $message = null): JsonResponse
    {
        return self::error(
            ErrorCode::VALIDATION_ERROR,
            $message ?? 'The given data was invalid.',
            $errors
        );
    }

    public static function notFound(?string $message = null, ?string $resource = null): JsonResponse
    {
        $finalMessage = $message ?? 'The requested resource was not found.';

        if ($resource) {
            $finalMessage = ucfirst($resource).' not found.';
        }

        return self::error(ErrorCode::NOT_FOUND, $finalMessage);
    }

    public static function unauthorized(?string $message = null): JsonResponse
    {
        return self::error(
            ErrorCode::UNAUTHORIZED,
            $message ?? 'You are not authorized to perform this action.'
        );
    }

    public static function insufficientScope(?string $requiredScope = null): JsonResponse
    {
        $message = 'Your API token does not have the required permissions for this action.';

        if ($requiredScope) {
            $message .= " Required scope: {$requiredScope}";
        }

        return self::error(ErrorCode::INSUFFICIENT_SCOPE, $message);
    }

    public static function rateLimitExceeded(int $retryAfter): JsonResponse
    {
        return self::error(
            ErrorCode::RATE_LIMIT_EXCEEDED,
            "Too many requests. Please try again in {$retryAfter} seconds.",
            null,
            ['retry_after' => $retryAfter]
        );
    }

    public static function featureLimitExceeded(
        string $feature,
        int $current,
        int $maximum,
        ?string $message = null
    ): JsonResponse {
        return self::error(
            ErrorCode::FEATURE_LIMIT_EXCEEDED,
            $message ?? "Feature limit exceeded for {$feature}. Please upgrade your plan.",
            [
                'feature' => $feature,
                'current' => $current,
                'maximum' => $maximum,
            ]
        );
    }

    public static function collection(
        ResourceCollection $collection,
        ?array $meta = null
    ): JsonResponse {
        $data = $collection->response()->getData(true);

        $response = [
            'success' => true,
            'data' => $data['data'],
        ];

        $responseMeta = [
            'request_id' => self::requestId(),
            'timestamp' => now()->toIso8601String(),
        ];

        if (isset($data['meta'])) {
            $responseMeta = array_merge($responseMeta, $data['meta']);
        }

        if ($meta) {
            $responseMeta = array_merge($responseMeta, $meta);
        }

        $response['meta'] = $responseMeta;

        if (isset($data['links'])) {
            $response['links'] = $data['links'];
        }

        return response()->json($response);
    }

    public static function resource(JsonResource $resource, ?array $meta = null, int $status = 200): JsonResponse
    {
        $data = $resource->response()->getData(true);

        $response = [
            'success' => true,
            'data' => $data,
        ];

        $responseMeta = [
            'request_id' => self::requestId(),
            'timestamp' => now()->toIso8601String(),
        ];

        if ($meta) {
            $responseMeta = array_merge($responseMeta, $meta);
        }

        $response['meta'] = $responseMeta;

        return response()->json($response, $status);
    }

    public static function paginated(
        LengthAwarePaginator $paginator,
        ?callable $transformer = null
    ): JsonResponse {
        $items = $paginator->items();

        if ($transformer) {
            $items = array_map($transformer, $items);
        }

        $response = [
            'success' => true,
            'data' => $items,
            'meta' => [
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
                'request_id' => self::requestId(),
                'timestamp' => now()->toIso8601String(),
            ],
            'links' => [
                'self' => $paginator->url($paginator->currentPage()),
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];

        return response()->json($response);
    }

    public static function created(mixed $data = null, ?string $message = null, array $meta = []): JsonResponse
    {
        return self::success($data, $message ?? 'Resource created successfully.', 201, $meta);
    }

    public static function updated(mixed $data = null, ?string $message = null, array $meta = []): JsonResponse
    {
        return self::success($data, $message ?? 'Resource updated successfully.', 200, $meta);
    }

    public static function deleted(?string $message = null): JsonResponse
    {
        return self::success(null, $message ?? 'Resource deleted successfully.', 200);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
