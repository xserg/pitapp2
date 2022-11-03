<?php

namespace App\Services;


use Illuminate\Support\Facades\Storage;

class Filesystems
{
    static function imagesFilesystem() {
        return Storage::disk(env('IMAGES_FILESYSTEM', 'local'));
    }
}