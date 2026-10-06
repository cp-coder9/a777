<?php
declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure;

use Architex\Infrastructure\Integration\BridgeConfig;
use Architex\Infrastructure\Integration\BridgeClient;
use Architex\Infrastructure\Integration\BridgeTransport;
use Architex\Infrastructure\Integration\IntegrationException;
use Architex\Infrastructure\Integration\SignedCallback;
use Architex\Infrastructure\Integration\CurlBridgeTransport;
use Architex\Platform\Finance\ProviderLifecycle;
use PHPUnit\Framework\TestCase;

final class IntegrationContractTest extends TestCase
{
    public function testMissingConfigurationFailsClosed(): void
    {
        $this->expectException(IntegrationException::class);
        BridgeConfig::fromValues([]);
    }

    public function testUnsafeEndpointAndHeaderInjectionAreRejected(): void
    {
        foreach (['http://bridge.example', 'https://user:pass@bridge.example', 'https://bridge.example?q=x', 'https://bridge.example/#x', 'https://127.0.0.1', "https://bridge.example\r\nX: y"] as $url) {
            try { BridgeConfig::fromValues(['URL'=>$url, 'TOKEN'=>str_repeat('x', 32), 'WEBHOOK_SECRET'=>str_repeat('y', 32)]); self::fail($url); }
            catch (IntegrationException $e) { self::assertSame('integration_configuration_invalid', $e->reason); }
        }
    }

    public function testAuthenticatedContractChecksScopeAndNeverInventsSuccess(): void
    {
        $transport = new class implements BridgeTransport {
            public array $calls = [];
            public function request(string $method, string $url, array $headers, string $body): array
            {
                $this->calls[] = [$method,$url,$headers,$body];
                $scope = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
                return ['status'=>202,'body'=>json_encode($scope+['contract'=>'architex.integration.v1','providerReference'=>'fixture-only-1','status'=>'accepted'], JSON_THROW_ON_ERROR)];
            }
        };
        $config = BridgeConfig::fromValues(['URL'=>'https://bridge.example/v1', 'TOKEN'=>str_repeat('x',32), 'WEBHOOK_SECRET'=>str_repeat('y',32)]);
        $client = new BridgeClient($config, $transport);
        $scope = ['organisationId'=>str_repeat('a',32),'providerId'=>str_repeat('b',32),'releaseRequestId'=>str_repeat('c',32),'amountCents'=>123,'currency'=>'ZAR'];
        $result = $client->call('POST','/releases',$scope,'release-'.$scope['releaseRequestId']);
        self::assertSame('accepted', $result['status']);
        self::assertSame('Bearer '.str_repeat('x',32), $transport->calls[0][2]['Authorization']);
        self::assertSame('release-'.$scope['releaseRequestId'], $transport->calls[0][2]['Idempotency-Key']);
        self::assertArrayNotHasKey('providerPaidCents', $result);
    }

    public function testWrongTenantResponseIsRejected(): void
    {
        $transport = new class implements BridgeTransport {
            public function request(string $method, string $url, array $headers, string $body): array {
                return ['status'=>200,'body'=>'{"contract":"architex.integration.v1","organisationId":"wrong"}'];
            }
        };
        $client = new BridgeClient(BridgeConfig::fromValues(['URL'=>'https://bridge.example','TOKEN'=>str_repeat('x',32),'WEBHOOK_SECRET'=>str_repeat('y',32)]), $transport);
        $this->expectException(IntegrationException::class);
        $client->call('GET','/capabilities',['organisationId'=>str_repeat('a',32)]);
    }

    public function testSignedCallbackAuthenticatesRawBodyBeforeParsing(): void
    {
        $secret = str_repeat('fixture-secret-',3); $timestamp=(string)time(); $body='{"eventId":"fixture"}';
        $signature=hash_hmac('sha256',$timestamp.'.'.$body,$secret);
        self::assertSame(['eventId'=>'fixture'], SignedCallback::verify($body,$timestamp,$signature,$secret));
        $this->expectException(IntegrationException::class);
        SignedCallback::verify($body.' ',$timestamp,$signature,$secret);
    }

    public function testStaleCallbackRejected(): void
    {
        $secret=str_repeat('x',32); $timestamp=(string)(time()-301); $body='{}';
        $this->expectException(IntegrationException::class);
        SignedCallback::verify($body,$timestamp,hash_hmac('sha256',$timestamp.'.'.$body,$secret),$secret);
    }

    public function testRealCurlTransportRejectsLoopbackBeforeOpeningConnection(): void
    {
        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('integration_endpoint_denied');
        (new CurlBridgeTransport())->request('POST','https://127.0.0.1/fixture',[],'{}');
    }

    public function testHttpErrorsAndMalformedResponsesNeverProduceSuccess(): void
    {
        foreach ([['status'=>503,'body'=>'{"contract":"architex.integration.v1"}'],['status'=>302,'body'=>'{}'],['status'=>200,'body'=>'<html>wrong</html>'],['status'=>200,'body'=>'[]']] as $response) {
            $transport=new class($response) implements BridgeTransport {
                public function __construct(private array $response) {}
                public function request(string $method,string $url,array $headers,string $body): array { return $this->response; }
            };
            $client=new BridgeClient(BridgeConfig::fromValues(['URL'=>'https://fixture.example','TOKEN'=>str_repeat('x',32),'WEBHOOK_SECRET'=>str_repeat('y',32)]),$transport);
            try { $client->call('GET','/capabilities',[]); self::fail('Bad external response accepted.'); }
            catch (IntegrationException $e) { self::assertSame(502,$e->httpStatus); }
        }
    }

    public function testPaymentTerminalStatesCannotRegress(): void
    {
        self::assertSame('provider_confirmed_paid', ProviderLifecycle::next('submitted_to_provider','payment_paid'));
        self::assertSame('refunded', ProviderLifecycle::next('provider_confirmed_paid','payment_refunded'));
        foreach ([['provider_confirmed_paid','payment_processing'],['provider_confirmed_paid','payment_failed'],['refunded','payment_paid'],['failed','payment_paid'],['submitted_to_provider','payment_refunded']] as [$from,$event]) {
            self::assertNull(ProviderLifecycle::next($from,$event));
        }
    }
}
