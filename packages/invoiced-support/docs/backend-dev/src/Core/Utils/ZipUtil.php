<?php

namespace App\Core\Utils;

use ZipArchive;

class ZipUtil
{
    /**
     * Creates a compressed zip file
     * Stolen from: http://davidwalsh.name/create-zip-php.
     */
    public static function createZip(array $files, string $tempDir, string $filePrefix, string $destination): ?string
    {
        // if the zip file already exists and overwrite is false, return false
        if (file_exists($destination)) {
            return null;
        }
        // vars
        $valid_files = [];
        // if files were passed in...
        // cycle through each file
        foreach ($files as $file) {
            // make sure the file exists
            if (file_exists($file)) {
                $valid_files[] = $file;
            }
        }
        // if we have good files...
        if (count($valid_files)) {
            // create the archive
            $zip = new ZipArchive();
            if (true !== $zip->open($destination, ZipArchive::CREATE)) {
                return null;
            }
            // add the files
            foreach ($valid_files as $file) {
                $zippedName = str_replace([$tempDir.'/', $filePrefix], ['', ''], $file);
                $zip->addFile($file, $zippedName);
            }

            // close the zip -- done!
            $zip->close();

            // check to make sure the file exists
            return $destination;
        }

        return null;
    }
}
