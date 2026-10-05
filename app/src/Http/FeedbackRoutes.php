<?php
declare(strict_types=1);
namespace Architex\Http;
use Architex\Http\Controller\FeedbackController;
final readonly class FeedbackRoutes
{
    public function __construct(private FeedbackController $controller) {}
    public function register(Router $router): void
    {
        // Authentication, organisation scoping and CSRF are supplied by the kernel.
        $router->get('/api/v1/feedback',$this->controller->index(...));
        $router->post('/api/v1/feedback',$this->controller->create(...));
        $router->get('/api/v1/feedback/{id}',$this->controller->show(...));
    }
}
