<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class EmailNotification extends Notification
{
    use Queueable;
    protected $mailDetails;

    /**
     * Create a new notification instance.
     */
    public function __construct($mailDetails)
    {
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
        return (new MailMessage)
                    ->greeting('Hello, ' . $this->mailDetails['recipientName'] . '!')
                    ->subject($this->mailDetails['subject'])
                    ->line($this->mailDetails['intro'])
                    ->action($this->mailDetails['actionText'], $this->mailDetails['actionUrl'])
                    // ->line(new HtmlString($template))
                    ->line($this->mailDetails['outro']);
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
