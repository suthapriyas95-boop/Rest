<?php
// @codingStandardsIgnoreFile
 
/**
 * Module Packager Script
 *
 * This script creates ZIP archives for modules based on their composer.json files.
 *
 * @category  Build
 * @package   Packager
 * @author    Visa Acceptance
 * @copyright Copyright (c) 2025
 * @license   MIT License
 */
 
declare(strict_types=1);
 
namespace VisaAcceptance\Build;
 
/**
 * Class Packager
 *
 * Handles the packaging of modules into ZIP archives
 */
class Packager
{
    /**
     * Module directory constant
     */
    public const MODULE_DIR = '.';
 
    /**
     * Run the packaging process
     *
     * @return void
     */
    public function run(): void
    {
        $subdirs = $this->getDirList();
 
        if (!is_dir('build')) {
            mkdir('build');
        }
        array_walk($subdirs, [$this, 'processModuleDir']);
    }
 
    /**
     * Get list of directories to process
     *
     * @return array<int, string>|false
     */
    private function getDirList(): array|false
    {
        $currentDir = getcwd();
 
        return glob($currentDir . '/' . self::MODULE_DIR . '/*', GLOB_ONLYDIR);
    }
 
    /**
     * Process a single module directory
     *
     * @param string $dirname The directory name to process
     *
     * @return void
     */
    private function processModuleDir(string $dirname): void
    {
        $composerFileName = $dirname . '/composer.json';
 
        if (!file_exists($composerFileName)) {
            return;
        }
 
        $composerContent = file_get_contents($composerFileName);
        if ($composerContent === false) {
            return;
        }
 
        $packageData = json_decode($composerContent, true);
 
        if (!is_array($packageData)) {
            return;
        }
 
        $archiveName = $this->getArchiveName($packageData);
 
        if ($this->createArchive($dirname, $archiveName) !== 0) {
            return;
        }
 
        fwrite(STDOUT, "Package $archiveName created. \n");
    }
 
    /**
     * Create a ZIP archive from a directory
     *
     * @param string $directory      The directory to archive
     * @param string $targetFileName The target archive filename
     *
     * @return int Return code (0 = success, -1 = file exists, other = error)
     */
    private function createArchive(string $directory, string $targetFileName): int
    {
        $origDir = getcwd();
 
        if ($origDir === false) {
            return -1;
        }
 
        if (file_exists('build/' . $targetFileName)) {
            return -1;
        }
 
        chdir($directory);
 
        // Using ZipArchive instead of exec for PHP 8.4 compatibility
        $zip = new \ZipArchive();
        $zipPath = '../build/' . $targetFileName;
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            chdir($origDir);
            return 1;
        }
 
        $this->addDirectoryToZip($zip, '.', '');
        $zip->close();
 
        chdir($origDir);
 
        return 0;
    }
 
    /**
     * Add directory contents to ZIP archive
     *
     * @param \ZipArchive $zip
     * @param string $dir
     * @param string $zipPath
     *
     * @return void
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $dir, string $zipPath): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
 
        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $zipPath . substr($filePath, strlen(realpath($dir)) + 1);
            // Convert Windows path separators to forward slashes for zip consistency
            $relativePath = str_replace('\\', '/', $relativePath);
 
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
 
    /**
     * Get archive name from composer.json data
     *
     * @param array<string, mixed> $jsonData The composer.json data
     *
     * @return string The archive filename
     */
    private function getArchiveName(array $jsonData): string
    {
        $version = $jsonData['version'] ?? false;
        $packageName = $jsonData['name'] ?? '';
        $parts = explode('/', $packageName);
 
        return $parts[1] . ($version ? '-' . $version : '') . '.zip';
    }
}
 
$packager = new \VisaAcceptance\Build\Packager();
$packager->run();