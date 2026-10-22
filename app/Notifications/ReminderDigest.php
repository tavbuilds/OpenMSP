<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\Domain;
use App\Models\Endpoint;
use App\Models\PlannedTask;
use App\Support\Money;
use App\Support\PlatformSettings;
use App\Support\ReminderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Everything of one kind that needs attention, in one mail.
 *
 * Six domains expiring used to be six mails landing in the same minute,
 * which is the fastest way to teach someone to ignore all six. One mail
 * per kind is a list you read.
 *
 * Every word is produced in toMail(), never by the caller, because this
 * one digest goes to several people who may not read the same language.
 * Laravel sets each recipient's locale around this method, so the lines
 * come out in theirs — build a sentence any earlier and the first
 * recipient's language is the one everybody gets.
 */
class ReminderDigest extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const DOMAINS = 'domains';

    public const CERTIFICATES = 'certificates';

    public const CONTRACTS = 'contracts';

    public const TASKS = 'tasks';

    /**
     * @param  EloquentCollection<int, Model>  $records  what is due
     * @param  array<int, array{days: int|null, reason?: string}>  $context
     *                                                                       keyed by record id; days null once it is past
     */
    public function __construct(
        public string $kind,
        public EloquentCollection $records,
        public array $context = [],
        public bool $forCustomer = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $items = [];

        foreach ($this->records as $record) {
            $days = $this->context[$record->getKey()]['days'] ?? null;
            $items[] = $this->lineFor($record, $days);
        }

        // Soonest first, with whatever is already past at the very top:
        // this is a worklist, so it is ordered like one.
        usort($items, fn (ReminderItem $a, ReminderItem $b) => $a->days <=> $b->days);

        $count = count($items);

        return (new MailMessage)
            ->subject(PlatformSettings::name().': '.$this->subjectFor($count))
            ->markdown('mail.reminder-digest', [
                'salutation' => __('Hello :name,', ['name' => $notifiable->name ?? '']),
                'heading' => $this->headingFor($count),
                'items' => $items,
                'closing' => $this->forCustomer
                    ? __('Contact your administrator if you were not expecting any of this.')
                    : null,
            ]);
    }

    // --- One line per thing ---------------------------------------------

    private function lineFor(Model $record, ?int $days): ReminderItem
    {
        return match (true) {
            $record instanceof Domain => $this->domainLine($record, $days),
            $record instanceof Endpoint => $this->endpointLine($record, $days),
            $record instanceof Contract => $this->contractLine($record, $days),
            default => $this->taskLine($record, $days),
        };
    }

    private function domainLine(Domain $domain, ?int $days): ReminderItem
    {
        return new ReminderItem(
            title: $domain->name,
            when: $this->date($domain->expires_at),
            status: $days === null ? __('expired') : $this->inDays($days),
            days: $days ?? -1,
            meta: __('Customer: :company · Auto-renew: :state', [
                'company' => $domain->company?->name ?? __('not assigned'),
                'state' => $domain->auto_renew ? __('on') : __('off'),
            ]),
            url: url('/admin/domains/'.$domain->getKey()),
        );
    }

    private function endpointLine(Endpoint $endpoint, ?int $days): ReminderItem
    {
        $host = $endpoint->hostname ?: $endpoint->name;

        return new ReminderItem(
            title: $endpoint->name.' ('.$host.')',
            when: $this->date($endpoint->expires_at),
            status: $days === null ? __('expired') : $this->inDays($days),
            days: $days ?? -1,
            // The customer has no use for where we got it from.
            meta: $this->forCustomer ? null : __('Source: :source · Kind: :kind', [
                'source' => $endpoint->source?->getLabel() ?? __('—'),
                'kind' => $endpoint->kind?->getLabel() ?? __('—'),
            ]),
            url: $this->forCustomer ? null : url('/admin/endpoints/'.$endpoint->getKey()),
        );
    }

    private function contractLine(Contract $contract, ?int $days): ReminderItem
    {
        $reason = $this->context[$contract->getKey()]['reason'] ?? 'renewal';
        $days ??= 0;

        return new ReminderItem(
            title: $contract->name,
            when: $this->date($contract->renewal_date),
            status: $reason === 'notice'
                ? ($days === 0
                    ? __('notice period ends today')
                    : trans_choice('{1} notice period ends tomorrow|[2,*] notice period ends in :count days', $days, ['count' => $days]))
                : ($days === 0
                    ? __('renews today')
                    : trans_choice('{1} renews tomorrow|[2,*] renews in :count days', $days, ['count' => $days])),
            days: $days,
            meta: $this->forCustomer
                ? __('Customer: :company', ['company' => $contract->company?->name ?? __('not assigned')])
                : __('Customer: :company · Auto-renew: :state · Value: :value', [
                    'company' => $contract->company?->name ?? __('not assigned'),
                    'state' => $contract->auto_renew ? __('on') : __('off'),
                    'value' => Money::format($contract->total_sale, $contract->currency),
                ]),
            url: $this->forCustomer ? null : url('/admin/contracts/'.$contract->getKey()),
        );
    }

    private function taskLine(PlannedTask $task, ?int $days): ReminderItem
    {
        return new ReminderItem(
            title: $task->title,
            when: $this->date($task->due_on),
            status: $days === null ? __('the deadline has passed') : $this->inDays($days),
            days: $days ?? -1,
            meta: __('Customer: :company · Type: :kind · Status: :status', [
                'company' => $task->company?->name ?? __('internal'),
                'kind' => $task->kind?->getLabel() ?? __('—'),
                'status' => $task->status?->getLabel() ?? __('—'),
            ]),
            url: url('/admin/planned-tasks/'.$task->getKey()),
        );
    }

    // --- Words ------------------------------------------------------------

    private function inDays(int $days): string
    {
        return $days === 0
            ? __('today')
            : trans_choice('{1} tomorrow|[2,*] in :count days', $days, ['count' => $days]);
    }

    private function date(mixed $date): string
    {
        return $date?->translatedFormat('j F Y') ?? __('date unknown');
    }

    private function subjectFor(int $count): string
    {
        return match ($this->kind) {
            self::DOMAINS => trans_choice('{1} 1 domain needs attention|[2,*] :count domains need attention', $count, ['count' => $count]),
            self::CERTIFICATES => trans_choice('{1} 1 certificate needs attention|[2,*] :count certificates need attention', $count, ['count' => $count]),
            self::CONTRACTS => trans_choice('{1} 1 contract is coming up|[2,*] :count contracts are coming up', $count, ['count' => $count]),
            default => trans_choice('{1} 1 planned task is due|[2,*] :count planned tasks are due', $count, ['count' => $count]),
        };
    }

    private function headingFor(int $count): string
    {
        return match ($this->kind) {
            self::DOMAINS => trans_choice('{1} One domain needs your attention.|[2,*] These :count domains need your attention.', $count, ['count' => $count]),
            self::CERTIFICATES => trans_choice('{1} One certificate needs your attention.|[2,*] These :count certificates need your attention.', $count, ['count' => $count]),
            self::CONTRACTS => trans_choice('{1} One contract is coming up for renewal.|[2,*] These :count contracts are coming up for renewal.', $count, ['count' => $count]),
            default => trans_choice('{1} One planned task is due.|[2,*] These :count planned tasks are due.', $count, ['count' => $count]),
        };
    }
}
