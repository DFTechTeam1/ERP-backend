<?php

namespace App\Pdf;

use FPDF;

require (__DIR__ . '/../../public/fpdf186/fpdf.php');

class PdfCreator extends FPDF {
    private $boxClientY = 0;

    function Header() {
        $this->setSidebar();

        // Arial bold 15
        $this->SetFont('Arial','B',15);
        
        // write quotation information
        $this->setQuotationContent();
    }

    protected function setSidebar()
    {
        // Logo
        $this->Image(
            file: 'dfactory.png',
            x: 17.5,
            y: 6,
            w: 15
        );

        $this->Line(
            x1: 10,
            y1: 25,
            x2: 40,
            y2: 25
        );

        $this->setFont(
            family: 'Arial',
            size: '8'
        );
        $this->setXY(x: 10, y: 30);

        $this->MultiCell(
            w: 40,
            h: 4,
            txt: 'Kaca piring 19 2nd level Surabaya - East java',
            border: 0,
            align: 'L'
        );

        $currentPosition = $this->GetY();

        $this->setXY(x: 10, y: $currentPosition);
        $this->Cell(
            w: 30,
            h: 5,
            txt: '+62 821 1068 6655 ',
            border: 0
        );

        $this->setXY(x: 10, y: $this->GetY() + 5);
        $this->Cell(
            w: 32,
            h: 5,
            txt: 'dfactory.id@gmail.com',
            border: 0
        );
    }

    protected function setQuotationContent()
    {
        // point to the right of page
        // write first line
        $this->SetXY(x: 120, y: 10);
        $this->SetFontSize(15);
        $this->Cell(w: 30, h: 10, txt: "Quotation", border: 0);

        $this->SetXY(x: 150, y: 10);
        $this->cell(w: 5, h: 10, txt: ':', border: 0);

        $this->SetXY(x: 155, y: 10);
        $this->Cell(w: 27, h: 10, txt: '#DF01067', border: 0);

        // write second line
        $this->SetFontSize(11);
        $this->setFont('');
        $this->SetXY(x: 120, y: 20);
        $this->Cell(w: 30, h: 5, txt: "Tanggal", border: 0);

        $this->SetXY(x: 150, y: 20);
        $this->Cell(w: 5, h: 5, txt: ":", border: 0);

        $this->SetXY(x: 155, y: 20);
        $this->Cell(w: 27, h: 5, txt: "27 Desember 2025", border: 0);

        // write third line
        $this->SetFontSize(11);
        $this->setFont('');
        $this->SetXY(x: 120, y: 25);
        $this->Cell(w: 30, h: 5, txt: "Design Job", border: 0);

        $this->SetXY(x: 150, y: 25);
        $this->Cell(w: 5, h: 5, txt: ":", border: 0);

        $this->SetXY(x: 155, y: 25);
        $this->Cell(w: 27, h: 5, txt: "S", border: 0);

        $this->SetXY(x: 155, y: 30);
        $this->Cell(w: 27, h: 5, txt: "", border: 0);
        $this->SetXY(x: 155, y: 40);
        $this->Cell(w: 27, h: 5, txt: "", border: 0);
    }

    protected function addClient()
    {
        $this->setXY(x: 50, y: 40);
    
        $this->SetFont(family: 'Arial', style: 'B');
        $this->Cell(
            w: 55,
            h: 10,
            txt: "Ditawarkan Kepada",
            border: 'T,L,R'
        );
    
        $this->SetFont('');
        $this->setXY(x: 50, y: 50);
        $this->MultiCell(
            w: 55,
            h: 5,
            txt: "Anniversary of Mr.Sujanto & Mrs.Melani",
            border: 'L,R'
        );
    
        $this->setXY(x: 50, y: $this->GetY());
        $this->Cell(w: 55, h: 7, txt: "Surabaya", border: 'L,R');
    
        $this->setXY(x: 50, y: $this->GetY() + 7);
        $this->MultiCell(w: 55, h: 5, txt: "Indonesia\n\n", border: 'L,R,B');

        $this->boxClientY = $this->GetY() + 10;
    }

    protected function addOffice()
    {
        $this->setXY(x: 130, y: 40);

        $this->SetFont(family: 'Arial', style: 'B');
        $this->Cell(
            w: 50,
            h: 10,
            txt: "Ditawarkan Oleh",
        );

        $this->setXY(x: 130, y: 50);
        $this->Cell(
            w: 50,
            h: 5,
            txt: "DFactory",
        );
        
        $this->SetFont('');
        $this->setXY(x: 130, y: $this->GetY() + 5);
        $this->MultiCell(w: 50, h: 5, txt: "Kaca Piring 19 / 2nd level, Surabaya - East Java");
    
        $this->setXY(x: 130, y: $this->GetY());
    }

