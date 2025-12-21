<?php
namespace App\Http\Controllers;

use App\Managers\PrinterManager;
use App\Services\PrinterService;
use Illuminate\Http\Request;

class PrinterController extends Controller
{

    public function print(Request $request)
    {
        $m = new PrinterManager();
        $s = new PrinterService($m);

        if ($request->has('raw-text')) {
            $s->printRawText($request->input('raw-text'),$request->input('part',2));
        } elseif ($request->has('filename')) {
            $s->print($request->input('filename'));
            return response()->json(['success' => true], 200);
        } else {
            return response()->json(['error' => 'No content provided to print'], 400);
        }
    }

    public function printFromData(Request $request)
    {
        $m = new PrinterManager();
        $s = new PrinterService($m);

        $s->printFromData($request->all(),$request->input('part',2));
        return response()->json(['success' => true], 200);
    }

}
