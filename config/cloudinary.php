<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary URL
    |--------------------------------------------------------------------------
    |
    | Credentials for the official Cloudinary PHP SDK, in the form
    | cloudinary://API_KEY:API_SECRET@CLOUD_NAME. The SDK instance is bound in
    | App\Providers\AppServiceProvider. When this is empty the SDK falls back
    | to reading the CLOUDINARY_URL environment variable itself.
    |
    */

    'cloud_url' => env('CLOUDINARY_URL'),

];
