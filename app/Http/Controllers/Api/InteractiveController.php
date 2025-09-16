<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InteractiveImage;
use Illuminate\Http\Request;

class InteractiveController extends Controller
{
    /**
     * Generate QR Code contain path to download the image
     *
     * @param  string  $deviceId
     * @return void
     */
    public function generateImageQrCode(Request $request)
    {
        try {
            $date = date('Y-m-d');
            $filepath = "interactive/qr/tp/{$date}";

            if (! is_dir(storage_path('app/public/'.$filepath))) {
                mkdir(storage_path('app/public/'.$filepath), 0777, true);
            }

            // make indentifier
            $identifier = uniqid(prefix: 'tpc');

            $rand = random_int(1, 20);
            $dateName = date('YmdHis');
            $filename = "{$dateName}{$rand}.png";
            $image = uploadBase64($request->getContent(), $filepath);
            if ($image) {
                // create qr
                $qrcode = generateQrcode(env('APP_URL').'/interactive/download?if='.$identifier, $filepath.'/'.$filename);

                // store to database
                InteractiveImage::create([
                    'filepath' => $filepath.'/'.$image,
                    'qrcode' => $filepath.'/'.$filename,
                    'identifier' => $identifier,
                ]);
            }

            return $qrcode ? 'data:image/png;base64,'.base64_encode(file_get_contents(storage_path("app/public/{$qrcode}"))) : '';
        } catch (\Throwable $th) {
            return json_encode([
                'error' => $th->getMessage(),
            ]);
        }
    }

    public function download()
    {
        $identifier = request('if');

        // get from database
        $image = InteractiveImage::select('qrcode', 'filepath')
            ->where('identifier', $identifier)
            ->first();

        if (! $image) {
            return view('interactive/image_not_found');
        }

        $filepath = public_path("storage/{$image->filepath}");

        return \Illuminate\Support\Facades\Response::download($filepath);
    }
}
