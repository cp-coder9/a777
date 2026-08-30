<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class MunicipalReadinessRefinementWorkspaceTest extends TestCase
{
    public function testWorkspaceExposesTheCompleteArchitexBackedPackWorkflow():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');$css=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css');
        foreach(['Property &amp; application profile','Application type','Ownership confirmed','Municipal readiness workflow','Open findings','Professional routes','Candidate supervision','Issue Architex-backed submission pack','Immutable pack history','does not constitute municipal approval','municipal-profile','/workflow','/findings/','/routes/','/packs/issue','municipal-submission-packs']as$token)self::assertStringContainsString($token,$shell);
        foreach(['municipalProfileForm','saveMunicipalProfile','renderMunicipalRefinement','resolveMunicipalFinding','assignMunicipalRoute','signOffMunicipalRoute','issueMunicipalPack']as$token)self::assertStringContainsString($token,$shell);
        foreach(['.municipal-profile-form','.municipal-refinement-dashboard','.municipal-finding-grid','.municipal-pack-history','.municipal-route-actions']as$token)self::assertStringContainsString($token,$css);
        self::assertStringContainsString('@media(max-width:760px)',$css);
        self::assertStringContainsString('[hidden]{display:none!important}',$css);
    }
}
