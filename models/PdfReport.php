<?php

class PdfReport
{
    public function createReportForItem($item)
    {
        $pdf = new FPDF('P', 'in', 'letter');

        $pdf->SetTopMargin(0.75);
        $pdf->SetLeftMargin(0.75);
        $pdf->SetRightMargin(0.75);

        $pdf->AddPage();
        $pdf->SetFont('Arial','',13);

        $title = ItemMetadata::getItemTitle($item);
        $pdf->Cell(0, 0, self::decode($title));
        $pdf->Ln(0.2);
        $pdf->SetFont('Arial','',10);

        $itemFiles = $item->Files;
        if ($itemFiles)
        {
            // Remove the first file because it appears in the Primary section above the fields.
            $primaryImage = $itemFiles[0];
            $imageFileName = FILES_DIR . '/fullsize/' . $primaryImage['filename'];
            if ($primaryImage['mime_type'] == 'image/jpeg')
            {
                $pdf->Image($imageFileName);
            }
            else
            {
                $pdf->Ln(0.2);
                $pdf->Cell(1, 0, "Attachment: $imageFileName");
            }
            $pdf->Ln(0.2);
        }

        $elementTexts = get_db()->getTable('ElementText')->findByRecord($item);

        $previousName = '';
        foreach ($elementTexts as $elementText)
        {
            $name = ItemMetadata::getElementNameFromId($elementText['element_id']);
            if ($name == $previousName)
            {
                $name = '';
            }
            else
            {
                $previousName = $name;
                $name .= ':';
            }
            $text = $elementText['text'];
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell(1, 0.2, $name, 0, 0, 'R');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(6, 0.18, self::decode($text));
            $pdf->Ln(0.08);
        }

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