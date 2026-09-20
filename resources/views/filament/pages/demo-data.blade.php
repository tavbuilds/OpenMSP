<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Demo data is a sample portfolio (customers, licenses, a failed collection, upcoming
            notice dates). Every row is flagged <strong>is_demo</strong>. Real records are left
            alone. Remove it here or with <code>php artisan demo:purge</code>.
        </p>

        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <div class="text-sm font-medium">
                Status:
                @if ($this->loaded())
                    demo data is in the database
                @else
                    no demo data
                @endif
            </div>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                {{ $this->seedAction }}
                {{ $this->purgeAction }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
