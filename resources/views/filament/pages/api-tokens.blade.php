<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Tokens authenticate the Agent API and the built-in MCP endpoint
        (<code>/mcp</code>). The plaintext value is shown only once.
        Administrators can revoke other people’s tokens under
        <strong>Users</strong>.
    </p>

    @if (filled($this->plainTextToken))
        <div class="rounded-xl border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-950">
            <div class="text-sm font-medium">New token — copy it now</div>
            <code class="mt-2 block break-all text-sm">{{ $this->plainTextToken }}</code>
        </div>
    @endif

    <div class="overflow-x-auto">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
