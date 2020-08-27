<?php

class FPDFExtended extends FPDF
{
    function Footer()
    {
        $this->SetTextColor(80, 80, 80);
        $this->SetY(-0.5);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 0, date('n/j/Y'));
        $this->Cell(0, 0, 'Page '.$this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
}