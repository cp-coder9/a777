<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\CPD;

use Architex\Platform\CPD\CPDAssessmentService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CPDCertificateVerificationTest extends TestCase
{
    public static function revocationStates(): array
    {
        return ['valid'=>[null,true], 'revoked'=>['2026-09-06 12:00:00',false]];
    }

    #[DataProvider('revocationStates')]
    public function testPublicVerificationExposesOnlyAwardEvidence(?string $revokedAt, bool $valid): void
    {
        $code = str_repeat('A', 32);
        $row = [
            'id'=>str_repeat('b',32), 'organisation_id'=>str_repeat('c',16),
            'learner_user_id'=>str_repeat('d',16), 'attempt_id'=>str_repeat('e',16),
            'course_id'=>str_repeat('f',16), 'registration_number'=>'PRIVATE-REGISTRATION',
            'verification_code'=>$code, 'learner_name'=>'Example Learner',
            'professional_body'=>'SACAP', 'approved_category'=>'category_1',
            'credits_awarded'=>'0.1000', 'accreditation_reference'=>'EXAMPLE-001',
            'provider_name'=>'Example Provider', 'issued_at'=>'2026-09-05 12:00:00',
            'revoked_at'=>$revokedAt, 'future_private_column'=>'must not leak',
        ];
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with(['code'=>$code])->willReturn(true);
        $statement->method('fetch')->willReturn($row);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->willReturn($statement);

        self::assertSame([
            'verificationCode'=>$code, 'learnerName'=>'Example Learner',
            'professionalBody'=>'SACAP', 'approvedCategory'=>'category_1',
            'creditsAwarded'=>'0.1', 'accreditationReference'=>'EXAMPLE-001',
            'providerName'=>'Example Provider', 'issuedAt'=>'2026-09-05 12:00:00',
            'valid'=>$valid,
        ], (new CPDAssessmentService($pdo))->verifyCertificate($code));
    }
}
