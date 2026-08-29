<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Analytics;

use Architex\Platform\Analytics\AnalyticsReportingException;
use Architex\Platform\Analytics\AnalyticsReportingService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class AnalyticsReportingServiceTest extends MariaDbTestCase
{
    public function testBuildsAuditableKpisDashboardsAlertsExportsAndRedactedObservability(): void
    {
        [$organisation,$owner,$project]=$this->tenant('analytics-core');
        $member=$this->member($organisation,'analytics-member@example.test','member');
        $service=new AnalyticsReportingService($this->pdo);
        $sources=$this->sourceRecords($organisation,$owner,$project);

        $formulas=$service->installBaselineFormulas($organisation,$owner,'owner');
        self::assertCount(5,$formulas);
        self::assertSame(['compliance_gap_count','cost_to_complete_cents','defect_liability_remaining_days','retention_release_readiness_percent','schedule_variance_days'],array_column($formulas,'formulaKey'));
        $scheduleV2=$service->publishFormulaVersion($organisation,$owner,'owner',['formulaKey'=>'schedule_variance_days','title'=>'Schedule variance days','unit'=>'days','expression'=>'actual_or_forecast_date - planned_date','humanConfirmed'=>true]);
        self::assertSame(2,$scheduleV2['version']);
        try{$service->publishFormulaVersion($organisation,$owner,'owner',['formulaKey'=>'schedule_variance_days','title'=>'Misleading formula','unit'=>'days','expression'=>'arbitrary executable formula','humanConfirmed'=>true]);self::fail('Unsupported formula text must not diverge from the implemented calculator.');}
        catch(AnalyticsReportingException$exception){self::assertSame('analytics_formula_definition_unsupported',$exception->reason);}

        $metrics=$service->calculateProjectKpis($organisation,$owner,$project,['asOf'=>'2026-08-29','humanConfirmed'=>true]);
        self::assertCount(5,$metrics);
        self::assertSame(5,$this->metric($metrics,'schedule_variance_days')['value']);
        self::assertSame(500000,$this->metric($metrics,'cost_to_complete_cents')['value']);
        self::assertSame(33,$this->metric($metrics,'defect_liability_remaining_days')['value']);
        self::assertSame(50,$this->metric($metrics,'retention_release_readiness_percent')['value']);
        self::assertSame(2,$this->metric($metrics,'compliance_gap_count')['value']);
        self::assertSame(2,$this->metric($metrics,'schedule_variance_days')['formulaVersion']);
        self::assertContains($sources['milestone'],$this->metric($metrics,'schedule_variance_days')['sourceRecordIds']);

        $layout=$service->saveDashboardLayout($organisation,$owner,'owner',$project,['dashboardRole'=>'client','widgetKeys'=>['schedule_variance_days','retention_release_readiness_percent','compliance_gap_count'],'refreshSeconds'=>60,'humanConfirmed'=>true]);
        self::assertSame('client',$layout['dashboardRole']);
        $dashboard=$service->dashboard($organisation,$owner,'owner',$project,'client');
        self::assertCount(3,$dashboard['widgets']);
        self::assertTrue($dashboard['advisoryOnly']);

        $rule=$service->createAlertRule($organisation,$owner,'owner',$project,['name'=>'Retention release pending','recordType'=>'retention_status','payloadPath'=>'retentionReleasePending','operator'=>'equals','comparisonValue'=>true,'severity'=>'high','recipientRole'=>'owner','throttleMinutes'=>60,'humanConfirmed'=>true]);
        $first=$service->evaluateAlerts($organisation,$owner,$project,['humanConfirmed'=>true]);
        self::assertCount(1,$first);
        self::assertSame($rule['id'],$first[0]['ruleId']);
        self::assertTrue($first[0]['acknowledgementRequired']);
        self::assertCount(0,$service->evaluateAlerts($organisation,$owner,$project,['humanConfirmed'=>true]));
        try{$service->acknowledgeAlert($organisation,$member,$first[0]['id'],['humanConfirmed'=>true,'note'=>'Wrong recipient role.']);self::fail('A non-recipient member must not acknowledge this owner alert.');}
        catch(AnalyticsReportingException$exception){self::assertSame('analytics_alert_permission_denied',$exception->reason);}
        $ack=$service->acknowledgeAlert($organisation,$owner,$first[0]['id'],['humanConfirmed'=>true,'note'=>'Reviewed; formal release remains separate.']);
        self::assertSame('acknowledged',$ack['status']);
        self::assertFalse($ack['formalApprovalChanged']);

        $metric=$service->recordObservability($organisation,$owner,'owner',['moduleKey'=>'analytics_reporting','metricType'=>'latency_ms','metricValue'=>42.5,'retentionDays'=>30,'technicalContext'=>['route'=>'kpi.refresh','statusCode'=>200],'humanConfirmed'=>true]);
        self::assertSame(['route'=>'kpi.refresh','statusCode'=>200],$metric['technicalContext']);
        $workspace=$service->workspace($organisation,$owner,'owner',$project);
        self::assertCount(5,$workspace['latestMetrics']);
        self::assertCount(1,$workspace['alertRules']);
        self::assertCount(1,$workspace['alertEvents']);
        self::assertCount(1,$workspace['observability']);

        $csv=$service->export($organisation,$owner,$project,'project_records','csv','2026-01-01','2026-12-31');
        self::assertSame('text/csv',$csv['mimeType']);
        self::assertStringContainsString('record_type',$csv['content']);
        self::assertStringNotContainsString('private@example.test',$csv['content']);
        self::assertStringContainsString('[redacted]',$csv['content']);
        $json=$service->export($organisation,$owner,$project,'inbox','json','2026-01-01','2026-12-31');
        self::assertSame('application/json',$json['mimeType']);
        self::assertStringContainsString('analytics.alert',$json['content']);

        self::assertSame(5,(int)$this->pdo->query("SELECT COUNT(*) FROM analytics_kpi_snapshots")->fetchColumn());
        self::assertSame(5,(int)$this->pdo->query("SELECT COUNT(*) FROM project_records WHERE module_key='analytics_reporting' AND record_type='kpi_metric'")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM inbox_items WHERE event_type='analytics.alert'")->fetchColumn());
        self::assertGreaterThanOrEqual(8,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'analytics.%'")->fetchColumn());
    }

    public function testProjectAnalyticsCannotCrossTenantBoundary(): void
    {
        [$organisation,$owner]=$this->tenant('analytics-scope-a');
        [,,$otherProject]=$this->tenant('analytics-scope-b');
        try{(new AnalyticsReportingService($this->pdo))->workspace($organisation,$owner,'owner',$otherProject);self::fail('Cross-tenant analytics must be hidden.');}
        catch(AnalyticsReportingException$exception){self::assertSame('analytics_project_not_found',$exception->reason);}
    }

    /** @return array<string,array<string,mixed>> */
    private function sourceRecords(string$o,string$u,string$p):array
    {
        $records=[
            'milestone'=>['milestone_status','Milestone',['plannedDate'=>'2026-08-20','actualDate'=>'2026-08-25','email'=>'private@example.test']],
            'cost'=>['financial_summary','Cost',['budgetCents'=>2000000,'committedCents'=>1400000,'actualCents'=>900000,'forecastCostCents'=>1400000]],
            'defects'=>['defect_liability','Defects',['endsOn'=>'2026-10-01']],
            'retention'=>['retention_status','Retention',['conditionsMet'=>2,'conditionsTotal'=>4,'retentionReleasePending'=>true,'amountCents'=>120000]],
            'compliance'=>['compliance_status','Compliance',['gaps'=>['insurance_expired','registration_missing']]],
        ];
        $ids=[];
        foreach($records as$key=>[$type,$title,$payload]){$id=Identifier::generate();$ids[$key]=$id;$this->pdo->prepare("INSERT INTO project_records(id,organisation_id,project_id,phase,module_key,record_type,title,status,payload,approvals,linked_record_ids,created_by) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),'construction','source',:type,:title,'pending_review',:payload,'{}','[]',UNHEX(:u))")->execute(['id'=>$id,'o'=>$o,'p'=>$p,'type'=>$type,'title'=>$title,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'u'=>$u]);}
        return$ids;
    }

    /** @param list<array<string,mixed>>$metrics @return array<string,mixed> */
    private function metric(array$metrics,string$key):array{foreach($metrics as$metric)if($metric['formulaKey']===$key)return$metric;self::fail('Metric not found: '.$key);}

    /** @return array{string,string,string} */
    private function tenant(string$slug):array
    {
        $o=Identifier::generate();$u=Identifier::generate();$p=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$u,'email'=>$slug.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id'=>$o,'name'=>$slug,'slug'=>$slug.'-'.substr($o,0,8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:p),UNHEX(:o),'ANALYTICS','Analytics Project','Client','City','Erf 2','Office','','architect','construction_execution',UNHEX(:u))")->execute(['p'=>$p,'o'=>$o,'u'=>$u]);
        return[$o,$u,$p];
    }

    private function member(string$o,string$email,string$role):string
    {
        $u=Identifier::generate();$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$u,'email'=>$email]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u,'role'=>$role]);return$u;
    }
}
