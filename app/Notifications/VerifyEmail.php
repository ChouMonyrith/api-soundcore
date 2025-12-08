<?php

namespace App\Notifications;
        
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends BaseVerifyEmail
{
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Verify Email Address')
                    ->greeting('Hello!')
                    ->line('Please click the button below to verify your email address.')
                    ->action('Verify Email Address', route('verification.verify'))
                    ->line('Thank you for using our application!');
    }
    
}
