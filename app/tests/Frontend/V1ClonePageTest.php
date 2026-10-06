<?php
declare(strict_types=1);
namespace Architex\Tests\Frontend;
use Architex\Http\Controller\V1ClonePageController;
use Architex\Http\Request;
use Architex\Platform\Identity\AuthenticatedIdentity;
use PHPUnit\Framework\TestCase;
final class V1ClonePageTest extends TestCase
{
    public function testCandidateRequiresAnAuthenticatedIdentityBeforeRendering(): void
    {
        self::assertTrue(class_exists(V1ClonePageController::class), 'An authenticated candidate controller is required.');
        $controller = new V1ClonePageController(static fn (): string => '<body>PRIVATE CANDIDATE</body>');
        $denied = $controller(Request::json('GET', '/v1-candidate.php'));
        self::assertSame(401, $denied->status());
        self::assertStringNotContainsString('PRIVATE CANDIDATE', $denied->body());
        $request = Request::json('GET', '/v1-candidate.php')->withAttribute('identity', new AuthenticatedIdentity(str_repeat('a', 32), 'test@example.test', []));
        $allowed = $controller($request);
        self::assertSame(200, $allowed->status());
        self::assertStringContainsString('PRIVATE CANDIDATE', $allowed->body());
        self::assertSame('no-store, private', $allowed->header('Cache-Control'));
        self::assertStringNotContainsString('content="allowed"', $allowed->body());
    }
}
