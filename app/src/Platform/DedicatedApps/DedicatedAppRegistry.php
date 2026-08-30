<?php

declare(strict_types=1);

namespace Architex\Platform\DedicatedApps;

final class DedicatedAppRegistry
{
    /** @return list<array<string,mixed>> */
    public function all():array{return array_map(fn(array$app)=>$app+['aiMayExecute'=>false],$this->definitions());}

    /** @return array<string,mixed> */
    public function get(string$id):array
    {
        foreach($this->all()as$app)if($app['id']===$id)return$app;
        throw new DedicatedAppException('dedicated_app_not_found');
    }

    /** @return list<array<string,mixed>> */
    private function definitions():array
    {
        return[
            $this->app('agent_worker_runtime','Agent Worker Runtime','backend_worker',1,'Queues, retries, scoped permissions and background execution outside a browser.',['agentWorkflow/agentService','auditService','approvalGateService'],['ProjectDecision','ProjectInboxItem','ProjectRiskSignal','AuditEvent'],false,false,['review','approval','payment_release','municipal_submission','professional_certification'],['job queue','retry','permissions','human gates','audit'],'api_backed'),
            $this->app('notification_gateway','Notification Gateway','gateway_service',1,'Push, email and provider dispatch must work independently of browser sessions.',['notificationService','messagingService'],['NotificationEvent','ProjectInboxItem','AuditEvent'],false,false,['none'],['push','email','SMS/WhatsApp provider abstraction','escalation rules'],'integration_required'),
            $this->app('mobile_companion','Mobile Companion / Decision Inbox','mobile_pwa',2,'Cellphone workflows need installability, offline drafts, camera/share capture and fast review.',['ProjectCommunicationCentrePage','notificationService','projectCommandCentreService'],['ProjectInboxItem','ProjectDecision','NotificationEvent','AuditEvent','FileManagerAsset'],true,false,['review','approval','signature','payment_release','closeout_acceptance'],['push inbox','project chat','photo upload','voice note','approval review'],'api_backed'),
            $this->app('field_companion','Site Field Companion','mobile_pwa',3,'Site capture needs offline forms, location/time evidence, QR tags and conflict-safe sync.',['constructionService','closeoutService','FileManager'],['ProjectRecord','FileManagerAsset','ProjectRiskSignal','ProjectInboxItem','AuditEvent'],true,false,['review','approval','closeout_acceptance'],['daily logs','snags','RFIs','NCRs','inspection photos','offline sync'],'api_backed'),
            $this->app('architex_drive','Architex Drive / Desktop File Sync Agent','desktop_sync_agent',4,'Large project folders need watched folders, resumable upload, cache, versions and conflicts.',['FileManager','uploadService','documentCdeService'],['FileManagerAsset','ProjectRecord','ProjectRiskSignal','AuditEvent'],true,true,['review'],['watched folders','resumable upload','version snapshots','duplicate drawing warnings'],'integration_required'),
            $this->app('revit_cad_connector','Revit / CAD / BIM Connector','desktop_plugin',5,'Native model APIs are required for sheet metadata, revisions and governed exports.',['documentCdeService','drawingChecklistService','FileManager'],['ProjectRecord','FileManagerAsset','ProjectRiskSignal','AuditEvent'],true,true,['review','approval','municipal_submission'],['sheet sync','PDF/DWG/IFC export','revision metadata','issue links'],'integration_required'),
            $this->app('secure_remote_desktop','Secure Remote Desktop / Workstation Rental','native_host_agent',6,'Selected-app-only control needs signed OS enforcement, input filtering and workspace confinement.',['ResourceSharingPage','resourceBookingService','FileManager'],['ResourceBooking','FileManagerAsset','AuditEvent','ProjectInboxItem','PaymentGovernanceRecord'],false,true,['approval','payment_release'],['host registration','app allowlist','window/process binding','short-lived tokens','FileManager handoff'],'integration_required'),
            $this->app('signature_identity_gateway','Signature and Identity Gateway','gateway_service',7,'Identity and signature evidence must be verified by a real provider and callback.',['contractSigningService','userVerificationService','auditService'],['ApprovalGate','AuditEvent','ProjectRecord'],false,false,['signature','approval'],['signature request','provider webhook','identity audit','signed record link'],'integration_required'),
            $this->app('payment_governance_gateway','Payment Governance Gateway','gateway_service',8,'Payment provider intents, signed webhooks, dispute locks and reconciliation are backend-only.',['paymentService','paymentProviderReadinessService','escrowGovernanceService'],['PaymentGovernanceRecord','ProjectInboxItem','AuditEvent','ProjectRiskSignal'],false,false,['payment_release','approval'],['provider intent','webhook verification','dispute lock','retention record','human release request'],'integration_required'),
            $this->app('drawing_intelligence_worker','Drawing Intelligence Worker','backend_worker',9,'Large drawing analysis requires bounded queued jobs and reviewable outputs.',['documentCdeService','drawingChecklistService','ocrService'],['ProjectRecord','FileManagerAsset','ProjectRiskSignal','ProjectInboxItem','AuditEvent'],false,false,['review','municipal_submission'],['OCR','sheet detection','drawing register extraction','revision checking','municipal readiness support'],'integration_required'),
            $this->app('cpd_central_processor','CPD Central Processor','backend_worker',10,'Certificate processing, body mapping and reminders need governed background workflows.',['cpdService','notificationService','governanceService'],['CPDRecord','ProjectInboxItem','NotificationEvent','AuditEvent'],false,false,['professional_certification','review'],['certificate upload','body mapping','manual submission queue','certificate-ready alert'],'api_backed'),
            $this->app('resource_checkin_app','Plant / Equipment / Resource Check-in App','mobile_pwa',11,'Physical rentals need QR, condition evidence, usage hours and accountable handover.',['ResourceSharingPage','resourceBookingService','FileManager'],['ResourceBooking','FileManagerAsset','PaymentGovernanceRecord','AuditEvent'],true,false,['review','approval','payment_release'],['QR check-in/out','condition photos','return inspection','usage hours'],'integration_required'),
        ];
    }

    /** @param list<string>$anchors @param list<string>$outputs @param list<string>$gates @param list<string>$capabilities @return array<string,mixed> */
    private function app(string$id,string$name,string$kind,int$priority,string$reason,array$anchors,array$outputs,bool$offline,bool$device,array$gates,array$capabilities,string$readiness):array
    {
        return['id'=>$id,'name'=>$name,'kind'=>$kind,'priority'=>$priority,'reasonDedicated'=>$reason,'repoAnchors'=>$anchors,'spineOutputs'=>$outputs,'requiresOfflineSupport'=>$offline,'requiresDeviceOrHostAgent'=>$device,'sensitiveGates'=>$gates,'mvpCapabilities'=>$capabilities,'readiness'=>$readiness];
    }
}
