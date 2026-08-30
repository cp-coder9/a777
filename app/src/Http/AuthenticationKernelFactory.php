<?php

declare(strict_types=1);

namespace Architex\Http;

use Architex\Http\Controller\AuthController;
use Architex\Http\Controller\HealthController;
use Architex\Http\Controller\RegistrationController;
use Architex\Http\Controller\ProjectController;
use Architex\Http\Controller\InboxController;
use Architex\Http\Controller\ToolRegistryController;
use Architex\Http\Controller\ReadinessController;
use Architex\Http\Controller\FileController;
use Architex\Http\Controller\TrustController;
use Architex\Http\Controller\BriefController;
use Architex\Http\Controller\TeamController;
use Architex\Http\Controller\ToolboxController;
use Architex\Http\Controller\ProposalController;
use Architex\Http\Controller\AppointmentController;
use Architex\Http\Controller\MeetingController;
use Architex\Http\Controller\CommunicationController;
use Architex\Http\Controller\CommunityController;
use Architex\Http\Controller\DrawingComplianceController;
use Architex\Http\Controller\DemoSandboxController;
use Architex\Http\Controller\FormController;
use Architex\Http\Controller\DocumentController;
use Architex\Http\Controller\DocumentEvidenceController;
use Architex\Http\Controller\ComplianceController;
use Architex\Http\Controller\MunicipalReadinessController;
use Architex\Http\Controller\SpecForgeController;
use Architex\Http\Controller\ModelMetadataController;
use Architex\Http\Controller\TownPlanningController;
use Architex\Http\Controller\RegulatoryCaseController;
use Architex\Http\Controller\SurveyJobController;
use Architex\Http\Controller\ProcurementController;
use Architex\Http\Controller\FinanceController;
use Architex\Http\Controller\SiteExecutionController;
use Architex\Http\Controller\CloseoutController;
use Architex\Http\Controller\PracticeController;
use Architex\Http\Controller\TrustComplianceController;
use Architex\Http\Controller\AgentOrchestrationController;
use Architex\Http\Controller\AnalyticsReportingController;
use Architex\Http\Controller\CPDController;
use Architex\Http\Controller\OperatingSystemController;
use Architex\Http\Controller\BuildInfoController;
use Architex\Http\Controller\DedicatedAppController;
use Architex\Http\Controller\PlatformCompletenessController;
use Architex\Http\Middleware\CsrfMiddleware;
use Architex\Http\Middleware\JsonRequestMiddleware;
use Architex\Http\Middleware\OrganisationContextMiddleware;
use Architex\Http\Middleware\RateLimitMiddleware;
use Architex\Http\Middleware\PermissionMiddleware;
use Architex\Http\Middleware\SecurityHeadersMiddleware;
use Architex\Http\Middleware\SessionMiddleware;
use Architex\Platform\Identity\AuthenticationService;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\PasswordResetService;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\RegistrationService;
use Architex\Platform\Identity\SessionService;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Security\RateLimiter;
use Architex\Platform\Authorization\PermissionService;
use Architex\Platform\Project\LifecycleService;
use Architex\Platform\Project\ProjectPassportService;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Files\FileService;
use Architex\Platform\Files\LocalFileStorage;
use Architex\Platform\Trust\VerificationService;
use Architex\Platform\Brief\GuidedBriefService;
use Architex\Platform\Brief\BriefEvidenceService;
use Architex\Platform\Team\ProjectTeamService;
use Architex\Platform\Fees\FeeCalculatorService;
use Architex\Platform\Toolbox\ProductionToolRegistry;
use Architex\Platform\Toolbox\StandaloneUtilityService;
use Architex\Platform\Toolbox\StandaloneRecordToolService;
use Architex\Platform\Toolbox\ToolboxEngine;
use Architex\Platform\Proposal\ProposalService;
use Architex\Platform\Appointment\AppointmentService;
use Architex\Platform\Finance\CommercialControlService;
use Architex\Platform\SiteExecution\SiteExecutionService;
use Architex\Platform\Closeout\CloseoutService;
use Architex\Platform\Practice\PracticeManagementService;
use Architex\Platform\Trust\TrustComplianceService;
use Architex\Platform\Agent\AgentOrchestrationService;
use Architex\Platform\Analytics\AnalyticsReportingService;
use Architex\Platform\CPD\CPDAssessmentService;
use Architex\Platform\OperatingSystem\ApiCapabilityService;
use Architex\Platform\OperatingSystem\DeploymentReleaseGateService;
use Architex\Platform\OperatingSystem\ProductOperatingSystemService;
use Architex\Platform\DedicatedApps\DedicatedAppRegistry;
use Architex\Platform\DedicatedApps\DedicatedAppService;
use Architex\Platform\PlatformCompleteness\PlatformCompletenessRegistry;
use Architex\Platform\PlatformCompleteness\PlatformCompletenessService;
use Architex\Platform\Meeting\MeetingService;
use Architex\Platform\Communication\ProjectCommunicationService;
use Architex\Platform\Community\CommunityMessagingService;
use Architex\Platform\DrawingCompliance\DrawingComplianceService;
use Architex\Platform\Demo\DemoSandboxService;
use Architex\Platform\Forms\FormService;
use Architex\Platform\Documents\DocumentService;
use Architex\Platform\Documents\DocumentTransmittalService;
use Architex\Platform\Documents\DrawingIntelligenceService;
use Architex\Platform\Compliance\ComplianceCalculatorService;
use Architex\Platform\Engineering\EngineeringCalculatorService;
use Architex\Platform\Compliance\ComplianceRuleRegistry;
use Architex\Platform\Municipal\MunicipalReadinessService;
use Architex\Platform\Municipal\MunicipalRequirementRegistry;
use Architex\Platform\SpecForge\SpecForgeService;
use Architex\Platform\Models\ModelMetadataService;
use Architex\Platform\Planning\TownPlanningService;
use Architex\Platform\Environment\RegulatoryCaseService;
use Architex\Platform\Survey\SurveyJobService;
use Architex\Platform\Procurement\ProcurementScopeClassifier;
use Architex\Platform\Procurement\ProcurementService;
use Architex\Infrastructure\Logging\JsonLogger;
use PDO;

