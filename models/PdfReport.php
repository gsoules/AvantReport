<?php

class PdfReport
{
    public function createReportForItem($item)
    {
        $identifier = ItemMetadata::getItemIdentifier($item);
        $title = ItemMetadata::getItemTitle($item);
        $text = "$identifier: $title";
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $imageFileName = '';
        $itemFiles = $item->Files;
        if ($itemFiles)
        {
            // Remove the first file because it appears in the Primary section above the fields.
            $imageFileName = FILES_DIR . '/fullsize/' . $itemFiles[0]['filename'];
        }


        $pdf = new FPDF('P', 'in', 'letter');
        $pdf->AddPage();
        $pdf->SetFont('Arial','',12);
        $pdf->Image($imageFileName);
        $pdf->Cell(0,0, $text);
        $pdf->Output('test7.pdf', 'D');
    }

    public function createReportForSearchResults()
    {

    }
}