    protected function addDetailEvent()
    {
        $this->SetFont(family: "Arial", style: "B");

        $this->setXY(x: 50, y: $this->boxClientY);
        $this->Cell(w: 30, h: 5, txt: "Detail Acara");

        $this->setXY(x: 80, y: $this->boxClientY);
        $this->Cell(w: 5, h: 5, txt: ":");

        $this->setXY(x: 85, y: $this->boxClientY);
        // remove bold
        $this->SetFont('');
        $this->MultiCell(w: 100, h: 5, txt: "Acara Internal GM Westin Sby (Imlek Dinner)");
        $eventNameY = $this->GetY() + 5;

        // START EVENT DATE
        $this->SetFont(family: "Arial", style: "B");

        $this->setXY(x: 50, y: $eventNameY);
        $this->Cell(w: 30, h: 5, txt: "Tanggal Event");

        $this->setXY(x: 80, y: $eventNameY);
        $this->Cell(w: 5, h: 5, txt: ":");

        $this->setXY(x: 85, y: $eventNameY);
        // remove bold
        $this->SetFont('');
        $this->Cell(w: 20, h: 5, txt: "10 Desember 2025");
        $eventDateY = $this->GetY() + 8;
        // END EVENT DATE

        // START VENUE
        $this->SetFont(family: "Arial", style: "B");

        $this->setXY(x: 50, y: $eventDateY);
        $this->Cell(w: 30, h: 5, txt: "Venue");

        $this->setXY(x: 80, y: $eventDateY);
        $this->Cell(w: 5, h: 5, txt: ":");

        $this->setXY(x: 85, y: $eventDateY);
        // remove bold
        $this->SetFont('');
        $this->Cell(w: 20, h: 5, txt: "Hotel Gajahmada");
        $venueY = $this->GetY();
        // END VENUE

        // START QUOTATION ITEM\
        $this->SetFont(family: 'Arial', size: '10');
        $this->SetDrawColor(r: 218, g: 218, b: 218);
        $tableRow = $venueY + 15;
        $leftTableWidth = 90;
        $leftTableX = 50;
        $tableHeight = 6;
        $this->SetXY($leftTableX, $tableRow);
        $this->Cell(w: $leftTableWidth, h: $tableHeight, txt: "     Deskripsi", border: "L,T,R");
        
        $priceY = $leftTableWidth + $leftTableX;
        $this->SetXY($priceY, $tableRow);
        $this->Cell(w: 40, h: $tableHeight, txt: "Harga (Rp) ", border: "L,T,R", align: 'C');

        $tableRow += $tableHeight;

        $priceStartX = $priceY;
        $priceStartY = $tableRow;
        $totalPriceCellHeight = $tableHeight;

        $this->SetXY($leftTableX, $tableRow);
        $this->Cell(w: $leftTableWidth, h: $tableHeight, txt: "     LED Visual Content", border: 'L,T,R');

        $tableRow += $tableHeight;
        $totalPriceCellHeight += $tableHeight;

        $this->SetXY($leftTableX, $tableRow);
        $this->Cell(w: $leftTableWidth, h: $tableHeight, txt: "     Content Media Size:", border: 'L,R');

        $leds = [
            ['name' => 'Main Stage', 'size' => '5 x 6m'],
            ['name' => 'Main Stage', 'size' => '5 x 6m'],
        ];

        
        foreach ($leds as $led) {
            $tableRow += $tableHeight;
            $totalPriceCellHeight += $tableHeight;
            $this->SetXY($leftTableX, $tableRow);
            $this->Cell(w: $leftTableWidth, h: $tableHeight, txt: "          {$led['name']}: {$led['size']}", border: 'L,R');
        }

        $tableRow += $tableHeight;
        $totalPriceCellHeight += $tableHeight;

        $this->SetXY($leftTableX, $tableRow);
        $this->Cell(w: $leftTableWidth, h: $tableHeight, txt: "", border: 'L,R');

        $tableRow += $tableHeight;
        $totalPriceCellHeight += $tableHeight;

        $this->SetFont(family: "Arial", style: "B");
        $this->SetXY($leftTableX, $tableRow);
        $this->Cell(w: $leftTableWidth, h: $tableHeight, txt: "     Premium LED Visual", border: 'L,R');
        $this->SetFont('');

        $note = "Note yang banyak\nAda beberapa tambahan\nLED main ukuran 12\nPrefunction 12";

        $bullet = chr(149);

        $quotationItems = [
            "Opening Sequence Content",
            "LED Digital Backdrop Content",
            "Entertainment LED Concept",
            "Entertainment LED Concept",
            "Entertainment LED Concept",
            "Entertainment LED Concept",
            "Entertainment LED Concept",
            "Event Stationary",
            "Event Stationary",
            "Event Stationary",
        ];
        foreach ($quotationItems as $keyItem => $item) {
            $borderItem = "L,R";
            if (!$note && $keyItem == count($quotationItems) - 1) {
                $borderItem = "L,B,R";
            }
            $tableRow += $tableHeight;
            $totalPriceCellHeight += $tableHeight;
            $this->SetXY($leftTableX, $tableRow);
            $this->Cell(w: $leftTableWidth, h: $tableHeight, txt: "         {$bullet} {$item}", border: $borderItem);
        }

        if ($note) {
            $note = "     {$note}";
            $note = str_replace("\n", "\n     ", $note);
    
            $tableRow += $tableHeight;
            $totalPriceCellHeight += $tableHeight;
    
            $this->setXY($leftTableX, $tableRow);
            $this->SetFont(family: "Arial", style: 'B');
            $this->MultiCell(w: $leftTableWidth, h: $tableHeight, txt: "     Note", border: "L,R");
            $this->SetFont('');
    
            $tableRow += $tableHeight;
            $totalPriceCellHeight += $tableHeight;
    
            $this->SetXY($leftTableX, $tableRow);
            $this->MultiCell(w: $leftTableWidth, h: $tableHeight, txt: $note, border: 'L,R,B');
    
            // convert note \n to height
            $newLineNoteCount = substr_count($note, "\n");
            $totalPriceCellHeight += ($tableHeight * $newLineNoteCount);
        }
        $ruleX = $this->GetX();
        $ruleY = $this->GetY();

        // set price
        $this->SetXY($priceStartX, $priceStartY);
        $this->Cell(w: 40, h: $totalPriceCellHeight, txt: "Rp199,000,000", border: "L,T,R,B", align: 'C');
        // END QUOTATION ITEM
        
        $this->addQuotationRule($ruleX, $ruleY);
    }

