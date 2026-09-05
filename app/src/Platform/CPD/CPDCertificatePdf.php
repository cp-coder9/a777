<?php

declare(strict_types=1);

namespace Architex\Platform\CPD;

final class CPDCertificatePdf
{
    /** @param array<string,mixed> $ruleSnapshot */
    public static function awardField(string $amount, array $ruleSnapshot): string
    {
        if(($ruleSnapshot['calculationPolicy']??null)==='manual_compliance_only'||($ruleSnapshot['unitLabel']??null)==='compliance record')return 'CPD award: compliance record';
        $unit=$ruleSnapshot['unitLabel']??null;
        return 'CPD award: '.$amount.' '.(is_string($unit)&&trim($unit)!==''?$unit:'(unit pending confirmation)');
    }

    /** @param list<string> $fields */
    public static function render(array $fields, string $verificationUrl): string
    {
        $pdf=new class($verificationUrl) extends \tFPDF {
            public function __construct(private readonly string $verificationUrl)
            {
                parent::__construct();
                $this->PDFVersion='1.4';
            }
            public function Header()
            {
                $this->SetFont('DejaVu','',18);
                $this->SetTextColor(15,82,77);
                $this->SetXY(21,18);
                $this->Cell(168,10,'ARCHITEX CPD CERTIFICATE');
                $this->SetXY(21,36);
            }
            public function loadCertificateFont(): void
            {
                $this->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
                // Cached tFPDF metrics may come from a differently located release.
                $this->fonts['dejavu']['ttffile']=$this->fontpath.'unifont/DejaVuSansCondensed.ttf';
            }
            public function Footer()
            {
                $this->SetTextColor(15,82,77);
                $this->SetFont('DejaVu','',11);
                $this->SetXY(21,-43);
                $this->Cell(168,6,'Verify certificate online',0,1,'L',false,$this->verificationUrl);
                $this->SetFont('DejaVu','',9);
                $this->SetXY(21,-28);
                $this->Cell(168,6,'Page '.$this->PageNo().' of {nb}');
            }
        };
        $pdf->SetCompression(false);
        $pdf->SetMargins(21,36,21);
        $pdf->SetAutoPageBreak(true,52);
        $pdf->AliasNbPages();
        $pdf->loadCertificateFont();
        $pdf->AddPage();
        foreach($fields as $field){
            $pdf->SetFont('DejaVu','',11);
            $pdf->SetTextColor(31,38,43);
            $pdf->MultiCell(168,6,$field,0,'L');
            $pdf->Ln(5);
        }
        return $pdf->Output('S');
    }
}
