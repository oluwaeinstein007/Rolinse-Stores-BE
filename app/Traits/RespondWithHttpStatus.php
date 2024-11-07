<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait RespondWithHttpStatus
{
    protected function success($message = 'Data fetched successfully!', $data = [], $meta = [], $status = 200): JsonResponse
    {
        $res = ['message' => $message];
        if ($data) $res['data'] = $data;
        if ($meta) $res['meta'] = $meta;
        return response()->json($res, $status);
    }

    protected function failure($message = 'An error occurred!', $details = null, $status = 422): JsonResponse
    {
        $res = ['error' => $message];
        if ($details) $res['details'] = $details;
        return response()->json($res, $status);
    }
}
