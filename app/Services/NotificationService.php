<?php

namespace App\Services;

use App\Models\Notification as ModelsNotification;
use App\Notifications\EmailNotification;
use App\Notifications\OTPNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Perform some general service logic.
     *
     * @param mixed $data
     * @return mixed
     */

    public function userNotification($user, $type, $subType, $title, $body, $is_email = true, $link = null, $actionText = null){
        if($link != null){
            $link = env('FRONTEND_BASE_URL') . $link;
        }
        $notification = $this->storeNotification($user, $type, $subType, $title, $body, $link, $actionText);
        if($is_email){
            $this->sendEmailNotification($user,$notification);
        }
        // $this->sendEmailNotification($user,$notification);
    }


    public function storeNotification($user, $type, $subType, $title, $body, $link = null, $actionText = null){
        $notification = ModelsNotification::create([
            'user_id' => $user['id'],
            'type' => $type,
            'sub_type' => $subType,
            'title' => $title,
            'body' => $body,
            'link' => $link,
        ]);
        //merge actionText
        $notification['actionText'] = $actionText;

        return $notification;
    }


    public function sendEmailNotification($user,$notification) {

        $mailDetails = [
            'greeting' => 'Hello!',
            'recipientName' => $user['full_name'] ?? 'There',
            'subject' => $notification['title'],
            'recipientEmail' => $user['email'],
            'intro' => $notification['body'],
            'actionText' => $notification['actionText'] ?? 'Notification Action',
            'actionUrl' => $notification['link'],
            'outro' => 'Thank you for choosing Maldorini!'
        ];

        Notification::route('mail', $mailDetails['recipientEmail'])
            ->notify(new EmailNotification($mailDetails));
    }

    public function sendOTPNotification($user,$notification) {

        $mailDetails = [
            'greeting' => 'Hello!',
            'recipientName' => $user['full_name'] ?? 'There',
            'subject' => $notification['title'],
            'recipientEmail' => $user['email'],
            'otp' => $notification['otp'],
            // 'intro' => $notification['body'],
            // 'actionText' => $notification['actionText'] ?? 'Notification Action',
            // 'actionUrl' => $notification['link'],
            'outro' => 'Thank you for choosing Maldorini!'
        ];

        Notification::route('mail', $mailDetails['recipientEmail'])
            ->notify(new OTPNotification($mailDetails));
    }


}
