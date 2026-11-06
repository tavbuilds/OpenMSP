<?php

namespace App\Mcp;

/**
 * MCP tool definitions. HTTP paths map onto the existing Agent API (/api/v1).
 *
 * @phpstan-type Tool array{
 *     name: string,
 *     description: string,
 *     inputSchema: array<string, mixed>,
 *     method: string,
 *     path: string
 * }
 */
final class ToolCatalog
{
    /** @var array<string, mixed> */
    private static array $pagination = [
        'search' => ['type' => 'string', 'description' => 'Case-insensitive text search'],
        'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Page size (default 15, max 100)'],
        'page' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Page number'],
        'sort' => ['type' => 'string', 'description' => 'Sort column from the resource allowlist'],
        'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'description' => 'Sort direction'],
    ];

    /**
     * @return list<Tool>
     */
    public static function tools(): array
    {
        return array_merge(
            self::dashboard(),
            self::companies(),
            self::contacts(),
            self::contracts(),
            self::products(),
            self::productComponents(),
            self::purchaseBundles(),
            self::vendors(),
            self::plannedTasks(),
            self::domains(),
            self::endpoints(),
            self::uptimeKuma(),
        );
    }

    /**
     * @return Tool|null
     */
    public static function find(string $name): ?array
    {
        foreach (self::tools() as $tool) {
            if ($tool['name'] === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public static function listForProtocol(): array
    {
        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'inputSchema' => $tool['inputSchema'],
        ], self::tools());
    }

    /**
     * @return list<Tool>
     */
    private static function dashboard(): array
    {
        return [
            self::tool('get_dashboard', 'Portfolio metrics (MRR/ARR/margin, active contracts, upcoming renewals, domain and endpoint expiries, and planning).', [], 'GET', '/dashboard'),
            self::tool('list_upcoming_renewals', 'Active contracts renewing within N days (default 30, max 365).', [
                'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                'per_page' => self::$pagination['per_page'],
                'page' => self::$pagination['page'],
            ], 'GET', '/contracts/upcoming-renewals'),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function companies(): array
    {
        $fields = [
            'name' => ['type' => 'string', 'description' => 'Company name'],
            'kvk_number' => ['type' => 'string'],
            'vat_number' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'address' => ['type' => 'string'],
            'postal_code' => ['type' => 'string'],
            'city' => ['type' => 'string'],
            'country' => ['type' => 'string'],
            'notes' => ['type' => 'string'],
        ];

        return [
            self::tool('list_companies', 'List/search companies.', self::$pagination + [
                'city' => ['type' => 'string', 'description' => 'Partial, case-insensitive city match'],
                'country' => ['type' => 'string'],
            ], 'GET', '/companies'),
            self::tool('get_company', 'Show a company by id.', ['id' => self::id('Company id')], 'GET', '/companies/{id}', ['id']),
            self::tool('create_company', 'Create a company (sales+).', $fields, 'POST', '/companies', ['name']),
            self::tool('update_company', 'Partial-update a company (sales+).', ['id' => self::id('Company id')] + $fields, 'PATCH', '/companies/{id}', ['id']),
            self::tool('delete_company', 'Delete a company (admin/manager).', ['id' => self::id('Company id')], 'DELETE', '/companies/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function contacts(): array
    {
        $fields = [
            'company_id' => self::id('Company id'),
            'name' => ['type' => 'string', 'description' => 'Contact name'],
            'email' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'job_title' => ['type' => 'string'],
            'is_primary' => ['type' => 'boolean'],
        ];

        return [
            self::tool('list_contacts', 'List/search contacts.', self::$pagination + [
                'company_id' => self::id('Company id'),
                'is_primary' => ['type' => 'boolean'],
            ], 'GET', '/contacts'),
            self::tool('list_company_contacts', 'List contacts nested under a company.', [
                'company_id' => self::id('Company id'),
                'search' => self::$pagination['search'],
                'is_primary' => ['type' => 'boolean'],
                'per_page' => self::$pagination['per_page'],
                'page' => self::$pagination['page'],
            ], 'GET', '/companies/{company_id}/contacts', ['company_id']),
            self::tool('get_contact', 'Show a contact by id.', ['id' => self::id('Contact id')], 'GET', '/contacts/{id}', ['id']),
            self::tool('create_contact', 'Create a contact (sales+).', $fields, 'POST', '/contacts', ['company_id', 'name']),
            self::tool('create_company_contact', 'Create a contact on a company via the nested route.', $fields, 'POST', '/companies/{company_id}/contacts', ['company_id', 'name']),
            self::tool('update_contact', 'Partial-update a contact (sales+).', ['id' => self::id('Contact id')] + $fields, 'PATCH', '/contacts/{id}', ['id']),
            self::tool('delete_contact', 'Delete a contact (admin/manager).', ['id' => self::id('Contact id')], 'DELETE', '/contacts/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function contracts(): array
    {
        $fields = [
            'company_id' => self::id('Company id'),
            'product_id' => self::id('Product id'),
            'vendor_id' => self::id('Vendor id'),
            'purchase_bundle_id' => self::id('Purchase bundle id'),
            'name' => ['type' => 'string'],
            'reference' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => ['license', 'support', 'subscription', 'service', 'other']],
            'quantity' => ['type' => 'integer', 'minimum' => 1],
            'cost_price' => ['type' => 'number', 'minimum' => 0],
            'sale_price' => ['type' => 'number', 'minimum' => 0],
            'currency' => ['type' => 'string'],
            'billing_cycle' => ['type' => 'string', 'enum' => ['monthly', 'quarterly', 'yearly', 'once']],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'renewal_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'notice_period_days' => ['type' => 'integer', 'minimum' => 0],
            'auto_renew' => ['type' => 'boolean'],
            'status' => ['type' => 'string', 'enum' => ['active', 'pending', 'cancelled', 'expired']],
            'next_invoice_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'cancelled_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'license_keys' => ['type' => 'string', 'description' => 'Encrypted at rest; sales+ only'],
            'notes' => ['type' => 'string'],
        ];

        return [
            self::tool('list_contracts', 'List/search contracts with Filament-parity filters.', self::$pagination + [
                'company_id' => self::id('Company id'),
                'product_id' => self::id('Product id'),
                'vendor_id' => self::id('Vendor id'),
                'purchase_bundle_id' => self::id('Purchase bundle id'),
                'status' => $fields['status'],
                'type' => $fields['type'],
                'billing_cycle' => $fields['billing_cycle'],
                'auto_renew' => ['type' => 'boolean'],
                'sale_min' => ['type' => 'number'],
                'sale_max' => ['type' => 'number'],
                'cost_min' => ['type' => 'number'],
                'cost_max' => ['type' => 'number'],
                'margin_min' => ['type' => 'number'],
                'margin_max' => ['type' => 'number'],
                'starts_after' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'starts_before' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'renews_after' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'renews_before' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'cancels_after' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'cancels_before' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'invoices_after' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'invoices_before' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            ], 'GET', '/contracts'),
            self::tool('get_contract', 'Show a contract by id. license_keys included for sales+ only.', ['id' => self::id('Contract id')], 'GET', '/contracts/{id}', ['id']),
            self::tool('create_contract', 'Create a contract (sales+).', $fields, 'POST', '/contracts', ['company_id', 'name', 'start_date']),
            self::tool('update_contract', 'Partial-update a contract (sales+).', ['id' => self::id('Contract id')] + $fields, 'PATCH', '/contracts/{id}', ['id']),
            self::tool('delete_contract', 'Delete a contract (admin/manager).', ['id' => self::id('Contract id')], 'DELETE', '/contracts/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function products(): array
    {
        $fields = [
            'vendor_id' => self::id('Vendor id'),
            'name' => ['type' => 'string'],
            'sku' => ['type' => 'string'],
            'type' => ['type' => 'string', 'description' => 'Product type enum'],
            'default_cost_price' => ['type' => 'number', 'minimum' => 0],
            'default_sale_price' => ['type' => 'number', 'minimum' => 0],
            'currency' => ['type' => 'string'],
            'billing_cycle' => ['type' => 'string', 'enum' => ['monthly', 'quarterly', 'yearly', 'once']],
            'description' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
        ];

        return [
            self::tool('list_products', 'List/search products.', self::$pagination + [
                'vendor_id' => self::id('Vendor id'),
                'type' => ['type' => 'string'],
                'billing_cycle' => $fields['billing_cycle'],
                'active' => ['type' => 'boolean'],
                'sale_min' => ['type' => 'number'],
                'sale_max' => ['type' => 'number'],
                'cost_min' => ['type' => 'number'],
                'cost_max' => ['type' => 'number'],
            ], 'GET', '/products'),
            self::tool('get_product', 'Show a product by id.', ['id' => self::id('Product id')], 'GET', '/products/{id}', ['id']),
            self::tool('create_product', 'Create a product (sales+).', $fields, 'POST', '/products', ['name']),
            self::tool('update_product', 'Partial-update a product (sales+).', ['id' => self::id('Product id')] + $fields, 'PATCH', '/products/{id}', ['id']),
            self::tool('delete_product', 'Delete a product (admin/manager).', ['id' => self::id('Product id')], 'DELETE', '/products/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function productComponents(): array
    {
        $fields = [
            'product_id' => self::id('Composite product (package) id'),
            'component_id' => self::id('Catalog product used as a part'),
            'quantity' => ['type' => 'integer', 'minimum' => 1],
        ];

        return [
            self::tool('list_product_components', 'List product composition (BOM) links.', self::$pagination + [
                'product_id' => self::id('Package id'),
                'component_id' => self::id('Part id'),
            ], 'GET', '/product-components'),
            self::tool('list_product_bom', 'List components nested under a product.', [
                'product_id' => self::id('Composite product id'),
                'per_page' => self::$pagination['per_page'],
                'page' => self::$pagination['page'],
            ], 'GET', '/products/{product_id}/components', ['product_id']),
            self::tool('get_product_component', 'Show a BOM link by id.', ['id' => self::id('Link id')], 'GET', '/product-components/{id}', ['id']),
            self::tool('create_product_component', 'Add a component to a package (sales+).', $fields, 'POST', '/product-components', ['product_id', 'component_id']),
            self::tool('create_product_bom_item', 'Add a component via the nested product route (sales+).', $fields, 'POST', '/products/{product_id}/components', ['product_id', 'component_id']),
            self::tool('update_product_component', 'Update a BOM link (sales+).', ['id' => self::id('Link id')] + $fields, 'PATCH', '/product-components/{id}', ['id']),
            self::tool('delete_product_component', 'Delete a BOM link (admin/manager).', ['id' => self::id('Link id')], 'DELETE', '/product-components/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function purchaseBundles(): array
    {
        $fields = [
            'vendor_id' => self::id('Vendor id'),
            'name' => ['type' => 'string'],
            'reference' => ['type' => 'string'],
            'total_cost' => ['type' => 'number'],
            'currency' => ['type' => 'string'],
            'billing_cycle' => ['type' => 'string', 'enum' => ['monthly', 'quarterly', 'yearly', 'once']],
            'allocation_method' => ['type' => 'string', 'enum' => ['even']],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'renewal_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'notes' => ['type' => 'string'],
        ];

        return [
            self::tool('list_purchase_bundles', 'List/search purchase (resell) bundles.', self::$pagination + [
                'vendor_id' => self::id('Vendor id'),
                'billing_cycle' => $fields['billing_cycle'],
                'allocation_method' => $fields['allocation_method'],
                'currency' => ['type' => 'string'],
                'cost_min' => ['type' => 'number'],
                'cost_max' => ['type' => 'number'],
                'starts_after' => ['type' => 'string'],
                'starts_before' => ['type' => 'string'],
                'renews_after' => ['type' => 'string'],
                'renews_before' => ['type' => 'string'],
            ], 'GET', '/purchase-bundles'),
            self::tool('get_purchase_bundle', 'Show a purchase bundle by id.', ['id' => self::id('Bundle id')], 'GET', '/purchase-bundles/{id}', ['id']),
            self::tool('create_purchase_bundle', 'Create a purchase bundle (sales+).', $fields, 'POST', '/purchase-bundles', ['name']),
            self::tool('update_purchase_bundle', 'Partial-update a purchase bundle (sales+).', ['id' => self::id('Bundle id')] + $fields, 'PATCH', '/purchase-bundles/{id}', ['id']),
            self::tool('delete_purchase_bundle', 'Delete a purchase bundle (admin/manager).', ['id' => self::id('Bundle id')], 'DELETE', '/purchase-bundles/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function vendors(): array
    {
        $fields = [
            'name' => ['type' => 'string'],
            'website' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'notes' => ['type' => 'string'],
        ];

        return [
            self::tool('list_vendors', 'List/search vendors.', self::$pagination, 'GET', '/vendors'),
            self::tool('get_vendor', 'Show a vendor by id.', ['id' => self::id('Vendor id')], 'GET', '/vendors/{id}', ['id']),
            self::tool('create_vendor', 'Create a vendor (sales+).', $fields, 'POST', '/vendors', ['name']),
            self::tool('update_vendor', 'Partial-update a vendor (sales+).', ['id' => self::id('Vendor id')] + $fields, 'PATCH', '/vendors/{id}', ['id']),
            self::tool('delete_vendor', 'Delete a vendor (admin/manager).', ['id' => self::id('Vendor id')], 'DELETE', '/vendors/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function plannedTasks(): array
    {
        $kind = ['type' => 'string', 'enum' => ['relocation', 'migration', 'onsite', 'project', 'other']];
        $status = ['type' => 'string', 'enum' => ['planned', 'in_progress', 'blocked', 'done', 'cancelled']];
        $priority = ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']];
        $fields = [
            'title' => ['type' => 'string'],
            'company_id' => self::id('Customer id'),
            'assigned_user_id' => self::id('Assignee user id'),
            'kind' => $kind,
            'status' => $status,
            'priority' => $priority,
            'due_on' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'location_from' => ['type' => 'string'],
            'location_to' => ['type' => 'string'],
            'notes' => ['type' => 'string'],
            'notify_30' => ['type' => 'boolean'],
            'notify_14' => ['type' => 'boolean'],
            'notify_7' => ['type' => 'boolean'],
            'notify_1' => ['type' => 'boolean'],
            'notify_expired' => ['type' => 'boolean'],
        ];

        return [
            self::tool('list_planned_tasks', 'List/search planned work (moves, migrations, on-site jobs).', self::$pagination + [
                'company_id' => self::id('Customer id'),
                'assigned_user_id' => self::id('Assignee user id'),
                'kind' => $kind,
                'status' => $status,
                'priority' => $priority,
                'due_after' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'due_before' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'open' => ['type' => 'boolean', 'description' => 'Exclude done/cancelled'],
                'overdue' => ['type' => 'boolean'],
            ], 'GET', '/planned-tasks'),
            self::tool('list_upcoming_planned_tasks', 'Open planned tasks due within N days (includes overdue).', [
                'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'description' => 'Horizon, default 60'],
            ], 'GET', '/planned-tasks/upcoming'),
            self::tool('get_planned_task', 'Show a planned task by id.', ['id' => self::id('Planned task id')], 'GET', '/planned-tasks/{id}', ['id']),
            self::tool('create_planned_task', 'Create a planned task (sales+).', $fields, 'POST', '/planned-tasks', ['title', 'due_on']),
            self::tool('update_planned_task', 'Partial-update a planned task (sales+).', ['id' => self::id('Planned task id')] + $fields, 'PATCH', '/planned-tasks/{id}', ['id']),
            self::tool('delete_planned_task', 'Delete a planned task (admin/manager).', ['id' => self::id('Planned task id')], 'DELETE', '/planned-tasks/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function domains(): array
    {
        $fields = [
            'name' => ['type' => 'string', 'description' => 'Domain name, stored lowercased (required on create)'],
            'extension' => ['type' => 'string', 'description' => 'Filled from the name when omitted'],
            'company_id' => self::id('Customer id'),
            'product_id' => self::id('Catalog product for this extension'),
            'expires_at' => ['type' => 'string', 'description' => 'Registry expiry YYYY-MM-DD'],
            'renewal_date' => ['type' => 'string', 'description' => 'Date to act on YYYY-MM-DD. Reminders use this, then expires_at'],
            'auto_renew' => ['type' => 'boolean'],
            'status' => ['type' => 'string', 'description' => 'Registrar status code, for a domain typed in by hand'],
            'notes' => ['type' => 'string'],
            'notes_visible_to_customer' => ['type' => 'boolean', 'description' => 'Off by default. A domain never emails the customer'],
            'notify_30' => ['type' => 'boolean'],
            'notify_14' => ['type' => 'boolean'],
            'notify_7' => ['type' => 'boolean'],
            'notify_1' => ['type' => 'boolean'],
            'notify_expired' => ['type' => 'boolean'],
        ];

        return [
            self::tool('list_domains', 'List/search domains. Default lifecycle=present hides domains the registrar no longer returns. Pass lifecycle=all or removed to see them.', self::$pagination + [
                'company_id' => self::id('Customer id'),
                'source' => ['type' => 'string', 'enum' => ['manual', 'openprovider']],
                'extension' => ['type' => 'string', 'description' => 'e.g. nl or co.uk'],
                'auto_renew' => ['type' => 'boolean'],
                'unassigned' => ['type' => 'boolean', 'description' => 'Only domains with no customer'],
                'expired' => ['type' => 'boolean'],
                'expires_within' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 3650, 'description' => 'Actionable date within N days, including already expired'],
                'lifecycle' => ['type' => 'string', 'enum' => ['present', 'removed', 'all'], 'description' => 'Default present'],
            ], 'GET', '/domains'),
            self::tool('list_upcoming_domain_expiries', 'Domains still at the registrar whose renewal (or expiry) falls within N days, including already expired. Default 60.', [
                'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                'per_page' => self::$pagination['per_page'],
                'page' => self::$pagination['page'],
            ], 'GET', '/domains/upcoming-expiries'),
            self::tool('get_domain', 'Show a domain by id, including days until the actionable date.', ['id' => self::id('Domain id')], 'GET', '/domains/{id}', ['id']),
            self::tool('create_domain', 'Create a domain typed in by hand (sales+). Openprovider domains arrive via sync, not this tool.', $fields, 'POST', '/domains', ['name']),
            self::tool('update_domain', 'Partial-update a domain (sales+). On an Openprovider domain only the customer, notes and reminder toggles stick; expiry and auto-renew belong to the registrar.', ['id' => self::id('Domain id')] + $fields, 'PATCH', '/domains/{id}', ['id']),
            self::tool('assign_domains', 'Assign many domains to one customer (sales+). This is the step after an Openprovider import.', [
                'company_id' => self::id('Customer id'),
                'ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'minItems' => 1,
                    'maxItems' => 100,
                    'description' => 'Domain ids',
                ],
            ], 'POST', '/domains/assign', ['company_id', 'ids']),
            self::tool('delete_domain', 'Delete a domain (admin/manager). A domain still at Openprovider comes back on the next sync.', ['id' => self::id('Domain id')], 'DELETE', '/domains/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function endpoints(): array
    {
        $fields = [
            'name' => ['type' => 'string', 'description' => 'e.g. VPN certificate (required on create)'],
            'company_id' => self::id('Customer id. Omit for an internal endpoint'),
            'kind' => ['type' => 'string', 'enum' => ['certificate', 'domain', 'other']],
            'hostname' => ['type' => 'string'],
            'url' => ['type' => 'string', 'description' => 'https://…'],
            'expires_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD. A monitor can fill this in later'],
            'notes' => ['type' => 'string'],
            'notify_30' => ['type' => 'boolean'],
            'notify_14' => ['type' => 'boolean'],
            'notify_7' => ['type' => 'boolean'],
            'notify_1' => ['type' => 'boolean'],
            'notify_expired' => ['type' => 'boolean'],
            'notify_customer' => ['type' => 'boolean', 'description' => 'Also email the customer contacts. Off by default'],
        ];

        return [
            self::tool('list_endpoints', 'List certificate and endpoint monitors. webhook_url is omitted here; get_endpoint returns it.', self::$pagination + [
                'company_id' => self::id('Customer id'),
                'kind' => $fields['kind'],
                'source' => ['type' => 'string', 'enum' => ['manual', 'webhook', 'uptime_kuma']],
                'status' => ['type' => 'string', 'enum' => ['ok', 'warning', 'expired', 'unknown']],
                'expired' => ['type' => 'boolean'],
                'expires_within' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 3650],
            ], 'GET', '/endpoints'),
            self::tool('list_upcoming_endpoints', 'Endpoints with an expiry inside N days, including already expired. Default 60.', [
                'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                'per_page' => self::$pagination['per_page'],
                'page' => self::$pagination['page'],
            ], 'GET', '/endpoints/upcoming'),
            self::tool('get_endpoint', 'Show an endpoint, including webhook_url. push_uptime_kuma_webhooks creates the Uptime Kuma HTTPS monitor when it is missing and attaches that URL.', ['id' => self::id('Endpoint id')], 'GET', '/endpoints/{id}', ['id']),
            self::tool('create_endpoint', 'Add an endpoint (sales+). push_uptime_kuma_webhooks then creates the Uptime Kuma HTTPS monitor when it is missing, attaches the webhook and turns that monitor’s certificate and domain expiry notifications off. OpenMSP sends the reminders.', $fields, 'POST', '/endpoints', ['name']),
            self::tool('update_endpoint', 'Partial-update an endpoint (sales+). A new expiry date restarts the reminder cycle. Returns webhook_url.', ['id' => self::id('Endpoint id')] + $fields, 'PATCH', '/endpoints/{id}', ['id']),
            self::tool('rotate_endpoint_webhook', 'Issue a new webhook URL (sales+). The previous URL stops working.', ['id' => self::id('Endpoint id')], 'POST', '/endpoints/{id}/rotate-webhook', ['id']),
            self::tool('delete_endpoint', 'Delete an endpoint (admin/manager). Also removes the matching Uptime Kuma monitor and the OpenMSP webhook. Two matches or a Kuma error leave the endpoint in place.', ['id' => self::id('Endpoint id')], 'DELETE', '/endpoints/{id}', ['id']),
        ];
    }

    /**
     * @return list<Tool>
     */
    private static function uptimeKuma(): array
    {
        return [
            self::tool('get_uptime_kuma', 'Whether a Uptime Kuma base URL, API key and dashboard login are stored. The key and password are never returned.', [], 'GET', '/integrations/uptime-kuma'),
            self::tool('set_uptime_kuma', 'Store the Uptime Kuma base URL, API key and dashboard login (admin/manager). The key and password are encrypted and are not echoed back. Send an empty string to clear a value. The API key only unlocks GET /metrics. The username and password are what add webhook URLs.', [
                'base_url' => ['type' => 'string', 'description' => 'https://status.example.com — without /metrics. A trailing /metrics is stripped'],
                'api_key' => ['type' => 'string', 'description' => 'Key from Uptime Kuma → Settings → Security → API Keys. Empty string clears it'],
                'username' => ['type' => 'string', 'description' => 'Dashboard login. Empty string clears it'],
                'password' => ['type' => 'string', 'description' => 'Dashboard password. Empty string clears it'],
            ], 'PUT', '/integrations/uptime-kuma'),
            self::tool('test_uptime_kuma', 'GET /metrics with the stored API key (admin/manager). Reports how many certificate monitors the scrape contains.', [], 'POST', '/integrations/uptime-kuma/test'),
            self::tool('push_uptime_kuma_webhooks', 'Create each endpoint webhook in Uptime Kuma and attach it to the matching monitor (admin/manager). When no monitor matches the URL, hostname or a domain-like name, OpenMSP creates an HTTPS monitor. Turns certificate and domain expiry notifications off. Other channels stay. Ambiguous matches are skipped. A domain that fails is tried up to 3 times, then stored so it can be sent again. failed_only sends only those. endpoint_ids sends only that selection. Requires the dashboard username and password, not the API key. Omit endpoint_id and endpoint_ids to do every endpoint.', [
                'endpoint_id' => self::id('Only this endpoint. Omit for every endpoint'),
                'endpoint_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Only these endpoints. Omit for every endpoint'],
                'failed_only' => ['type' => 'boolean', 'description' => 'Only domains whose last push failed. Default false'],
            ], 'POST', '/integrations/uptime-kuma/webhooks'),
            self::tool('sync_uptime_kuma', 'Import certificate expiry from Uptime Kuma /metrics into endpoints (admin/manager). Matches on URL, then hostname. create_missing=true adds an endpoint for each unmatched certificate monitor. The nightly job updates matches and does not create. This does not enable Kuma notifications.', [
                'create_missing' => ['type' => 'boolean', 'description' => 'Create endpoints for certificate monitors that do not match yet. Default false'],
            ], 'POST', '/integrations/uptime-kuma/sync'),
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return Tool
     */
    private static function tool(string $name, string $description, array $properties, string $method, string $path, array $required = []): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => $schema,
            'method' => $method,
            'path' => $path,
        ];
    }

    /**
     * @return array{type: string, description: string}
     */
    private static function id(string $description): array
    {
        return ['type' => 'integer', 'description' => $description];
    }
}
