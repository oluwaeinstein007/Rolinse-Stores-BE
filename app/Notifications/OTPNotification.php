<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class OTPNotification extends Notification
{
    use Queueable;
    protected $mailDetails;

    /**
     * Create a new notification instance.
     */
    public function __construct($mailDetails)
    {
        //
        $this->mailDetails = $mailDetails;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $token = $this->mailDetails['otp'];
        $template = <<<EOT
        <code>
            <h1>
                <div align="center" style="font-size: 40px !important;">{$token}</div>
            </h1>
        </code>
        EOT;

        return (new MailMessage)
        ->greeting('Hello, ' . $this->mailDetails['recipientName'] . '!')
        ->subject($this->mailDetails['subject'])
        ->line("Below is your one time password to complete your process. which will expire in 5 minutes.")
        ->line(new HtmlString($template))
        ->line('Please use this code for verification purposes.')
        ->line('If you did not request this OTP, there\'s nothing to worry about. You can safely ignore it.')
        ->line('Have any questions about Maldrini? Drop us a note at dev@stravel.live')
        ->line('Thank you for choosing Maldrini!');
            }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
