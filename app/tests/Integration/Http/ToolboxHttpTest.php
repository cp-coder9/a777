<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class ToolboxHttpTest extends MariaDbTestCase
{
    public function testStandaloneBoqCanBeCreatedThenAssignedToAnExternalReferenceThroughHttp(): void
    {
        [$organisationId,$ownerId]=$this->seedProject();$notifier=new class implements RegistrationNotifier,PasswordResetNotifier{public function isAvailable():bool{return false;}public function send(string$email,string$token,string$expiresAt):void{}};$kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();$session=(new SessionService($this->pdo))->rotate($ownerId);$headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];
        $created=$kernel->handle($this->jsonRequest('POST','/api/v1/toolbox/runs',$headers,['toolId'=>'boq_takeoff','input'=>['title'=>'HTTP standalone takeoff','currency'=>'ZAR','contingencyPercent'=>5,'items'=>[['description'=>'Brickwork','quantity'=>20,'unit'=>'m2','rate'=>350]]],'assignment'=>['mode'=>'none'],'issueImmediately'=>false]));
        self::assertSame(201,$created->status(),$created->body());$run=$created->json()['data'];self::assertSame(735000,$run['output']['totalCents']);
        $assigned=$kernel->handle($this->jsonRequest('POST','/api/v1/toolbox/runs/'.$run['id'].'/assignment',$headers,['mode'=>'external_reference','projectName'=>'Offline school','externalReference'=>'SCHOOL-EXT-9','notes'=>'Typed by the authenticated user.']));
        self::assertSame(200,$assigned->status(),$assigned->body());self::assertSame('SCHOOL-EXT-9',$assigned->json()['data']['assignment']['externalReference']);
    }

    public function testVerifiedFeeScheduleCanBeIssuedAndExportedThroughHttp(): void
    {
        [$organisationId, $ownerId, $projectId] = $this->seedProject();
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email, string $token, string $expiresAt): void {}
        };
        $kernel = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier))->create();
        $session = (new SessionService($this->pdo))->rotate($ownerId);
        $headers = [
            'Cookie' => 'architex_session=' . $session->token,
            'X-Organisation-Id' => $organisationId,
            'X-CSRF-Token' => $session->csrfToken,
        ];

        $source = $kernel->handle($this->jsonRequest('POST', '/api/v1/fee-sources', $headers, [
            'profession' => 'architect',
            'body' => 'Practice-approved internal estimating schedule.',
            'title' => 'HTTP fee schedule',
            'effectiveDate' => '2026-08-28',
            'metadata' => ['basis' => 'Not a statutory tariff.'],
        ]));
        self::assertSame(201, $source->status(), $source->body());
        $sourceId = $source->json()['data']['id'];

        $sourceVerification = $kernel->handle($this->jsonRequest(
            'POST',
            '/api/v1/fee-sources/' . $sourceId . '/verify',
            $headers,
            ['verificationReason' => 'Human reviewed.'],
        ));
        self::assertSame(200, $sourceVerification->status(), $sourceVerification->body());

        $definition = $kernel->handle($this->jsonRequest('POST', '/api/v1/fee-definitions', $headers, [
            'profession' => 'architect',
            'name' => 'HTTP fee schedule',
            'version' => '1.0.0',
            'formulaType' => 'percentage_of_cost',
            'sourceVersionId' => $sourceId,
            'configuration' => [
                'percentage' => 0.05,
                'vatRate' => 0.15,
                'complexities' => ['normal' => 1],
                'categories' => ['standard' => 1],
                'stages' => [['id' => 'all', 'name' => 'All services', 'weight' => 1]],
            ],
        ]));
        self::assertSame(201, $definition->status(), $definition->body());
        $definitionId = $definition->json()['data']['id'];

        $definitionVerification = $kernel->handle($this->jsonRequest(
            'POST',
            '/api/v1/fee-definitions/' . $definitionId . '/verify',
            $headers,
            ['verificationReason' => 'Formula reviewed.'],
        ));
        self::assertSame(200, $definitionVerification->status(), $definitionVerification->body());

        $run = $kernel->handle($this->jsonRequest('POST', '/api/v1/toolbox/runs', $headers, [
            'toolId' => 'professional_fee_calculator',
            'input' => [
                'definitionId' => $definitionId,
                'projectValue' => 1_000_000,
                'complexityId' => 'normal',
                'categorySplits' => ['standard' => 1],
                'selectedStages' => ['all' => ['applicable' => true, 'reduction' => 0]],
                'hourlyLines' => [],
                'unitLines' => [],
                'disbursements' => [['label' => 'Printing', 'amount' => 1_000]],
                'statutoryFees' => [['label' => 'Municipal fee', 'amount' => 5_000]],
                'discount' => ['percentage' => 0.1, 'reason' => 'Approved commercial adjustment.'],
                'vatApplicable' => true,
            ],
            'assignment' => ['mode' => 'internal_project', 'projectId' => $projectId],
            'issueImmediately' => true,
            'issueReason' => 'Responsible professional review completed.',
        ]));
        self::assertSame(201, $run->status(), $run->body());
        self::assertSame('issued', $run->json()['data']['status']);
        self::assertSame(57_900.0, $run->json()['data']['output']['totalInclVat']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $run->json()['data']['auditHash']);

        $runId = $run->json()['data']['id'];
        $exports = $kernel->handle(Request::json('GET', '/api/v1/toolbox/runs/' . $runId . '/exports', $headers));
        self::assertSame(200, $exports->status(), $exports->body());
        self::assertSame(['json', 'csv', 'html'], array_column($exports->json()['data'], 'format'));

        $jsonExport = $exports->json()['data'][0];
        $download = $kernel->handle(Request::json('GET', '/api/v1/toolbox/exports/' . $jsonExport['id'], $headers));
        self::assertSame(200, $download->status());
        self::assertSame('application/json', $download->header('Content-Type'));
        self::assertSame(hash('sha256', $download->body()), $download->header('X-Content-Sha256'));
        self::assertStringContainsString($runId, $download->body());
    }

    /** @return array{string,string,string} */
    private function seedProject(): array
    {
        $organisationId = Identifier::generate();
        $ownerId = Identifier::generate();
        $projectId = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'toolbox-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Toolbox HTTP Practice','toolbox-http-practice','active')")->execute(['id' => $organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','active')")->execute(['id' => Identifier::generate(), 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation_id),'TOOL-HTTP','Toolbox HTTP Project','Client','Tshwane','Erf 12','Residential','','architect',UNHEX(:user_id))")->execute(['id' => $projectId, 'organisation_id' => $organisationId, 'user_id' => $ownerId]);
        return [$organisationId, $ownerId, $projectId];
    }

    /** @param array<string,string> $headers @param array<string,mixed> $body */
    private function jsonRequest(string $method, string $path, array $headers, array $body): Request
    {
        return Request::json(
            $method,
            $path,
            $headers + ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }
}
