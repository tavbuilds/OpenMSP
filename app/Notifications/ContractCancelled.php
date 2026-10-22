<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\PlannedTask;
use App\Support\ContractCancellation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A customer has cancelled. Somebody has to stop the licence, the hosting
 * or the domain from renewing, and that is not something the platform can
 * do by itself — hence the dates, up front, and a task to go with it.
 */
class ContractCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Contract $contract,
        public ?PlannedTask $task = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cancellation = ContractCancellation::for($this->contract);
        $company = $this->contract->company?->name;

        $mail = (new MailMessage)
            ->subject(__('Cancelled: :contract (:company)', [
                'contract' => $this->contract->name,
                'company' => $company,
            ]))
            ->greeting(__('A customer has cancelled.'))
            ->line(__(':company cancelled :contract.', [
                'company' => $company,
                'contract' => $this->contract->name,
            ]));

        if ($ends = $cancellation->endsOn()) {
            $mail->line(__('The service runs until :date.', [
                'date' => $ends->translatedFormat('j F Y'),
            ]));
        }

        if ($invoice = $cancellation->lastInvoiceOn()) {
            $mail->line(__('The last invoice is on :date.', [
                'date' => $invoice->translatedFormat('j F Y'),
            ]));
        }

        // The thing that actually needs doing, said plainly.
        $mail->line(__('Make sure the licence, hosting or domain behind it is not renewed.'));

        if ($this->task) {
            $mail->action(__('Open the task'), url('/admin/planned-tasks/'.$this->task->getKey()));
        }

        return $mail;
    }
}
