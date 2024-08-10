<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelService {
    protected $spreadsheet;

    protected $worksheet;

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();

        $this->worksheet = $this->spreadsheet->getActiveSheet();
    }

    public function setAsBold(string $cell)
    {
        $array = [
            'font' => [
                'bold' => true,
            ]
        ];

        $this->spreadsheet->getActiveSheet()->getStyle($cell)->applyFromArray($array);
    }

    public function autoSize(array $dimension)
    {
        foreach($dimension as $dim) {
            $this->spreadsheet->getActiveSheet()->getColumnDimension($dim)->setAutoSize(true);
        }
    }

    public function setValue(string $cell, string $value)
    {
        $this->worksheet->setCellValue($cell, $value);
    }

    public function alignCenter(string $cell)
    {
        $this->spreadsheet->getActiveSheet()->getStyle($cell)
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }

    public function mergeCells(string $range)
    {
        $this->spreadsheet->getActiveSheet()->mergeCells($range);
    }

    public function setAsTypeList(
        string $values, string $cell,
        string $errorTitle, string $errorText,
        string $promtText
    )
    {
        $validation = $this->spreadsheet->getActiveSheet()->getCell($cell)->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION );
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setFormula1('"'. $values .'"');
        $validation->setShowDropDown(true);
        $validation->setErrorTitle($errorTitle);
        $validation->setError($errorText);
        $validation->setPromptTitle($promtText);
        $validation->setPrompt('Pilih Salah Satu');
    }

    public function save(string $path)
    {
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, "Xlsx");
        $writer->save($path);
    }

    public function createSheet(string $sheetName, int $index)
    {
        $worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($this->spreadsheet, $sheetName);
        $this->spreadsheet->addSheet($worksheet, $index);

        $this->deleteDefaultSheet();
    }
    
    public function setActiveSheet(string $sheetName)
    {
        $this->spreadsheet->setActiveSheetIndexByName($sheetName);

        $this->worksheet = $this->spreadsheet->getActiveSheet();
    }

    protected function deleteDefaultSheet()
    {
        if ($this->spreadsheet->getSheetByName('Worksheet')) {
            $sheetIndex = $this->spreadsheet->getIndex(
                $this->spreadsheet->getSheetByName('Worksheet')
            );
            $this->spreadsheet->removeSheetByIndex($sheetIndex);
        }
    }
}