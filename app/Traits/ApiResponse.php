<?php

namespace App\Traits;

trait ApiResponse
{
    protected function success($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error(string $message = 'Error', int $code = 400, $data = null)
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function paginated($data, string $message = 'Success')
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data->items(),
            'next' => $data->nextPageUrl(),
            'prev' => $data->previousPageUrl(),
            'total' => $data->total(),
        ]);
    }
}