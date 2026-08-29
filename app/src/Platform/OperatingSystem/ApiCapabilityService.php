<?php

declare(strict_types=1);

namespace Architex\Platform\OperatingSystem;

final readonly class ApiCapabilityService
{
    /** @return array<string,mixed> */
    public function report():array
    {
        $capabilities=[
            'projects'=>$this->capability('api_backed','review','Authenticated MariaDB project passport, lifecycle and record routes.',false),
            'messages'=>$this->capability('api_backed','review','Authenticated project and community messaging with tenant-scoped persistence.',false),
            'governance'=>$this->capability('api_backed','approval','Audit, trust, approval and human-review routes persist evidence.',false),
            'payments'=>$this->capability('provider_gated','payment_release','Architex records approvals and provider evidence but does not hold or move funds.',false),
            'cpd'=>$this->capability('api_backed','professional_certification','Body-specific CPD evidence, assessment, records and certificate verification require human gates.',false),
            'agents'=>$this->capability('api_backed','review','Agents may draft and recommend only; regulated actions cannot execute autonomously.',false),
            'files'=>$this->capability('api_backed','review','Private authenticated file storage, metadata and SHA-256 evidence are implemented.',false),
            'municipal'=>$this->capability('human_gated','municipal_submission','Readiness is advisory; actual authority submission and approval remain external human actions.',false),
            'deployment'=>$this->capability('human_gated','approval','Exact build provenance and smoke evidence are recorded; FTP promotion remains human controlled.',false),
        ];
        return['status'=>'warn','generatedAt'=>(new \DateTimeImmutable())->format(DATE_ATOM),'capabilities'=>$capabilities,'warnings'=>['External payment, municipal, professional-body and deployment actions remain provider or human gated.']];
    }

    /** @return array{status:string,humanGate:string,detail:string,movesMoney:bool} */
    private function capability(string$status,string$humanGate,string$detail,bool$movesMoney):array
    {
        return compact('status','humanGate','detail','movesMoney');
    }
}
