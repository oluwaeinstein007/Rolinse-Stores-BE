<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Level;
use App\Models\Setting;
use App\Models\Transaction;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use GuzzleHttp\Client;

class GeneralService
{
    /**
     * Perform some general service logic.
     *
     * @param mixed $data
     * @return mixed
     */

    public function generateReferralCode($name){
        $code = Str::random(5); // You can adjust the length as needed
        $referalCode = $name. '-' . $code;
        return $referalCode;
    }


    public function generateRolinseId(){
        $timeNow = (microtime(true)*10000);
        return 'REF'.$timeNow;
    }


    public function roundNum($num) {
        return number_format($num, 2, '.', '');
    }


    public function decryptService ($code){
        $decrypted = Crypt::decryptString($code);
        return $decrypted;
    }


    public function encryptService ($value){
        $encrypt = Crypt::encryptString($value);
        return $encrypt;
    }


}