final readonly class AuthenticationKernelFactory
{
    public function __construct(
        private PDO $pdo,
        private RegistrationNotifier $registrationNotifier,
        private PasswordResetNotifier $passwordResetNotifier,
        private ?ReadinessController $readinessController = null,
        private ?string $fileStorageRoot = null,
        private string $appMode = 'production',
    ) {}

    public function create(): Kernel
    {
        $sessions = new SessionService($this->pdo);
        $limiter = new RateLimiter($this->pdo);
        $registration = new RegistrationController(new RegistrationService($this->pdo, $this->registrationNotifier));
        $authentication = new AuthController(
            new AuthenticationService($this->pdo),
            $sessions,
            new PasswordResetService($this->pdo, $this->passwordResetNotifier),
        );
        $remoteKey = static fn (Request $request): string => is_string($request->attribute('remoteIp')) ? $request->attribute('remoteIp') : 'unknown';
        $loginKey = static function (Request $request) use ($remoteKey): string {
            try {
                $body = $request->jsonBody();
                $email = is_string($body['email'] ?? null) ? strtolower(trim($body['email'])) : 'invalid';
            } catch (\JsonException) {
                $email = 'invalid';
            }
            return $email . '|' . $remoteKey($request);
        };

        $router = new Router();
        $router->get('/api/v1/health', new HealthController());
        $router->get('/api/v1/build-info', new BuildInfoController(dirname(__DIR__,2).'/release.json'));
        if ($this->readinessController !== null) {
            $router->get('/api/v1/readiness', $this->readinessController);
        }
        (new AuthenticationRoutes(
            $registration,
            $authentication,
            new RateLimitMiddleware($limiter, 'registration', 5, 3_600, $remoteKey),
            new RateLimitMiddleware($limiter, 'login', 10, 900, $loginKey),
            new RateLimitMiddleware($limiter, 'password_reset', 5, 3_600, $remoteKey),
        ))->register($router);
        (new ProjectRoutes(
            new ProjectController(new ProjectPassportService($this->pdo), new LifecycleService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new ShellDataRoutes(
            new InboxController(new InboxService($this->pdo)),
            new ToolRegistryController(dirname(__DIR__, 2) . '/public/assets/tool-registry.json'),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
        ))->register($router);
        (new OperatingSystemRoutes(
            new OperatingSystemController(new ProductOperatingSystemService($this->pdo),new ApiCapabilityService(),new DeploymentReleaseGateService($this->pdo)),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'projects.write'),
            new PermissionMiddleware(new PermissionService(),'organisation.manage'),
        ))->register($router);
        (new DedicatedAppRoutes(
            new DedicatedAppController(new DedicatedAppService($this->pdo,new DedicatedAppRegistry())),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'projects.write'),
            new RateLimitMiddleware($limiter,'dedicated_app_client',600,3_600,$remoteKey),
        ))->register($router);
        (new PlatformCompletenessRoutes(
            new PlatformCompletenessController(new PlatformCompletenessService($this->pdo,new PlatformCompletenessRegistry())),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'projects.write'),
        ))->register($router);
        (new FileRoutes(
            new FileController(new FileService($this->pdo, new LocalFileStorage($this->fileStorageRoot ?? dirname(__DIR__, 2) . '/storage/files'))),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new TrustRoutes(
            new TrustController(new VerificationService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
            new PermissionMiddleware(new PermissionService(), 'verification.review'),
        ))->register($router);
        (new TrustComplianceRoutes(
            new TrustComplianceController(new TrustComplianceService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
            new PermissionMiddleware(new PermissionService(), 'verification.review'),
        ))->register($router);
        (new AgentOrchestrationRoutes(
            new AgentOrchestrationController(new AgentOrchestrationService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
            new PermissionMiddleware(new PermissionService(), 'verification.review'),
        ))->register($router);
        (new AnalyticsReportingRoutes(
            new AnalyticsReportingController(new AnalyticsReportingService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
            new PermissionMiddleware(new PermissionService(), 'verification.review'),
        ))->register($router);
        (new BriefRoutes(
            new BriefController(
                new GuidedBriefService($this->pdo),
                new BriefEvidenceService($this->pdo,new LocalFileStorage(($this->fileStorageRoot ?? dirname(__DIR__,2).'/storage/files').'/guided-briefs')),
            ),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new TeamRoutes(
            new TeamController(new ProjectTeamService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        $fees=new FeeCalculatorService($this->pdo);$complianceRules=new ComplianceRuleRegistry($this->pdo);$drawingCompliance=new DrawingComplianceService($this->pdo);$toolRegistry=(new ProductionToolRegistry($fees,new ComplianceCalculatorService($complianceRules),new EngineeringCalculatorService(),new StandaloneUtilityService(),new StandaloneRecordToolService(),$drawingCompliance))->create();
        (new ToolboxRoutes(new ToolboxController(new ToolboxEngine($this->pdo,$toolRegistry),$toolRegistry,$fees),new PermissionMiddleware(new PermissionService(),'tools.use')))->register($router);
        (new DrawingComplianceRoutes(new DrawingComplianceController($drawingCompliance),new PermissionMiddleware(new PermissionService(),'tools.use'),new PermissionMiddleware(new PermissionService(),'tools.use')))->register($router);
        (new ComplianceRoutes(
            new ComplianceController($complianceRules),
            new PermissionMiddleware(new PermissionService(),'tools.use'),
            new PermissionMiddleware(new PermissionService(),'verification.review'),
        ))->register($router);
        $municipalMatrices=new MunicipalRequirementRegistry($this->pdo);
        (new MunicipalReadinessRoutes(
            new MunicipalReadinessController($municipalMatrices,new MunicipalReadinessService($this->pdo,$municipalMatrices)),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'projects.write'),
            new PermissionMiddleware(new PermissionService(),'verification.review'),
        ))->register($router);
        (new ProposalRoutes(
            new ProposalController(new ProposalService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new AppointmentRoutes(
            new AppointmentController(new AppointmentService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new MeetingRoutes(
            new MeetingController(new MeetingService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new CommunicationRoutes(
            new CommunicationController(new ProjectCommunicationService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new CommunityRoutes(
            new CommunityController(new CommunityMessagingService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new FormRoutes(
            new FormController(new FormService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new DocumentRoutes(
            new DocumentController(new DocumentService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new DocumentEvidenceRoutes(
            new DocumentEvidenceController(new DocumentTransmittalService($this->pdo), new DrawingIntelligenceService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new SpecForgeRoutes(
            new SpecForgeController(new SpecForgeService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new ModelMetadataRoutes(
            new ModelMetadataController(new ModelMetadataService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new TownPlanningRoutes(
            new TownPlanningController(new TownPlanningService($this->pdo)),
            new PermissionMiddleware(new PermissionService(), 'projects.read'),
            new PermissionMiddleware(new PermissionService(), 'projects.write'),
        ))->register($router);
        (new RegulatoryCaseRoutes(new RegulatoryCaseController(new RegulatoryCaseService($this->pdo)),new PermissionMiddleware(new PermissionService(),'projects.read'),new PermissionMiddleware(new PermissionService(),'projects.write')))->register($router);
        (new SurveyJobRoutes(new SurveyJobController(new SurveyJobService($this->pdo)),new PermissionMiddleware(new PermissionService(),'projects.read'),new PermissionMiddleware(new PermissionService(),'projects.write')))->register($router);
        (new ProcurementRoutes(
            new ProcurementController(new ProcurementService($this->pdo,new ProcurementScopeClassifier())),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'projects.write'),
            new RateLimitMiddleware($limiter,'procurement_invitation',60,3_600,$remoteKey),
        ))->register($router);
        (new FinanceRoutes(
            new FinanceController(new CommercialControlService($this->pdo)),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'projects.write'),
            new RateLimitMiddleware($limiter,'finance_provider_webhook',300,3_600,$remoteKey),
        ))->register($router);
        (new SiteExecutionRoutes(
            new SiteExecutionController(new SiteExecutionService($this->pdo)),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'projects.write'),
        ))->register($router);
        (new CloseoutRoutes(
            new CloseoutController(new CloseoutService($this->pdo)),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'projects.write'),
        ))->register($router);
        (new PracticeRoutes(
            new PracticeController(new PracticeManagementService($this->pdo)),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'projects.write'),
        ))->register($router);
        (new CPDRoutes(
            new CPDController(new CPDAssessmentService($this->pdo)),
            new PermissionMiddleware(new PermissionService(),'projects.read'),
            new PermissionMiddleware(new PermissionService(),'tools.use'),
            new PermissionMiddleware(new PermissionService(),'verification.review'),
            new RateLimitMiddleware($limiter,'cpd_attempt',60,3_600,$remoteKey),
        ))->register($router);
        if ($this->appMode === 'demo') {
            $demoResetKey = static fn(Request $request): string => $request->attribute('identity') instanceof AuthenticatedIdentity
                ? $request->attribute('identity')->userId
                : 'unauthenticated';
            (new DemoSandboxRoutes(
                new DemoSandboxController(new DemoSandboxService($this->pdo)),
                new PermissionMiddleware(new PermissionService(), 'projects.read'),
                new PermissionMiddleware(new PermissionService(), 'organisation.manage'),
                new RateLimitMiddleware($limiter, 'demo_reset', 3, 3_600, $demoResetKey),
            ))->register($router);
        }

        return new Kernel($router, middleware: [
            new SecurityHeadersMiddleware(),
            new JsonRequestMiddleware(),
            new SessionMiddleware($sessions),
            new OrganisationContextMiddleware(),
            new CsrfMiddleware($sessions),
        ], logger: JsonLogger::toFile(dirname(__DIR__, 2) . '/storage/logs/application.jsonl'));
    }
}
