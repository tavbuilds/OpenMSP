<?php

namespace App\Support;

/**
 * One line of a reminder digest, in the reader's language.
 *
 * Built inside the notification rather than by the caller, because the
 * same digest goes to people who may not share a language. By the time
 * one of these exists, every string in it is already final.
 */
final class ReminderItem
{
    /**
     * @param  string  $title  what is expiring
     * @param  string  $when  the date, already formatted
     * @param  string  $status  how long is left: "in 7 days", "expired"
     * @param  int  $days  days left, for sorting; negative once past
     * @param  string|null  $meta  a second line of context
     * @param  string|null  $url  where to go and deal with it
     */
    public function __construct(
        public string $title,
        public string $when,
        public string $status,
        public int $days = 0,
        public ?string $meta = null,
        public ?string $url = null,
    ) {}
}
