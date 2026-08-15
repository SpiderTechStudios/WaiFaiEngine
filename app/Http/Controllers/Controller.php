<?php

namespace App\Http\Controllers;

abstract class Controller
{

    public static function errror($data = [], $message = "Something went wrong", $code = 500)
    {
        return static::error($data, $message, $code);
    }

    public static function error($data = [], $message = "Something went wrong", $code = 500)
    {
        $response = [
            'status' => false,
            'code' => $code,
            'message' => $message,
        ];

        if (! empty($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    public static function success($data = [], $message = 'Success', $code = 200)
    {
        return response()->json([
            'status' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }


}
