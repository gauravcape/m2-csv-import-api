<?php
namespace GauravCape\CsvImportApi\Api;

interface CsvProductImportManagementInterface
{
    /**
     * Upload CSV file
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function postCsvProductImport();
}