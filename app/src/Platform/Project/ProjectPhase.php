<?php
declare(strict_types=1);
namespace Architex\Platform\Project;

enum ProjectPhase: string
{
    case Onboarding = 'onboarding';
    case Feasibility = 'feasibility';
    case Appointment = 'appointment';
    case ConceptDesign = 'concept_design';
    case DesignDevelopment = 'design_development';
    case MunicipalSubmission = 'municipal_submission';
    case TenderProcurement = 'tender_procurement';
    case ConstructionExecution = 'construction_execution';
    case Closeout = 'closeout';

    /** @return list<string> */
    public function requiredRecords(): array
    {
        return match ($this) {
            self::Onboarding, self::Feasibility => ['project_brief', 'property_profile'],
            self::Appointment => ['professional_appointment', 'scope_baseline'],
            self::ConceptDesign => ['professional_appointment', 'scope_baseline', 'concept_drawings'],
            self::DesignDevelopment => ['professional_appointment', 'technical_drawings'],
            self::MunicipalSubmission => ['municipal_submission_pack', 'technical_drawings'],
            self::TenderProcurement => ['tender_pack', 'scope_baseline'],
            self::ConstructionExecution => ['municipal_approval_letter', 'professional_appointment', 'construction_programme'],
            self::Closeout => ['snag_register', 'closeout_pack'],
        };
    }

    public function next(): ?self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);
        return $index === false || !isset($cases[$index + 1]) ? null : $cases[$index + 1];
    }
}
