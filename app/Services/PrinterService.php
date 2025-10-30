<?php
namespace App\Services;

use App\Exceptions\BadConfigExcetpion;
use App\Managers\PrinterManager;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrinterService
{
    protected PrinterManager $manager;
    protected Printer $printer;

    public function __construct(PrinterManager $manager)
    {
        $this->manager = $manager;
        $this->printer = $this->getPrinter();
    }
    public function print($filename, int $part = 2)
    {
        $content = $this->getContent($filename);

        for ($i = 0; $i < $part; $i++) {
            $this->printer->text($content);
            $this->printer->feed();
            $this->printer->cut();
            // echo nl2br($content);
            // echo "<br>";
        }

        $this->printer->close();

        return true;

    }

    public function printRawText($content, int $part = 2)
    {
        for ($i = 0; $i < $part; $i++) {
            $this->printer->text($content);
            $this->printer->feed();
            $this->printer->cut();
        }

        $this->printer->close();

        return true;

    }

    public function getContent($filename)
    {
        $text    = null;
        $baseUrl = $this->manager->getConfig('data_base_url');

        if (empty($baseUrl)) {
            throw new BadConfigExcetpion("fetching data base url not set. can not get data to print.");
        }
        $location = $baseUrl . $filename;
        try {
            if (realpath($location) !== false) {
                $text = file_get_contents($location);
            } elseif (parse_url($location) !== false) {
                $httpResponse = Http::get($baseUrl, [
                    'filename' => $filename,
                ]);

                if ($httpResponse->successful()) {
                    $text = $httpResponse->body();
                } else {
                    Log::error(str_replace(['/', '\\'], '_', $location), [
                        'message'  => $httpResponse->body(),
                        'status'   => $httpResponse->status(),
                        'reason'   => $httpResponse->reason(),
                        'filename' => $filename,
                    ]);
                }

            }
        } catch (Exception $e) {
            Log::error(str_replace(['/', '\\'], '_', $location), [
                "message" => $e->getMessage(),
                "trace"   => $e->getTrace(),
                "code"    => $e->getCode(),
            ]);

        }

        return $text;

    }

    public function getPrinter()
    {

        $connector = $this->getConnector();

        return new Printer($connector);
    }

    public function getConnector()
    {
        $connector = null;
        if (strtoupper(PHP_OS) == 'WIN') {
            $connector = new WindowsPrintConnector($this->manager->getConfig('name'));
        } elseif (strtoupper(PHP_OS) == 'LINUX') {
            $connector = new FilePrintConnector($this->manager->getConfig('name'));
        }

        return $connector;
    }

}
