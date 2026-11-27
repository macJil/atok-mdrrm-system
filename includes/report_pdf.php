<?php
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

// Core Helvetica uses the Western European character set, including Philippine names with ñ.
class OfficeReport extends FPDF
{
    public function Header()
    {
        $this->SetTextColor(20, 60, 75);
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(0, 5, 'Republic of the Philippines  /  Province of Benguet', 0, 1);
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(0, 8, 'Municipality of Atok', 0, 1);
        $this->SetFont('Helvetica', '', 11);
        $this->Cell(0, 7, 'Municipal Disaster Risk Reduction and Management Office', 0, 1);
        $this->Ln(4);
    }

    public function Footer()
    {
        $this->SetY(-16);
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(0, 7, 'Proposed office format  |  Page ' . $this->PageNo() . ' of {nb}', 'T', 0, 'R');
    }

    public function section($heading)
    {
        if ($this->GetY() > 248) {
            $this->AddPage();
        }
        $this->SetFillColor(226, 237, 238);
        $this->SetFont('Helvetica', 'B', 11);
        $this->MultiCell(0, 8, $heading, 0, 'L', true);
    }

    public function lineText($text)
    {
        $this->SetFont('Helvetica', '', 10);
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        $this->MultiCell(0, 6, $encoded, 0, 'L');
    }
}

function startOfficeReport($title)
{
    $pdf = new OfficeReport('P', 'mm', 'A4');
    $pdf->SetMargins(17, 15, 17);
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->AliasNbPages();
    $pdf->SetTitle($title);
    $pdf->AddPage();
    $pdf->section($title);
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $pdf->lineText('Generated ' . $now->format('Y-m-d H:i T'));
    return $pdf;
}
