<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogger
{
    public static function log($type, $action, $description, $user_id, $extraInfo = null)
    {
        ActivityLog::create([
            'type' => $type,
            'sub_type' => $action,
            'action' => $action,
            'description' => $description,
            'user_id' => $user_id,
            'extra_info' => $extraInfo,
        ]);
    }
}
