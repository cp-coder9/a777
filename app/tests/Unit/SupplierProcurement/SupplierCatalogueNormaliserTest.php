<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\SupplierProcurement;

use Architex\Platform\SupplierProcurement\SupplierCatalogueNormaliser;
use PHPUnit\Framework\TestCase;

final class SupplierCatalogueNormaliserTest extends TestCase
{
    public function testNormalisationAndRiskScoringAreDeterministicAndAdvisory():void
    {
        $normaliser=new SupplierCatalogueNormaliser();
        $item=$normaliser->normalise(['sku'=>' BR-01 ','name'=>' Clay Face Brick SANS 227 ','category'=>' Masonry ','unit'=>'Each','priceZar'=>8.5,'stockQuantity'=>5000,'leadTimeDays'=>4,'standards'=>['SANS 227'],'deliveryRegions'=>['Gauteng']]);
        self::assertSame('BR-01',$item['sku']);self::assertSame('masonry',$item['category']);self::assertSame('each',$item['unit']);self::assertSame(850,$item['priceCents']);
        $score=$normaliser->score(['description'=>'SANS compliant clay face brick','category'=>'masonry','unit'=>'each','quantity'=>1000,'requiredStandard'=>'SANS 227','requiredOnSiteDate'=>'2026-07-15'],$item,'gauteng','2026-06-01');
        self::assertGreaterThanOrEqual(80,$score['score']);self::assertSame('2026-07-11',$score['latestOrderDate']);self::assertSame([],$score['riskFlags']);self::assertTrue($score['advisoryOnly']);
        $risk=$normaliser->score(['description'=>'waterproofing membrane','category'=>'waterproofing','unit'=>'m2','quantity'=>200,'requiredStandard'=>'SANS 10400-L','requiredOnSiteDate'=>'2026-07-15'],$item,'western cape','2026-07-14');
        self::assertContains('standard_not_confirmed',$risk['riskFlags']);self::assertContains('substitution_requires_professional_approval',$risk['riskFlags']);self::assertContains('programme_date_at_risk',$risk['riskFlags']);
    }
}
