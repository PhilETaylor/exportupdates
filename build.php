<?php

/**
 * @copyright  Copyright (C) 2017, 2018 Blue Flame Digital Solutions Limited / Phil Taylor. All rights reserved.
 * @author     Phil Taylor <phil@phil-taylor.com>
 *
 * @see        https://github.com/PhilETaylor/maintain.myjoomla.com
 *
 * @license    Commercial License - Not For Distribution.
 */
$zip = new ZipArchive();

$filename = '../release/joomla.zip';

if (true !== $zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
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
        preg_match('/\.formatter.yml/', $file) ||
        preg_match('/cleanup\.sh/', $file) ||
        preg_match('/\.php_cs\.cache/', $file) ||
        preg_match('/exportupdatesRelease/', $file) ||
        preg_match('/composer\.json/', $file) ||
        preg_match('/composer\.phar/', $file) ||
        preg_match('/composer\.lock/', $file) ||
        preg_match('/build\.php/', $file) ||
        preg_match('/README\.md/', $file) ||
        in_array(substr($file, strrpos($file, '/') + 1), array('.', '..', '.idea', '.git', '.DS_Store'))) {
        continue;
    }

    $file = realpath($file);

    if (true === is_dir($file)) {
        $zip->addEmptyDir(str_replace($source.'/', '', $file.'/'));
    } elseif (true === is_file($file)) {
        $zip->addFromString(str_replace($source.'/', '', $file), file_get_contents($file));
    }
}

$zip->close();
