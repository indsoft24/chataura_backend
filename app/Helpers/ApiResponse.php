<?php

namespace App\Helpers;

class ApiResponse
{
    /**
     * Return a successful response with data.
     */
    public static function success($data, $meta = null)
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response);
    }

    /**
     * Standard pagination meta for list endpoints. Frontend uses this to determine if more data is available.
     *
     * @param int $total Total number of items
     * @param int $currentPage Current page (1-based)
     * @param int $limit Per-page limit
     * @return array{total: int, current_page: int, last_page: int, limit: int}
     */
    public static function paginationMeta(int $total, int $currentPage, int $limit): array
    {
        $lastPage = $limit > 0 ? (int) max(1, ceil($total / $limit)) : 1;
        return [
            'total' => $total,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'limit' => $limit,
        ];
    }

    /**
     * Return an error response.
     */
    public static function error($code, $message, $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $statusCode);
    }

    /**
     * Return a 401 unauthorized error.
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        return self::error('UNAUTHORIZED', $message, 401);
    }

    /**
     * Return a 403 forbidden error.
     */
    public static function forbidden($message = 'Forbidden')
    {
        return self::error('FORBIDDEN', $message, 403);
    }

    /**
     * Return a 404 not found error.
     */
    public static function notFound($message = 'Resource not found')
    {
        return self::error('NOT_FOUND', $message, 404);
    }

    /**
     * Return a 409 conflict error.
     */
    public static function conflict($message = 'Conflict')
    {
        return self::error('CONFLICT', $message, 409);
    }

    /**
     * Return a validation error.
     */
    public static function validationError($message = 'Validation failed', $errors = [])
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $message,
            ],
        ];

        if (!empty($errors)) {
            $response['error']['errors'] = $errors;
        }

        return response()->json($response, 400);
    }
}

