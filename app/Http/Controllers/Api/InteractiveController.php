<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InteractiveController extends Controller
{
    /**
     * Generate QR Code contain path to download the image
     *
     * @param Request $request
     * @param string $deviceId
     * @return void
     */
    public function generateImageQrCode(Request $request, $deviceId)
    {
        try {
            $date = date('Y-m-d');
            $filepath = "interactive/qr/{$deviceId}/{$date}";
    
            if (!is_dir(storage_path('app/public/' . $filepath))) {
                mkdir(storage_path('app/public/' . $filepath), 0777, true);
            }
    
            $filename = date('YmdHis') . '.png';
            $image = uploadBase64($request->getContent(), $filepath);
            if ($image) {
                // create qr
                $qrcode = generateQrcode(env('APP_URL') . '/interactive/download?file=' . $image . '&d=' . $deviceId, $filepath . '/' . $filename);
            }

            return $qrcode ? 'data:image/png;base64,' . base64_encode(file_get_contents(storage_path("app/public/{$qrcode}"))) : '';
        } catch (\Throwable $th) {
            return json_encode([
                'error' => $th->getMessage()
            ]);
        }
    }

    public function download()
    {
        $date = date('Y-m-d');
        $deviceId = request('d');
        $filepath = public_path("storage/interactive/qr/{$deviceId}/{$date}/" . request('file'));
        if (!is_file($filepath)) {
            return view('interactive/image_not_found');
        }
        return is_file($filepath);
        return \Illuminate\Support\Facades\Response::download();
    }
}