    protected function addQuotationRule($startX, $startY)
    {
        $bullet = chr(149);
        $this->SetFont(family: "Arial", style: "", size: 9);
        $this->SetXY(50, $startY);
        $this->Cell(w: 5, h: 5, txt: $bullet);
        $this->MultiCell(w: 125, h: 5, txt: "Minimum Down Payment sebesar 50% dari total biaya yang ditagihkan, biaya tersebut tidak dapat dikembalikan.");

        $this->SetXY(50, $startY + 10);
        $this->Cell(w: 5, h: 5, txt: $bullet);
        $this->MultiCell(w: 125, h: 5, txt: "Pembayaran melalui rekening BCA 188 060 1225 a/n Wesley Wiyadi / Edwin Chandra Wijaya");

        $this->SetXY(50, $startY + 20);
        $this->Cell(w: 5, h: 5, txt: $bullet);
        $this->MultiCell(w: 125, h: 5, txt: "Biaya diatas tidak termasuk pajak.");

        $this->SetXY(50, $startY + 25);
        $this->Cell(w: 5, h: 5, txt: $bullet);
        $this->MultiCell(w: 125, h: 5, txt: "Biaya layanan diatas hanya termasuk perlengkapan multimedia DFACTORY dan tidak termasuk persewaan unit LED dan sistem multimedia lainnya bila diperlukan.");

        $this->SetXY(50, $startY + 35);
        $this->Cell(w: 5, h: 5, txt: $bullet);
        $this->MultiCell(w: 125, h: 5, txt: "Biaya diatas termasuk Akomodasi untuk Crew bertugas di hari-H event.");
    }

    protected function addContent()
    {
        $bullet = chr(149);

        $this->SetAutoPageBreak(auto: true, margin: 15);

        $this->addClient();

        $this->addOffice();

        $this->addDetailEvent();

        // $this->AddPage();

        // add watermark
        // $this->Image('watermark.png', 70, 85, 100, 0, 'png');
    }

    public function render()
    {
        $this->AliasNbPages();
        $this->AddPage();

        $this->addContent();
        $this->Output();
    }
}
