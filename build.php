<?php

$zip = new ZipArchive();


$filename = "./exportupdatesRelease.zip";

if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    exit("cannot open <$filename>\n");
}


$source = dirname(__FILE__);

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

foreach ($files as $file) {
    $file = str_replace('\\', '/', $file);

    // Ignore "." and ".." folders
    if (
        preg_match('/\.idea|\.git/', $file) ||
        preg_match('/README\.md/', $file) ||
        preg_match('/\.gitignore/', $file) ||
        preg_match('/exportupdatesRelease/', $file) ||
        preg_match('/composer\.json/', $file) ||
        preg_match('/composer\.phar/', $file) ||
        preg_match('/composer\.lock/', $file) ||
        preg_match('/build\.php/', $file) ||
        preg_match('/README\.md/', $file) ||
        in_array(substr($file, strrpos($file, '/') + 1), array('.', '..','.idea','.git','.DS_Store'))) {
        continue;
    }

    $file = realpath($file);

    if (is_dir($file) === true) {
        $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
    } else if (is_file($file) === true) {
        $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
    }
}

$zip->close();

