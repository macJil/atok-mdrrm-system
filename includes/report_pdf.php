<?php
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

// Core Helvetica uses the Western European character set, including Philippine names with ñ.
class OfficeReport extends FPDF
{
    public function Header()
    {
        $this->SetTextColor(20, 60, 75);
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(0, 5, 'Republic of the Philippines  /  Province of Benguet', 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(0, 8, 'Municipality of Atok', 0, 1, 'C');
        $this->SetFont('Helvetica', '', 11);
        $this->Cell(0, 7, 'Municipal Disaster Risk Reduction and Management Office', 0, 1, 'C');
        $this->SetDrawColor(107, 127, 136);
        $this->Line(17, $this->GetY()+1, 193, $this->GetY()+1);
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
        $this->SetFillColor(219, 229, 240);
        $this->SetFont('Helvetica', 'B', 11);
        $this->MultiCell(0, 8, $heading, 1, 'L', true);
    }

    // Each row has two or three short fields. Reserve space before drawing its borders.
    public function fields(array $fields)
    {
        if ($this->GetY() > 225) {
            $this->AddPage();
        }
        $top = $this->GetY();
        $bottom = $top;
        $width = 176 / count($fields);
        $left = 17;
        foreach ($fields as $label => $value) {
            $this->SetXY($left + 1, $top + 1);
            $this->SetFont('Helvetica', 'B', 8);
            $this->Cell($width - 2, 5, $label);
            $this->SetXY($left + 1, $top + 6);
            $this->SetFont('Helvetica', '', 10);
            $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', (string)$value);
            $this->MultiCell($width - 2, 5, $text, 0, 'L');
            $bottom = max($bottom, $this->GetY() + 1);
            $left += $width;
        }
        for ($column = 0; $column < count($fields); $column++) {
            $this->Rect(17 + $column * $width, $top, $width, $bottom - $top);
        }
        $this->SetXY(17, $bottom);
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
    $pdf->lineText('Generated ' . $now->format('Y-m-d H:i') . ' Philippine time');
    return $pdf;
}
