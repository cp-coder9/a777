<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\CPDController;
use Architex\Http\Middleware\RateLimitMiddleware;

final readonly class CPDRoutes
{
    public function __construct(private CPDController $controller,private Middleware $read,private Middleware $write,private Middleware $govern,private RateLimitMiddleware $attemptRate) {}

    public function register(Router $router): void
    {
        $router->get('/api/v1/cpd/workspace',fn(Request $request):Response=>$this->read->process($request,$this->controller->workspace(...)));
        $router->get('/api/v1/cpd/professional-bodies',fn(Request $request):Response=>$this->read->process($request,$this->controller->professionalBodies(...)));
        $router->post('/api/v1/cpd/profile',fn(Request $request):Response=>$this->write->process($request,$this->controller->saveProfile(...)));
        $router->post('/api/v1/cpd/content',fn(Request $request):Response=>$this->write->process($request,$this->controller->createContent(...)));
        $router->post('/api/v1/cpd/assessments',fn(Request $request):Response=>$this->write->process($request,$this->controller->createAssessment(...)));
        $router->post('/api/v1/cpd/content/{contentId}/assessment-generation',fn(Request $request):Response=>$this->write->process($request,$this->controller->generateAssessment(...)));
        $router->post('/api/v1/cpd/participants/{userId}/role',fn(Request $request):Response=>$this->write->process($request,$this->controller->assignParticipationRole(...)));
        $router->post('/api/v1/cpd/assessments/{assessmentId}/review',fn(Request $request):Response=>$this->write->process($request,$this->controller->reviewAssessment(...)));
        $router->post('/api/v1/cpd/accreditation',fn(Request $request):Response=>$this->write->process($request,$this->controller->createAccreditation(...)));
        $router->post('/api/v1/cpd/accreditation/{applicationId}/decision',fn(Request $request):Response=>$this->write->process($request,$this->controller->decideAccreditation(...)));
        $router->post('/api/v1/cpd/courses',fn(Request $request):Response=>$this->write->process($request,$this->controller->publishCourse(...)));
        $router->post('/api/v1/cpd/entitlements',fn(Request $request):Response=>$this->write->process($request,$this->controller->createFreeEntitlement(...)));
        $router->post('/api/v1/cpd/entitlements/manual',fn(Request $request):Response=>$this->write->process($request,$this->controller->createManualEntitlement(...)));
        $router->post('/api/v1/cpd/entitlements/{entitlementId}/review',fn(Request $request):Response=>$this->write->process($request,$this->controller->reviewEntitlement(...)));
        $router->post('/api/v1/cpd/commercial-settings',fn(Request $request):Response=>$this->write->process($request,$this->controller->configureCommercialSettings(...)));
        $router->post('/api/v1/cpd/referrals',fn(Request $request):Response=>$this->write->process($request,$this->controller->captureReferral(...)));
        $router->post('/api/v1/cpd/courses/{courseId}/attempts',fn(Request $request):Response=>$this->attemptRate->process($request,fn(Request $candidate):Response=>$this->write->process($candidate,$this->controller->startAttempt(...))));
        $router->post('/api/v1/cpd/attempts/{attemptId}/submit',fn(Request $request):Response=>$this->attemptRate->process($request,fn(Request $candidate):Response=>$this->write->process($candidate,$this->controller->submitAttempt(...))));
        $router->post('/api/v1/cpd/attempts/{attemptId}/review',fn(Request $request):Response=>$this->write->process($request,$this->controller->reviewAttempt(...)));
        $router->post('/api/v1/cpd/attempts/{attemptId}/certificate',fn(Request $request):Response=>$this->write->process($request,$this->controller->issueCertificate(...)));
        $router->get('/api/v1/cpd/certificates/{certificateId}/download',fn(Request $request):Response=>$this->read->process($request,$this->controller->downloadCertificate(...)));
        $router->get('/api/v1/cpd/certificates/verify/{verificationCode}',$this->controller->verifyCertificate(...));
    }
}
