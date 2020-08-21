<?php

class PdfReport
{
    public function createReportForItem($item)
    {
        $identifier = ItemMetadata::getItemIdentifier($item);
        $title = ItemMetadata::getItemTitle($item);

        $imageFileName = '';
        $itemFiles = $item->Files;
        if ($itemFiles)
        {
            // Remove the first file because it appears in the Primary section above the fields.
            $imageFileName = FILES_DIR . '/fullsize/' . $itemFiles[0]['filename'];
        }

        $pdf = new FPDF('P', 'in', 'letter');
        $pdf->AddPage();
        $pdf->SetFont('Arial','',13);
        $pdf->Cell(0, 0, self::decode($title));
        $pdf->Ln(0.2);
        $pdf->Image($imageFileName);
        $pdf->Ln(0.4);
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(1, 0, 'Identifier:', 0, 0, 'R');
        $pdf->Cell(0, 0, self::decode($identifier));
        $pdf->Ln(0.2);
        $pdf->Cell(1, 0, 'Date:', 0, 0, 'R');
        $pdf->Cell(0, 0, '2020-08-21');
        $pdf->Ln(0.2);
        $pdf->Cell(1, 0, 'Title:', 0, 0, 'R');
        $pdf->Cell(0, 0, self::decode($title));
        $pdf->Output('test1.pdf', 'D');
    }

    public function createReportForSearchResults()
    {

    }

    protected static function decode($text)
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}