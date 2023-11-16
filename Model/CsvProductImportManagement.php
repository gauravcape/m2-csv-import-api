<?php

declare(strict_types=1);

namespace GauravCape\CsvImportApi\Model;

class CsvProductImportManagement implements \GauravCape\CsvImportApi\Api\CsvProductImportManagementInterface
{
    const XML_Is_Module_Enabled = 'gauravcape_csvimportapi_config/general/enable';
    const XML_Is_Table_log_Enabled = 'gauravcape_csvimportapi_config/general/csvimport_logs_table_enable';
    const XML_Is_Email_Log_Enabled = 'gauravcape_csvimportapi_config/general/csvimport_logs_email_enable';
    const XML_Csvimport_File_Size = 'gauravcape_csvimportapi_config/general/csvimport_file_size';
    const XML_Csv_Sender_Emails = 'gauravcape_csvimportapi_config/general/csvimport_logs_sender_emails';
    const XML_Csv_Report_Emails = 'gauravcape_csvimportapi_config/general/csvimport_logs_on_receive_emails';

    protected $importFactory;
    protected $csvFactory;
    protected $readFactory;
    protected $dir;
    protected $scopeConfig;
    protected $csvImportApiLog;
    protected $apiResponse;
    protected $apiRequest;

    public function __construct(
        \Magento\ImportExport\Model\ImportFactory $importFactory,
        \Magento\ImportExport\Model\Import\Source\CsvFactory $csvFactory,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \GauravCape\CsvImportApi\Model\CsvImportApiLog $csvImportApiLog,
        \Magento\Framework\Webapi\Rest\Response $apiResponse,
        \Magento\Framework\Webapi\Rest\Request $apiRequest
    ) {
        $this->importFactory = $importFactory;
        $this->csvFactory = $csvFactory;
        $this->readFactory = $readFactory;
        $this->dir = $dir;
        $this->scopeConfig = $scopeConfig;
        $this->csvImportApiLog = $csvImportApiLog;
        $this->apiResponse = $apiResponse;
        $this->apiRequest = $apiRequest;
    }

    public function getPostParams(){
		return $this->apiRequest->getBodyParams();
    }
    
    public function responseToJson($data){
        $this->apiResponse->setHeader('Content-Type', 'application/json', true)
        ->setBody(json_encode($data))
        ->sendResponse();
    }

    public function paramsValidation($bodyParams){

       // Check if 'csv_path_type' key is present in the array
       if (!isset($bodyParams['csv_path_type'])) {
            $data = [
                'response' => 'error',
                'message' => __("Missing 'csv_path_type' parameter.")
            ];
            $this->responseToJson($data);
            return false;
        }

        $csvPathType = $bodyParams['csv_path_type'];
        
        // Check if 'csv_path_type' has a valid value
        if (!in_array($csvPathType, ['url', 'local'])) {
            $data = [
                'response' => 'error',
                'message' => __("Invalid 'csv_path_type' value. Allowed values are 'url' or 'local'.")
            ];
            $this->responseToJson($data);
            return false;
        }

        // Check separately for 'csv_location' key
        if (!isset($bodyParams['csv_location'])) {
            $data = [
                'response' => 'error',
                'message' => __("Missing 'csv_location' parameter.")
            ];
            $this->responseToJson($data);
            return false;
        }

        $csvLocation = $bodyParams['csv_location'];

        // Validate the URL if 'csv_path_type' is 'url'
        if ($csvPathType === 'url') {
            if (!filter_var($csvLocation, FILTER_VALIDATE_URL)) {
                $data = ['response' => 'error', 'message' => __("Invalid URL format.")];
                $this->responseToJson($data);
                return false;
            }

            // Additional validation for HTTP or HTTPS URL
            $urlParts = parse_url($csvLocation);
            if (!isset($urlParts['scheme']) || !in_array(strtolower($urlParts['scheme']), ['http', 'https'])) {
                $data = ['response' => 'error', 'message' => __("URL must be an HTTP or HTTPS URL.")];
                $this->responseToJson($data);
                return false;
            }
        }

        // If all validations pass, return true
        return true;
    }

