<?php

declare(strict_types=1);

namespace Architex\Platform\Toolbox;

/** Renders the persisted takeoff result; never recalculates or certifies it. */
final class BoqPdf
{
    public static function render(array $run): string
    {
        if (($run['toolId'] ?? null) !== 'boq_takeoff' || !is_array($run['output'] ?? null)) throw new ToolException('invalid_tool_output');
        $output = $run['output'];
        $pdf = new class extends \tFPDF {
            public function loadFont(): void
            {
                $this->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
                $this->fonts['dejavu']['ttffile'] = $this->fontpath.'unifont/DejaVuSansCondensed.ttf';
            }
            public function Footer()
            {
                $this->SetY(-15);
                $this->SetFont('DejaVu', '', 8);
                $this->Cell(0, 6, 'Internal draft takeoff — responsible-person review required | Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
            }
        };
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetAutoPageBreak(true, 22);
        $pdf->AliasNbPages();
        $pdf->loadFont();
        $pdf->AddPage();
        $pdf->SetFont('DejaVu', '', 17);
        $pdf->MultiCell(174, 8, 'ARCHITEX — BoQ / BoM');
        $pdf->SetFont('DejaVu', '', 12);
        $pdf->MultiCell(174, 7, (string)$output['title']);
        $pdf->SetFont('DejaVu', '', 9);
        foreach (['Run: '.($run['id'] ?? ''), 'Created: '.($run['createdAt'] ?? ''), 'Project: '.($run['assignment']['projectName'] ?? $run['assignment']['externalReference'] ?? 'Unassigned'), 'Currency: '.$output['currency'].' | Status: '.($run['status'] ?? 'completed')] as $text) $pdf->MultiCell(174, 5, $text);
        $pdf->Ln(5);
        $money = static fn(int $cents): string => number_format($cents / 100, 2, '.', ',');
        foreach ($output['items'] as $item) {
            $pdf->SetFont('DejaVu', '', 10);
            $pdf->MultiCell(174, 6, $item['line'].'. '.$item['description'], 'T');
            $pdf->SetFont('DejaVu', '', 9);
            $quantity = rtrim(rtrim(number_format((float)$item['quantity'], 4, '.', ''), '0'), '.');
            $pdf->MultiCell(174, 5, 'Quantity: '.$quantity.' '.$item['unit'].' | Rate: '.$money((int)$item['rateCents']).' | Amount: '.$money((int)$item['amountCents']));
            $pdf->Ln(3);
        }
        $pdf->Ln(4);
        $pdf->SetFont('DejaVu', '', 11);
        foreach (['Subtotal: '.$money((int)$output['subtotalCents']), 'Contingency ('.$output['contingencyPercent'].'%): '.$money((int)$output['contingencyCents']), 'Total '.$output['currency'].': '.$money((int)$output['totalCents'])] as $text) $pdf->MultiCell(174, 7, $text);
        $pdf->Ln(5);
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->MultiCell(174, 5, (string)$output['limitations']);
        return $pdf->Output('S');
    }
}