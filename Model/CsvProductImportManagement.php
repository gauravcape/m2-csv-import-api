<?php
/**
 * @category GauravCape
 * @author Gaurav
 * @copyright Copyright (c) 2023 Gaurav
 * @package GauravCape_CsvImportApi
 */

declare(strict_types=1);

namespace GauravCape\CsvImportApi\Model;

use Magento\ImportExport\Model\ImportFactory;
use Magento\ImportExport\Model\Import\Source\CsvFactory;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use GauravCape\CsvImportApi\Model\CsvImportApiLog;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

class CsvProductImportManagement implements \GauravCape\CsvImportApi\Api\CsvProductImportManagementInterface
{
    public const IS_MODULE_ENABLED = 'gauravcape_csvimportapi_config/general/enable';
    public const IS_TABLE_LOG_ENABLED = 'gauravcape_csvimportapi_config/general/csvimport_logs_table_enable';
   
    public const CSVIMPORT_FILE_SIZE = 'gauravcape_csvimportapi_config/general/csvimport_file_size';

    public const IS_EMAIL_LOG_ENABLED = 'gauravcape_csvimportapi_config/general/csvimport_logs_email_enable';
    public const CSV_SENDER_EMAILS = 'gauravcape_csvimportapi_config/general/csvimport_logs_sender_emails';
    public const CSV_REPORT_EMAILS = 'gauravcape_csvimportapi_config/general/csvimport_logs_on_receive_emails';

    /**
     * @var ImportFactory
     */
    protected $importFactory;

    /**
     * @var CsvFactory
     */
    protected $csvFactory;

    /**
     * @var ReadFactory
     */
    protected $readFactory;

    /**
     * @var DirectoryList
     */
    protected $dir;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CsvImportApiLog
     */
    protected $csvImportApiLog;

    /**
     * @var Response
     */
    protected $apiResponse;

    /**
     * @var Request
     */
    protected $apiRequest;

     /**
      * @var File
      */
    protected $ioFile;

