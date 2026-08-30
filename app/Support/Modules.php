<?php

namespace App\Support;

/**
 * Single source of truth for TravelERP's sellable package module keys.
 *
 * A module key is also the literal suffix of a company-scoped `settings`
 * row: `Setting::getByKey($companyId, "module.{$key}")` (type = boolean)
 * turns that module on/off for one company.
 *
 * - Read path: App\Models\Company::hasModule() (per-request memoized).
 * - Write path: App\Support\Entitlements\ApplyCompanyModulePreset, which
 *   applies config('modules.package_preset') — built from these same
 *   constants — to a single company.
 * - Enforcement: App\Policies\Concerns\RequiresCompanyModule, mixed into
 *   every Policy class whose abilities belong to one of these modules.
 */
final class Modules
{
    /**
     * Task Uploader — AIR/PDF/email document ingestion, task lifecycle.
     */
    public const TASK_UPLOADER = 'task_uploader';

    /**
     * Payment Gateway — charges, invoices, auto-billing, refunds.
     */
    public const PAYMENT_GATEWAY = 'payment_gateway';

    /**
     * Customer CRM — clients and client-agent assignment.
     */
    public const CRM = 'crm';

    /**
     * Agent Profit Calculation — agent records, agent charge/loss
     * settings, settlement reporting.
     */
    public const AGENT_PROFIT = 'agent_profit';

    /**
     * Resayil WhatsApp CRM — external product, embedded later as an
     * in-app drawer. Included here so it has the same on/off flag shape
     * as the other 4 package modules even though nothing in this Phase 1
     * change gates anything behind it yet.
     */
    public const RESAYIL = 'resayil';

    /**
     * Accounting — the ledger, chart of accounts, and financial reports.
     * Always posts silently in the background regardless of this flag;
     * this only gates whether a company's users can SEE any of it.
     * Hidden by default for new package companies (see config/modules.php).
     */
    public const ACCOUNTING = 'accounting';

    /**
     * Every recognized module key, in the canonical order they are
     * presented in config/modules.php and to any future UI toggle list.
     *
     * @var string[]
     */
    public const ALL = [
        self::TASK_UPLOADER,
        self::PAYMENT_GATEWAY,
        self::CRM,
        self::AGENT_PROFIT,
        self::RESAYIL,
        self::ACCOUNTING,
    ];

    /**
     * The `settings.key` value that stores this module's on/off flag for
     * a company. Kept in one place so the "module." prefix is never
     * hand-typed (and never drifts) at any call site.
     */
    public static function settingKey(string $module): string
    {
        return "module.{$module}";
    }
}
