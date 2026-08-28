<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Engineering;

use Architex\Platform\Engineering\EngineeringCalculatorException;
use Architex\Platform\Engineering\EngineeringCalculatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EngineeringCalculatorServiceTest extends TestCase
{
    private EngineeringCalculatorService $service;

    protected function setUp(): void
    {
        $this->service = new EngineeringCalculatorService();
    }

    public function testBeamQuickCheckProducesTransparentFirstPrinciplesEvidence(): void
    {
        $output = $this->service->calculate('beam_quick_check', [
            'spanM' => 6,
            'udlKnPerM' => 10,
            'pointLoadKn' => 0,
            'elasticModulusMpa' => 200000,
            'inertiaMm4' => 100000000,
            'sectionModulusMm3' => 500000,
            'allowableStressMpa' => 165,
            'deflectionLimitRatio' => 300,
        ]);

        self::assertSame('beam_quick_check', $output['calculatorId']);
        self::assertSame(45.0, $output['results']['maximumMomentKnM']);
        self::assertSame(90.0, $output['results']['bendingStressMpa']);
        self::assertSame(8.4375, $output['results']['midspanDeflectionMm']);
        self::assertSame(20.0, $output['results']['deflectionLimitMm']);
        self::assertSame('within_user_limits', $output['results']['screeningOutcome']);
        self::assertSame('structural_engineer', $output['professionalRoleRequired']);
        $this->assertControlledEvidence($output);
    }

    public function testRunoffAndVoltageDropKeepTheirAssumptionsAndUnitsVisible(): void
    {
        $runoff = $this->service->calculate('rational_runoff_calculator', [
            'catchmentAreaM2' => 10000,
            'runoffCoefficient' => 0.8,
            'rainfallIntensityMmPerHour' => 100,
        ]);
        self::assertEqualsWithDelta(222.2222, $runoff['results']['peakFlowLitresPerSecond'], 0.0001);
        self::assertSame('civil_engineer', $runoff['professionalRoleRequired']);
        self::assertStringContainsString('time of concentration', implode(' ', $runoff['assumptions']));

        $voltage = $this->service->calculate('voltage_drop_calculator', [
            'phase' => 'three_phase',
            'nominalVoltageV' => 400,
            'currentA' => 100,
            'routeLengthM' => 50,
            'resistanceOhmPerKm' => 0.5,
            'reactanceOhmPerKm' => 0.08,
            'powerFactor' => 0.9,
            'maximumDropPercent' => 5,
        ]);
        self::assertEqualsWithDelta(4.199, $voltage['results']['voltageDropV'], 0.001);
        self::assertEqualsWithDelta(1.0498, $voltage['results']['voltageDropPercent'], 0.0001);
        self::assertSame('within_user_limit', $voltage['results']['screeningOutcome']);
        self::assertSame('electrical_engineer', $voltage['professionalRoleRequired']);
        $this->assertControlledEvidence($runoff);
        $this->assertControlledEvidence($voltage);
    }

    public function testQuantityRateAndProductivityCalculatorsAreDeterministic(): void
    {
        $concrete = $this->service->calculate('concrete_quantity_calculator', [
            'elements' => [
                ['reference' => 'F1', 'lengthM' => 10, 'widthM' => 0.5, 'depthM' => 0.3, 'quantity' => 2],
                ['reference' => 'S1', 'lengthM' => 5, 'widthM' => 4, 'depthM' => 0.15, 'quantity' => 1],
            ],
            'wastePercent' => 5,
        ]);
        self::assertSame(6.0, $concrete['results']['netVolumeM3']);
        self::assertSame(6.3, $concrete['results']['orderVolumeM3']);

        $masonry = $this->service->calculate('masonry_quantity_calculator', [
            'grossWallAreaM2' => 20,
            'openingsAreaM2' => 2,
            'unitLengthM' => 0.4,
            'unitHeightM' => 0.2,
            'wastePercent' => 10,
        ]);
        self::assertSame(18.0, $masonry['results']['netWallAreaM2']);
        self::assertSame(248, $masonry['results']['unitsToOrder']);

        $paint = $this->service->calculate('finishes_quantity_calculator', [
            'mode' => 'paint',
            'surfaceAreaM2' => 100,
            'coats' => 2,
            'coverageM2PerLitre' => 10,
            'wastePercent' => 10,
        ]);
        self::assertSame(22.0, $paint['results']['quantityToOrder']);
        self::assertSame('litres', $paint['results']['unit']);

        $rate = $this->service->calculate('tender_rate_build_up_calculator', [
            'quantity' => 10,
            'unit' => 'm2',
            'materialsPerUnit' => 100,
            'labourPerUnit' => 50,
            'plantPerUnit' => 20,
            'subcontractPerUnit' => 0,
            'overheadsPercent' => 10,
            'profitPercent' => 10,
            'riskPercent' => 5,
        ]);
        self::assertSame(215.99, $rate['results']['ratePerUnit']);
        self::assertSame(2159.85, $rate['results']['total']);

        $labour = $this->service->calculate('labour_productivity_calculator', [
            'quantity' => 120,
            'unit' => 'm2',
            'productivityPerCrewDay' => 30,
            'parallelCrews' => 2,
            'hoursPerDay' => 8,
        ]);
        self::assertSame(4.0, $labour['results']['crewDays']);
        self::assertSame(2.0, $labour['results']['elapsedWorkingDays']);
        self::assertSame(16.0, $labour['results']['elapsedWorkingHours']);

        foreach ([$concrete,$masonry,$paint,$rate,$labour] as $output) {
            $this->assertControlledEvidence($output);
        }
    }

    /** @return iterable<string,array{string,array<string,mixed>}> */
    public static function invalidInputs(): iterable
    {
        yield 'unknown calculator' => ['invented', []];
        yield 'zero beam span' => ['beam_quick_check', ['spanM'=>0,'udlKnPerM'=>1,'pointLoadKn'=>0,'elasticModulusMpa'=>1,'inertiaMm4'=>1,'sectionModulusMm3'=>1,'allowableStressMpa'=>1,'deflectionLimitRatio'=>1]];
        yield 'runoff coefficient too high' => ['rational_runoff_calculator', ['catchmentAreaM2'=>1,'runoffCoefficient'=>1.1,'rainfallIntensityMmPerHour'=>1]];
        yield 'invalid power factor' => ['voltage_drop_calculator', ['phase'=>'three_phase','nominalVoltageV'=>400,'currentA'=>1,'routeLengthM'=>1,'resistanceOhmPerKm'=>1,'reactanceOhmPerKm'=>0,'powerFactor'=>0,'maximumDropPercent'=>5]];
        yield 'empty concrete list' => ['concrete_quantity_calculator', ['elements'=>[],'wastePercent'=>0]];
        yield 'openings exceed wall' => ['masonry_quantity_calculator', ['grossWallAreaM2'=>5,'openingsAreaM2'=>6,'unitLengthM'=>0.4,'unitHeightM'=>0.2,'wastePercent'=>0]];
        yield 'unknown finish' => ['finishes_quantity_calculator', ['mode'=>'wallpaper']];
        yield 'negative rate' => ['tender_rate_build_up_calculator', ['quantity'=>1,'unit'=>'m2','materialsPerUnit'=>-1,'labourPerUnit'=>0,'plantPerUnit'=>0,'subcontractPerUnit'=>0,'overheadsPercent'=>0,'profitPercent'=>0,'riskPercent'=>0]];
        yield 'zero productivity' => ['labour_productivity_calculator', ['quantity'=>1,'unit'=>'m2','productivityPerCrewDay'=>0,'parallelCrews'=>1,'hoursPerDay'=>8]];
    }

    /** @param array<string,mixed> $input */
    #[DataProvider('invalidInputs')]
    public function testInvalidInputsFailClosed(string $toolId, array $input): void
    {
        $this->expectException(EngineeringCalculatorException::class);
        $this->service->calculate($toolId, $input);
    }

    /** @param array<string,mixed> $output */
    private function assertControlledEvidence(array $output): void
    {
        self::assertSame('1.0.0', $output['calculatorVersion']);
        self::assertNotSame('', $output['formulaVersion']);
        self::assertNotSame('', $output['sourceReference']);
        self::assertIsArray($output['assumptions']);
        self::assertIsArray($output['riskFlags']);
        self::assertTrue($output['issueEligible']);
        self::assertTrue($output['professionalSignoffRequired']);
        self::assertTrue($output['advisoryOnly']);
        self::assertFalse($output['certifiesCompliance']);
    }
}