    /**
     * @var DriverInterface
     */
    protected $driver;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Initialize constructor For Importing Products by API
     *
     * @param ImportFactory $importFactory
     * @param CsvFactory $csvFactory
     * @param ReadFactory $readFactory
     * @param DirectoryList $dir
     * @param ScopeConfigInterface $scopeConfig
     * @param CsvImportApiLog $csvImportApiLog
     * @param Response $apiResponse
     * @param Request $apiRequest
     * @param File $ioFile
     * @param DriverInterface $driver
     * @param Curl $curl
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ImportFactory $importFactory,
        CsvFactory $csvFactory,
        ReadFactory $readFactory,
        DirectoryList $dir,
        ScopeConfigInterface $scopeConfig,
        CsvImportApiLog $csvImportApiLog,
        Response $apiResponse,
        Request $apiRequest,
        File $ioFile,
        DriverInterface $driver,
        Curl $curl,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager
    ) {
        $this->importFactory = $importFactory;
        $this->csvFactory = $csvFactory;
        $this->readFactory = $readFactory;
        $this->dir = $dir;
        $this->scopeConfig = $scopeConfig;
        $this->csvImportApiLog = $csvImportApiLog;
        $this->apiResponse = $apiResponse;
        $this->apiRequest = $apiRequest;
        $this->ioFile = $ioFile;
        $this->driver = $driver;
        $this->curl = $curl;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
    }

    /**
     * Process the CSV product import based on the parameters received in the API request.
     *
     * @return bool True if the CSV import process is successful; false otherwise.
     */
    public function postCsvProductImport()
    {
        $isModuleEnabled = $this->scopeConfig->getValue(self::IS_MODULE_ENABLED);
        if (!$isModuleEnabled) {
            $data = [
                'response' => 'Error',
                'message'=>__("Import API Module is disabled. Please contact to store admin")
            ];
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

        // convert External Url to local Magento File path
        $importPath = $this->downloadCsvFile($bodyParams['csv_location']);

        if (!is_string($importPath)) {
            return false;
        }
        
        // $importFile = pathinfo($importPath);
        $importFile = $this->ioFile->getPathInfo($importPath);

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

                $data = [
                    'response' => 'Error',
                    'message'=>__($error->getErrorMessage() . ' in row ' . $error->getRowNumber())
                ];
                $this->responseToJson($data);
                return false;
            } else {
                $result = $import->importSource();
                if ($result) {
                    $import->invalidateIndex();
                    $importedProductsCount = $import->getProcessedRowsCount();
                   
                    // Table Import Log
                    $this->saveCsvImportLog($importPath);

                    $data = [
                        'response' => 'Success',
                        'message' => "Finished importing $importedProductsCount products from $importPath"
                    ];
                    $this->responseToJson($data);
                    return true;
                } else {
                    $data = [
                        'response' => 'Error',
                        'message'=>__("Failed to import products.")
                    ];
                    $this->responseToJson($data);
                    return false;
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $data = [
                'response' => 'Error',
                'message'=>__($e->getMessage())
            ];
            return $this->responseToJson($data);
        }
    }

    /**
     * Get and return the request body parameters from the API request.
     *
     * @return array The array of request body parameters.
     */
    public function getPostParams()
    {
        return $this->apiRequest->getBodyParams();
    }

    /**
     * Validate the parameters received in the API request for CSV product import.
     *
     * @param array $bodyParams The array of parameters received in the API request body.
     * @return bool True if the parameters are valid; false otherwise.
     */
    public function paramsValidation($bodyParams)
    {
        // Check if 'csv_path_type' key is present in the array
        if (!isset($bodyParams['csv_path_type'])) {
            $data = [
                'response' => 'error',
                'message' => "Missing 'csv_path_type' parameter."
            ];
            $this->responseToJson($data);
            return false;
        }

        $csvPathType = $bodyParams['csv_path_type'];
        
        // Check if 'csv_path_type' has a valid value
        if (!in_array($csvPathType, ['url', 'local'])) {
            $data = [
                'response' => 'error',
                'message' => "Invalid 'csv_path_type' value. Allowed values are 'url' or 'local'."
            ];
            $this->responseToJson($data);
            return false;
        }

        // Check separately for 'csv_location' key
        if (!isset($bodyParams['csv_location'])) {
            $data = [
                'response' => 'error',
                'message' => "Missing 'csv_location' parameter."
            ];
            $this->responseToJson($data);
            return false;
        }

        $csvLocation = $bodyParams['csv_location'];

        // Validate the URL if 'csv_path_type' is 'url'
        if ($csvPathType === 'url') {
            if (!filter_var($csvLocation, FILTER_VALIDATE_URL)) {
                $data = [
                    'response' => 'error',
                    'message' => "Invalid URL format."
                ];
                $this->responseToJson($data);
                return false;
            }

            // Additional validation for HTTP or HTTPS URL
            if (!preg_match('/^https?:/i', $csvLocation)) {
                $data = [
                    'response' => 'error',
                    'message' => "URL must be an HTTP or HTTPS URL."
                ];
                $this->responseToJson($data);
                return false;
            }
        }

        // If all validations pass, return true
        return true;
    }

    /**
     * Download the CSV file from the specified URL, perform checks, and save it locally.
     *
     * @param string $csvURL The URL of the CSV file to be downloaded.
     * @return string|bool The local file path if the download and save are successful; false otherwise.
     */
    protected function downloadCsvFile($csvURL)
    {
        try {
            $fileSizeCheck = $this->scopeConfig->getValue(self::CSVIMPORT_FILE_SIZE);
            $maxFileSize = $fileSizeCheck * 1024 * 1024;

            // getting header of Url
            $this->curl->setOption(CURLOPT_URL, $csvURL);
            $this->curl->get($csvURL);
            $headers = $this->curl->getHeaders();

            $csvEncodeCheck = $this->ioFile->read($csvURL);

            // Start handling CSV check
            if (isset($headers['Content-Type']) && $headers['Content-Type'] !== 'text/csv') {
                $data = [
                    'response' => 'Error',
                    'message'=>__("File is not Csv.")
                ];
                $this->responseToJson($data);
                return false;
            }

            if (!mb_detect_encoding($csvEncodeCheck, 'UTF-8', true)) {
                $data = [
                    'response' => 'Error',
                    'message'=>__("CSV file is not in UTF-8 encoding.")
                ];
                $this->responseToJson($data);
                return false;
            }
            
            if (isset($headers['Content-Length']) && $headers['Content-Length'] > $maxFileSize) {
                $data = [
                    'response' => 'Error',
                    'message'=>__("CSV file size exceeds the limit of 2MB.")
                ];
                $this->responseToJson($data);
                return false;
            }
            // End handling CSV check

            $destinationFolder = $this->dir->getPath('pub').'/'.'api_import_csv/';

            // Create the destination folder with 777 permissions if it doesn't exist
            if (!$this->driver->isDirectory($destinationFolder)) {
                try {
                    $this->driver->createDirectory($destinationFolder, 0777);
                } catch (\Exception $e) {
                    $data = [
                        'response' => 'Error',
                        'message' => "Failed to create the destination folder, Contact Support."
                    ];
                    $this->responseToJson($data);
                    return false;
                }
            }

            // Get the name from the CSV URL
            $pathInfo = $this->ioFile->getPathInfo($csvURL);
            $csvFileName = $pathInfo['basename'];

            // Check if a file with the same name already exists in the destination folder
            $localFilePath = $destinationFolder . $csvFileName;
            
            $i = 1;
            while ($this->ioFile->fileExists($localFilePath)) {
                $timestamp = date('Y-m-d_H-i-s');
                $csvFileName = $csvFileName . '_' . $timestamp . '_' . $i . '.' . 'csv';
                $localFilePath = $destinationFolder . $csvFileName;
                $i++;
            }
        
            // Download the CSV file and save it locally
            try {
                $csvData = $this->ioFile->read($csvURL);
                $this->ioFile->write($localFilePath, $csvData, 0777);
                return $localFilePath;
            } catch (\Exception $e) {
                $data = [
                    'response' => 'Error',
                    'message'=>__("Failed to download or save the CSV file.")
                ];
                $this->responseToJson($data);
                return false;
            }

            return $localFilePath;
        } catch (\Exception $e) {
            if ($e->getMessage() == '') {
                $data = [
                    'response' => 'Error',
                    'message'=>__($e->getMessage())
                ];
            }
            $this->responseToJson($data);
            return false;
        }
    }

    /**
     * Save a log entry for the CSV import with the provided import path and comment.
     *
     * @param string $importPath The path of the CSV file being imported.
     * @param string $comment : Additional information or comments related to the import.
     *                          Defaults to 'No issue' if not provided.
     * @return void
     */
    protected function saveCsvImportLog($importPath, $comment = 'No issue')
    {
        $isTableLogEnabled = $this->scopeConfig->getValue(self::IS_TABLE_LOG_ENABLED);
    
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

    /**
     * Convert the provided data to JSON format and send it as a response.
     *
     * @param mixed $data The data to be converted to JSON and sent as a response.
     * @return void
     */
    public function responseToJson($data)
    {
        $this->sendEmail($data['message']);

        $this->apiResponse->setHeader('Content-Type', 'application/json', true)
                        ->setBody(json_encode($data))
                        ->sendResponse();
    }

    /**
     * Send an email with the provided subject and body.
     *
     * @param string $subject The subject of the email.
     * @param string $body The body of the email.
     * @return void
     */
    protected function sendEmail($body)
    {
        $isEmailLogEnabled = $this->scopeConfig->getValue(self::IS_EMAIL_LOG_ENABLED);

        if ($isEmailLogEnabled) {
            $senderEmails = explode(',', $this->scopeConfig->getValue(self::CSV_SENDER_EMAILS));
            $receiverEmails = explode(',', $this->scopeConfig->getValue(self::CSV_REPORT_EMAILS));

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('csv_import_notification')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $this->storeManager->getStore()->getId(),
                ])
                ->setTemplateVars([
                    'report' => $body,
                ])
                ->setFrom(['email' => $senderEmails[0], 'name' => 'CSV Import Data'])
                ->addTo($receiverEmails)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();
        }
    }
}
