<?php

class PdfReport
{
    public function createReportForItem($item)
    {
        $identifier = ItemMetadata::getItemIdentifier($item);
        $title = ItemMetadata::getItemTitle($item);
        $text = "$identifier: $title";

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',16);
        $pdf->Cell(40,10, $text);
        $pdf->Output('test1.pdf', 'D');
    }

    public function createReportForSearchResults()
    {

    }
}