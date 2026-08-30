<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

final class SupplierProcurementMigrationAssetTest extends TestCase
{
    public function testMigrationCreatesTenantScopedLifecycleTables():void
    {
        $sql=file_get_contents(dirname(__DIR__,3).'/database/migrations/0052_supplier_procurement_integration.sql');self::assertIsString($sql);
        foreach(['supplier_profiles','supplier_catalogue_revisions','supplier_catalogue_items','supplier_procurement_baselines','supplier_procurement_baseline_lines','supplier_integration_packages','supplier_integration_package_lines','supplier_catalogue_matches','supplier_rfqs','supplier_quote_revisions','supplier_substitution_decisions','supplier_award_decisions','supplier_purchase_orders','supplier_deliveries','supplier_site_acceptances','supplier_invoices','supplier_payment_recommendations']as$table)self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        foreach(['organisation_id','project_id','snapshot_sha256','token_hash','idempotency_key','moves_money']as$guard)self::assertStringContainsString($guard,$sql);
        self::assertStringNotContainsString('uq_supplier_rfq (package_id,supplier_id)',$sql);
        self::assertStringNotContainsString('DROP TABLE',$sql);
    }
}
