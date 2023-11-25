<?php
/**
 * @category GauravCape
 * @author Gaurav
 * @copyright Copyright (c) 2023 Gaurav
 * @package GauravCape_CsvImportApi
 */

declare(strict_types=1);

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