    public function postCsvProductImport(){
        $isModuleEnabled = $this->scopeConfig->getValue(self::XML_Is_Module_Enabled);
        if(!$isModuleEnabled){
            $data = ['response' => 'Error','message'=>__("Import API Module is disabled. Please contact to store admin")];
            $this->responseToJson($data);
            return false;
        }

        // start validate payload
        $bodyParams = $this->getPostParams();

        // Check if paramsValidation returns true before proceeding
        if (!$this->paramsValidation($bodyParams)) {
            return; // rest code will not proceed
        }
        // End validate payload

        $isEmailLogEnabled = $this->scopeConfig->getValue(self::XML_Is_Email_Log_Enabled);

        // convert External Url to local Magento File path
        $importPath = $this->downloadCsvFile($bodyParams['csv_location']);
        
        $importFile = pathinfo($importPath);

        $import = $this->importFactory->create();
        $import->setData([
            'entity' => 'catalog_product',
            'behavior' => 'append',
            'validation_strategy' => 'validation-stop-on-errors',
            'allowed_error_count' => 1
        ]);

        $readFile = $this->readFactory->create($importFile['dirname']);
        $csvSource = $this->csvFactory->create([
            'file' => $importFile['basename'],
            'directory' => $readFile,
        ]);
        
        try {
            $validate = $import->validateSource($csvSource);
            if (!$validate) {
                $validationErrors = $import->getErrorAggregator()->getAllErrors();
                $errorData = [];
                foreach ($validationErrors as $error) {
                    $errorData[] = $error->getErrorMessage() . ' in row ' . $error->getRowNumber();
                }

                // Table Import Log
                $this->saveCsvImportLog($importPath);

                $data = ['response' => 'Error','message'=>__($error->getErrorMessage() . ' in row ' . $error->getRowNumber())];

                $this->responseToJson($data);
                return false;
            } else {
                $result = $import->importSource();
                if ($result) {
                    $import->invalidateIndex();
                    $importedProductsCount = $import->getProcessedRowsCount();
                   
                    // Table Import Log
                    $this->saveCsvImportLog($importPath);

                    $data = ['response' => 'Success','message'=>__("Finished importing $importedProductsCount products from $importPath")];
                    $this->responseToJson($data);
                    return false;
                } else {
                    $data = ['response' => 'Error','message'=>__("Failed to import products.")];
                    $this->responseToJson($data);
                    return false;
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $data = ['response' => 'Errocr','message'=>__($e->getMessage())];
            return $this->responseToJson($data);
        }
    }

    protected function downloadCsvFile($csvURL){
        try {
            $fileSizeCheck = $this->scopeConfig->getValue(self::XML_Csvimport_File_Size);
            $maxFileSize = $fileSizeCheck * 1024 * 1024;
            $headers = get_headers($csvURL, 1);
            $csvEncodeCheck = @file_get_contents($csvURL);

            // Start handling CSV check
            if (isset($headers['Content-Type']) && $headers['Content-Type'] !== 'text/csv') {
                $data = ['response' => 'Error','message'=>__("File is not Csv.")];
                $this->responseToJson($data);
                return false;
            }

            if (!mb_detect_encoding($csvEncodeCheck, 'UTF-8', true)) {
                $data = ['response' => 'Error','message'=>__("CSV file is not in UTF-8 encoding.")];
                $this->responseToJson($data);
                return false;
            }
            
            if (isset($headers['Content-Length']) && $headers['Content-Length'] > $maxFileSize) {
                $data = ['response' => 'Error','message'=>__("CSV file size exceeds the limit of 2MB.")];
                $this->responseToJson($data);
                return false;
            }
            // Start handling CSV check

            $destinationFolder = $this->dir->getPath('pub').'/'.'api_import_csv/';

            // Create the destination folder with 777 permissions if it doesn't exist
            if (!is_dir($destinationFolder)) {
                if (!mkdir($destinationFolder, 0777, true)) {
                    $data = ['response' => 'Error','message'=>__("Failed to create the destination folder, Contact Support.")];
                    $this->responseToJson($data);
                    return false;
                }
            }

            // Get the name from the CSV URL
            $csvFileName = basename($csvURL);

            // Check if a file with the same name already exists in the destination folder
            $localFilePath = $destinationFolder . $csvFileName;
            $i = 1;
            while (file_exists($localFilePath)) {
                $timestamp = date('Y-m-d_H-i-s');
                $csvFileName = $csvFileName . '_' . $timestamp . '_' . $i . '.' . 'csv';
                $localFilePath = $destinationFolder . $csvFileName;
                $i++;
            }
        
            // Download the CSV file and save it locally
            $csvData = @file_get_contents($csvURL);
            
            if ($csvData === false) {
                $data = ['response' => 'Error','message'=>__("Failed to download the CSV file from the URL.")];
                $this->responseToJson($data);
                return false;
            }

            if (file_put_contents($localFilePath, $csvData) === false) {
                $data = ['response' => 'Error','message'=>__("Failed to save the CSV file locally.")];
                $this->responseToJson($data);
                return false;
            }
            return $localFilePath;
        } catch (\Exception $e) {
            if($e->getMessage() == '')
            $data = ['response' => 'Error','message'=>__($e->getMessage())];
            $this->responseToJson($data);
            return false;
        }
    }

    protected function saveCsvImportLog($importPath, $comment = 'No issue'){
        $isTableLogEnabled = $this->scopeConfig->getValue(self::XML_Is_Table_log_Enabled);
    
        if ($isTableLogEnabled) {
            $this->csvImportApiLog
                ->addData([
                    'import_type' => 'product',
                    'csv_file_path' => $importPath,
                    'csv_comment' => $comment,
                ])
                ->save();
        }
    }
}
