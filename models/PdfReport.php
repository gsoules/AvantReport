<?php

class PDF extends FPDF
{
    function Footer()
    {
        $this->SetTextColor(80, 80, 80);
        $this->SetY(-0.5);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 0, date('n/j/Y'));
        $this->Cell(0, 0, 'Page '.$this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
}

class PdfReport
{
    public function createReportForItem($item)
    {
        $pdf = new PDF('P', 'in', 'letter');
        $pdf->AliasNbPages();

        $pdf->SetTopMargin(0.75);
        $pdf->SetLeftMargin(0.75);
        $pdf->SetRightMargin(0.75);

        $pdf->AddPage();

        $url = WEB_ROOT . '/items/show/' . $item->id;
        $pdf->SetFont('Arial','',10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 0, get_option('site_title'));
        $pdf->SetFont('','U');
        $pdf->AddLink();
        $pdf->Cell(0, 0, $url, 0, 0, 'R');
        $pdf->Line(0.8, 1.0, 7.70, 1.0);

        $pdf->Ln(0.2);
        $pdf->SetFont('Arial','',13);
        $title = ItemMetadata::getItemTitle($item);
        $pdf->Ln(0.4);
        $pdf->Cell(0, 0, self::decode($title));

        $pdf->Ln(0.2);
        $pdf->SetFont('Arial','',10);
        $pdf->SetTextColor(0, 0, 0);

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
        $privateElementsData = CommonConfig::getOptionDataForPrivateElements();

        $previousName = '';
        foreach ($elementTexts as $elementText)
        {
            $name = ItemMetadata::getElementNameFromId($elementText['element_id']);
            if ($name == $previousName)
            {
                $name = '';
                $style = '';
            }
            else
            {
                $style = in_array($name,$privateElementsData) ? 'I' : '';
                $previousName = $name;
                $name .= ':';
            }

            $pdf->SetFont('', $style );
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell(1, 0.2, $name, 0, 0, 'R');

            $pdf->SetFont('', '');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(5.75, 0.18, self::decode($elementText['text']));
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