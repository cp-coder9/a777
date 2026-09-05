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
        $lines=[];
        foreach($fields as $field){
            $ascii=preg_replace('/[^\x20-\x7E]/','?', $field) ?? '';
            foreach(explode("\n",wordwrap($ascii,42,"\n",true)) as $line)$lines[]=$line;
            $lines[]='';
        }
        $pages=array_chunk($lines,32);
        if($pages===[])$pages=[[]];
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $kids=[];
        foreach($pages as $index=>$page){
            $pageId=count($objects)+1;$streamId=$pageId+1;$linkId=$pageId+2;
            $kids[]=$pageId.' 0 R';
            $stream="0.06 0.32 0.30 rg\nBT /F1 18 Tf 60 780 Td (ARCHITEX CPD CERTIFICATE) Tj ET\n";
            $stream.="0.12 0.15 0.17 rg\nBT /F1 11 Tf 60 740 Td\n";
            foreach($page as $line)$stream.='('.self::escape($line).") Tj 0 -18 Td\n";
            $stream.="ET\n0.06 0.32 0.30 rg\nBT /F1 11 Tf 60 105 Td (Verify certificate online) Tj ET\n";
            $stream.='BT /F1 9 Tf 60 75 Td (Page '.($index+1).' of '.count($pages).") Tj ET\n";
            $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$streamId.' 0 R /Annots ['.$linkId.' 0 R] >>';
            $objects[$streamId]='<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
            $objects[$linkId]='<< /Type /Annot /Subtype /Link /Rect [60 101 220 119] /Border [0 0 0] /A << /S /URI /URI ('.self::escape($verificationUrl).') >> >>';
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($pages).' >>';
        $pdf="%PDF-1.4\n";$offsets=[];
        foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$size=count($objects)+1;
        $pdf.="xref\n0 ".$size."\n0000000000 65535 f \n";
        foreach($offsets as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
        return $pdf."trailer\n<< /Size ".$size." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$text);
    }
}
