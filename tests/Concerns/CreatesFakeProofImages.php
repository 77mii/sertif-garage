<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

trait CreatesFakeProofImages
{
    protected function createFakeProofImage(string $filename = 'bukti-bayar.png'): UploadedFile
    {
        if (extension_loaded('gd')) {
            return UploadedFile::fake()->image($filename, 200, 200);
        }

        // Authentic 1x1 PNG binary so image & mimes validation passes even without ext-gd.
        $pngBinary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($filename, $pngBinary);
    }
}
