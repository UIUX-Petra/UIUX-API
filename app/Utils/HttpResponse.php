<?php

namespace App\Utils;

use Illuminate\Support\Facades\Log;

trait HttpResponse
{


    protected function success($message = null, $data = null, $code = 200)
    {
        return response()->json([
            'code' => $code,
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }


    protected function error($message = null, $code = 500, $data = null)
    {
        return response()->json([
            'code' => $code,
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}

// https://developer.mozilla.org/en-US/docs/Web/HTTP/Status

class HttpResponseCode
{
    public const HTTP_OK = 200;

    public const HTTP_CREATED = 201;

    public const HTTP_ACCEPTED = 202;

    public const HTTP_NO_CONTENT = 204;

    public const HTTP_BAD_REQUEST = 400;

    public const HTTP_UNAUTHORIZED = 401;

    public const HTTP_FORBIDDEN = 403;

    public const HTTP_NOT_FOUND = 404;

    public const HTTP_METHOD_NOT_ALLOWED = 405;

    public const HTTP_NOT_ACCEPTABLE = 406;

    public const HTTP_CONFLICT = 409;
    
    public const HTTP_GONE = 410;

    public const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;

    public const HTTP_UNPROCESSABLE_ENTITY = 422;

    public const HTTP_TOO_MANY_REQUESTS = 429;

    public const HTTP_INTERNAL_SERVER_ERROR = 500;

    public const HTTP_SERVICE_UNAVAILABLE = 503;
}
