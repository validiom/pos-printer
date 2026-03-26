<?php
namespace App\Services;

use Exception;
use Mike42\Escpos\Printer;
use App\Managers\PrinterManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Exceptions\BadConfigExcetpion;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

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

    public function printFromData($data, int $part = 2)
    {

        // dd($data);
        for ($i = 0; $i < $part; $i++) {
            //heading
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text(strtoupper($data['header']) . "\n");
            $this->printer->setTextSize(2, 2);
            $this->printer->setEmphasis(true);
            $this->printer->text($data['title'] . "\n");
            $this->printer->setEmphasis(false);
            $this->printer->setTextSize(1, 1);
            $this->printer->text($data['address'] . "\n");
            $this->printer->text($data['phones'] . "\n");
            $this->printer->feed();

            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->printer->text("Heure : " . date('Y-m-d h:i') . "\n");
            $this->printer->text("Code : " . $data['code'] . "\n");
            $this->printer->text("Caissier: " . $data['cashier'] . "\n");
            $this->printer->text("-------------------------------\n");

            //Content
            $this->printer->text("Articles:\n");
            foreach ($data['items'] as $item) {
                $this->printer->text($this->writeLine($item['label'], $item['quantity'], $item['amount'],$item['price']));
            }

            $this->printer->setJustification(Printer::JUSTIFY_RIGHT);
            if(isset($data['total_ht'])){
                $this->printer->text("\nTotal HT: " . $data['total_ht'] . "\n");
            }
            if(isset($data['tva'])){
                $this->printer->text("TVA 18%: " . $data['tva'] . "\n");
            }
            $this->printer->text("Total TTC: " . $data['total_ttc'] . "\n");

            if($data['others']){
                $this->printer->text("\nAutres:\n");
                foreach ($data['others'] as $other) {
                    $this->printer->text($other['label'] . ": " . $other['value'] . "\n");
                }
            }

            //footer
            $this->printer->feed();
            $this->printer->setFont(Printer::FONT_B);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("-------------------------------\n");
            $this->printer->text("Les produits vendus ne sont ni echanges ni repris.\n");
            $this->printer->text("Merci et a tres bientot!" . "\n");
            $this->printer->text("-------------------------------\n");
            $this->printer->feed();
            $this->printer->cut();
            $this->printer->setFont(Printer::FONT_A);

        }

        $this->printer->close();
    }

    public function writeLine($label, $qt, $amount,$price)
    {
        $formatedPrice = number_format($price, 0, '.', ' ');
        $FormatedAmount = number_format($amount, 0, '.', ' ');

        // dd($formatedPrice, $FormatedAmount);
        // Logic to write a line to the printer
        $t        = "- " . $label . " ( ".$formatedPrice . " x" . $qt . " ): ";
        $ttLenght = strlen($t . $FormatedAmount);
        $times    = ceil($ttLenght / 48);
        $length   = 48 * $times - (strlen($t) + strlen($FormatedAmount));

        $t .= str_repeat(".", $length);
        // dd($length, $ttLenght, $times, $t.$FormatedAmount);
        return $t . $FormatedAmount . "\n";
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
