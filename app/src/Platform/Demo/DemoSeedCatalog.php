<?php

declare(strict_types=1);

namespace Architex\Platform\Demo;

final class DemoSeedCatalog
{
    private const VERSION = 'pack21-v1';

    /** @return list<array<string,mixed>> */
    public function personas(): array
    {
        $personas = [];
        foreach ((new DemoRoleLensRegistry())->all() as $role) {
            $personas[] = [
                'sourceKey' => 'persona_' . $role['key'],
                'roleKey' => $role['key'],
                'label' => $role['label'],
                'group' => $role['group'],
                'canonicalRole' => $role['canonicalRole'],
                'displayName' => 'Demo ' . $role['label'],
                'firmName' => 'Architex Synthetic Demo Practice',
                'email' => str_replace('_', '.', $role['key']) . '@demo.architex.example',
                'profileStatus' => 'synthetic_unverified_persona',
            ];
        }

        return $personas;
    }

    /** @return list<array<string,mixed>> */
    public function projects(): array
    {
        return [
            $this->project('parkview','ATX-D01','Parkview Residence','Van der Merwe Developments','City of Johannesburg','Parkview, Johannesburg','Residential (New)','brief_enquiry','concept_design',320_000_000,'2026-03-15T08:00:00Z','2027-06-30','architect'),
            $this->project('sandton','ATX-D02','Sandton Office Block','Nkosi Property Group','City of Johannesburg','Sandton Central, Johannesburg','Commercial (New)','tender_documentation','tender_procurement',1_850_000_000,'2025-08-01T09:00:00Z','2027-03-31','architect'),
            $this->project('seapoint','ATX-D03','Sea Point Apartment Renovation','Nkosi Property Group','City of Cape Town','Sea Point, Cape Town','Residential (Renovation)','construction','construction_execution',870_000_000,'2025-06-15T10:00:00Z','2026-09-30','architect'),
            $this->project('umhlanga_medical','ATX-D04','Umhlanga Medical Centre','Mthembu Healthcare Properties','eThekwini Metropolitan','Umhlanga Ridge, Durban','Commercial (Healthcare)','brief_enquiry','onboarding',2_200_000_000,'2026-05-01T11:00:00Z','2027-12-31','cpm'),
            $this->project('fourways','ATX-D05','Fourways Mall Extension','Van der Merwe Developments','City of Johannesburg','Fourways, Johannesburg','Commercial (Retail)','design_development','design_development',4_500_000_000,'2025-11-01T08:00:00Z','2028-06-30','architect'),
            $this->project('greenpoint','ATX-D06','Greenpoint Primary School','Mthembu Healthcare Properties','City of Cape Town','Greenpoint, Cape Town','Educational (New)','close_out','closeout',3_500_000_000,'2024-09-01T08:00:00Z','2026-05-31','architect'),
            $this->project('rosebank','ATX-D07','Rosebank Mixed-Use Precinct','Van der Merwe Developments','City of Johannesburg','Rosebank, Johannesburg','Mixed-Use (Residential + Retail)','shortlisted','feasibility',8_500_000_000,'2026-04-10T09:00:00Z','2028-12-31','architect'),
            $this->project('springs','ATX-D08','Springs Industrial Warehouse','Nkosi Property Group','Ekurhuleni Metropolitan','Springs, Ekurhuleni','Industrial (New)','design_development','design_development',2_800_000_000,'2026-01-20T08:00:00Z','2027-08-31','architect'),
            $this->project('bokaap','ATX-D09','Bo-Kaap Heritage Restoration','Mthembu Healthcare Properties','City of Cape Town','Bo-Kaap, Cape Town','Heritage (Restoration)','construction','construction_execution',650_000_000,'2025-10-01T10:00:00Z','2026-11-30','architect'),
            $this->project('stellenbosch','ATX-D10','Stellenbosch Wine Estate Centre','Van der Merwe Developments','Stellenbosch Municipality','Stellenbosch, Western Cape','Agricultural / Hospitality','brief_enquiry','appointment',1_400_000_000,'2026-05-20T11:00:00Z','2028-06-30','cpm','Manifest lifecycle coverage advances this second enquiry example to appointment while retaining the supplied source stage.'),
            $this->project('lenasia','ATX-D11','Lenasia Community Mosque & Centre','Mthembu Healthcare Properties','City of Johannesburg','Lenasia, Johannesburg','Religious / Community','tender_documentation','tender_procurement',1_250_000_000,'2025-07-01T08:00:00Z','2027-01-31','architect'),
            $this->project('umhlanga_tower','ATX-D12','Umhlanga Ridge Tower','Van der Merwe Developments','eThekwini Metropolitan','Umhlanga Ridge, Durban','Mixed-Use (High-Rise)','concept_design','concept_design',22_000_000_000,'2026-02-01T09:00:00Z','2029-06-30','architect'),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function items(): array
    {
        $items = [];
        foreach ($this->projects() as $index => $project) {
            $date = sprintf('2026-06-%02dT09:00:00Z', $index + 1);
            $items[] = $this->item('submission_' . $project['sourceKey'], 'submission', $project['sourceKey'], 'Illustrative submission readiness snapshot', $date, 'illustrative_demo_not_for_reliance');
            $items[] = $this->item('message_' . $project['sourceKey'], 'message', $project['sourceKey'], 'Synthetic project coordination message', $date, 'illustrative_demo_not_for_reliance');
            $items[] = $this->item('compliance_' . $project['sourceKey'], 'compliance', $project['sourceKey'], 'Advisory compliance example requiring professional verification', $date, 'illustrative_demo_not_for_reliance');
        }
        foreach (['Professional practice','Accessible design','Energy efficiency','Construction quality','Project communication','Ethics and responsibility'] as $index => $title) {
            $items[] = $this->item('learning_article_' . ($index + 1), 'learning_article', null, $title, sprintf('2026-07-%02dT08:00:00Z', $index + 1), 'not_accredited_demo_record');
        }
        foreach (['Practice assessment','Compliance assessment','Technical assessment','Ethics assessment'] as $index => $title) {
            $items[] = $this->item('learning_assessment_' . ($index + 1), 'learning_assessment', null, $title, sprintf('2026-07-%02dT09:00:00Z', $index + 10), 'not_accredited_demo_record');
        }
        foreach (['Sustainable design module','Professional delivery module','Technical coordination module'] as $index => $title) {
            $items[] = $this->item('learning_module_' . ($index + 1), 'learning_module', null, $title, sprintf('2026-07-%02dT10:00:00Z', $index + 20), 'not_accredited_demo_record');
        }
        foreach (['Demo learning record A','Demo learning record B','Demo learning record C','Demo learning record D','Demo learning record E'] as $index => $title) {
            $items[] = $this->item('learning_certificate_' . ($index + 1), 'learning_certificate', null, $title, sprintf('2026-08-%02dT11:00:00Z', $index + 1), 'not_accredited_demo_record');
        }

        return $items;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function hash(): string
    {
        return hash('sha256', $this->canonicalJson([
            'version' => self::VERSION,
            'personas' => $this->personas(),
            'projects' => $this->projects(),
            'items' => $this->items(),
        ]));
    }

    /** @return array<string,mixed> */
    private function project(string $sourceKey,string $code,string $name,string $client,string $municipality,string $location,string $use,string $sourceStage,string $phase,int $budgetCents,string $createdAt,string $targetDate,string $leadRole,string $adaptation='Direct deterministic projection of the supplied source project.'): array
    {
        return ['sourceKey'=>$sourceKey,'projectCode'=>$code,'projectName'=>$name,'clientName'=>$client,'municipality'=>$municipality,'propertyReference'=>$location,'propertyUse'=>$use,'landUseNotes'=>'Synthetic demo project. Not for professional or statutory reliance.','leadProfessionalRole'=>$leadRole,'sourceStage'=>$sourceStage,'currentPhase'=>$phase,'stageAdaptation'=>$adaptation,'budgetCents'=>$budgetCents,'createdAt'=>$createdAt,'targetDate'=>$targetDate,'relianceLabel'=>'illustrative_demo_not_for_reliance'];
    }

    /** @return array<string,mixed> */
    private function item(string $sourceKey,string $itemType,?string $projectKey,string $title,string $occurredAt,string $relianceLabel): array
    {
        return ['sourceKey'=>$sourceKey,'itemType'=>$itemType,'projectKey'=>$projectKey,'title'=>$title,'occurredAt'=>$occurredAt,'relianceLabel'=>$relianceLabel,'payload'=>['title'=>$title,'synthetic'=>true,'authorityClaimed'=>false,'accreditationClaimed'=>false]];
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->sortRecursively($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        return $value;
    }
}
