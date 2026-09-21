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
            self::tool('get_dashboard', 'Portfolio metrics (MRR/ARR/margin, active contracts, upcoming renewals and planning).', [], 'GET', '/dashboard'),
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
