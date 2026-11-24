<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds the Arabic, right-to-left mail shared by the booking notifications.
 *
 * Laravel's stock markdown theme aligns paragraphs to the left, which reads
 * wrong for Arabic, so notifications go through a dedicated RTL template
 * instead of the default `mail::message` view.
 */
trait BuildsArabicMail
{
    /**
     * @param  array<int, string>  $lines
     * @param  array{text: string, url: string}|null  $action
     */
    private function arabicMail(
        string $subject,
        string $heading,
        array $lines,
        ?array $action = null,
    ): MailMessage {
        return (new MailMessage)
            ->subject($subject)
            ->view(
                ['html' => 'mail.notification', 'text' => 'mail.notification-text'],
                [
                    'subject' => $subject,
                    'heading' => $heading,
                    'lines' => $lines,
                    'actionText' => $action['text'] ?? null,
                    'actionUrl' => $action['url'] ?? null,
                    'footer' => 'منصة حجوزات المصورين',
                ],
            );
    }
}
