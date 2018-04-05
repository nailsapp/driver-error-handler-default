<?php

namespace Nails\Common\ErrorHandler;

use Nails\Common\Exception\NailsException;
use Nails\Common\Interfaces\ErrorHandlerDriver;
use Nails\Factory;

class DefaultHandler implements ErrorHandlerDriver
{
    /**
     * Instantiates the driver
     * @return void
     */
    public static function init()
    {
        //  Nothing to do, but interface requires this method be defined.
    }

    // --------------------------------------------------------------------------

    /**
     * Called when a PHP error occurs
     *
     * @param  int    $iErrorNumber The error number
     * @param  string $sErrorString The error message
     * @param  string $sErrorFile   The file where the error occurred
     * @param  int    $iErrorLine   The line number where the error occurred
     *
     * @return void
     */
    public static function error($iErrorNumber, $sErrorString, $sErrorFile, $iErrorLine)
    {
        //  Don't clog the logs up with strict notices
        if ($iErrorNumber === E_STRICT) {
            return;
        }

        $aData = [
            'iNumber'   => $iErrorNumber,
            'sMessage'  => $sErrorString,
            'sFile'     => $sErrorFile,
            'iLine'     => $iErrorLine,
            'sSeverity' => 'Unknown',
        ];

        //  Should we show this error?
        if ((bool) ini_get('display_errors') && error_reporting() !== 0) {

            $oErrorHandler = Factory::service('ErrorHandler');

            if (array_key_exists($iErrorNumber, $oErrorHandler::LEVELS)) {
                $aData['sSeverity'] = $oErrorHandler::LEVELS[$iErrorNumber];
            }

            $oErrorHandler = Factory::service('ErrorHandler');
            $oErrorHandler->renderErrorView('php', $aData, true);
        }

        //  Show we log the item?
        if (function_exists('config_item') && config_item('log_threshold') != 0) {
            log_message(
                'error',
                $aData['sMessage'] . ' (' . $aData['sFile'] . ':' . $aData['iLine'] . ')'
            );
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Catches uncaught exceptions
     *
     * @param  \Exception $oException The caught exception
     *
     * @return void
     */
    public static function exception($oException)
    {
        $oDetails = (object) [
            'type' => get_class($oException),
            'code' => $oException->getCode(),
            'msg'  => $oException->getMessage(),
            'file' => $oException->getFile(),
            'line' => $oException->getLine(),
            'url'  => $oException instanceof NailsException ? $oException->getDocumentationUrl() : null,
        ];

        $sSubject = $oDetails->msg;
        $sMessage = 'Uncaught Exception with code: ' . $oDetails->code;

        //  Show we log the item?
        if (function_exists('config_item') && config_item('log_threshold') != 0) {
            log_message('error', $sMessage);
        }

        $oErrorHandler = Factory::service('ErrorHandler');
        $oErrorHandler->sendDeveloperMail($sSubject, $sMessage);
        $oErrorHandler->showFatalErrorScreen($sSubject, $sMessage, $oDetails);
    }

    // --------------------------------------------------------------------------

    /**
     * Catches fatal errors on shut down
     * @return void
     */
    public static function fatal()
    {
        $aError = error_get_last();

        if (!is_null($aError) && $aError['type'] === E_ERROR) {

            $oDetails = (object) [
                'type' => 'Fatal Error',
                'code' => $aError['type'],
                'msg'  => $aError['message'],
                'file' => $aError['file'],
                'line' => $aError['line'],
            ];

            $sSubject = 'Fatal Error';
            $sMessage = $aError['message'] . ' in ' . $aError['file'] . ' on line ' . $aError['line'];

            $oErrorHandler = Factory::service('ErrorHandler');
            $oErrorHandler->sendDeveloperMail($sSubject, $sMessage);
            $oErrorHandler->showFatalErrorScreen($sSubject, $sMessage, $oDetails);
        }
    }
}
