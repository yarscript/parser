<?php


namespace App\Feeds\Parser;


use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class Excel2007_NonStandardNamespacesWorkaround extends Xlsx
{
    public function __construct()
    {
        parent::__construct();
        $this->securityScanner->setAdditionalCallback([self::class, 'securityScan']);
    }

    public static function securityScan($xml)
    {
        return str_replace(
            [
                '<x:',
                '</x:',
                /*':x=',*/
                '<d:',
                '</d:',
                /*, ':d='*/
            ],
            [
                '<',
                '</',
                /*'=',*/
                '<',
                '</',
                /*, '='*/
            ],
            $xml
        );
    }
}
