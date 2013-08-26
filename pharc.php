<?php

class helper
{
    /**
     * Write string
     *
     * @param string $string
     */
    protected function write($string)
    {
        echo $string;
    }

    /**
     * Write string and EOL
     *
     * @param string $string
     */
    protected function writeLn($string)
    {
        $this->write("{$string}\n");
    }

    /**
     * Build mpr package (.phar) by package manifest array
     *
     * @param      $lib_path
     * @param      $phar_file
     * @param bool $pharHeader
     *
     * @return bool Result
     */
    public function createMprPackage($lib_path, $phar_file, $pharHeader = false)
    {
        try {
            $manifest = $this->loadManifest($lib_path);
            if(!$manifest) {
                throw new \Exception("Could not load or validate manifest!");
            }
            if(file_exists($phar_file)) {
                $this->writeLn("[ERROR] Package already exists!");
                return true;
            }
            $phar = new \Phar($phar_file);
            $phar->buildFromDirectory($lib_path);
            $default_stub = $phar->createDefaultStub($manifest['package']['init']);
            if($pharHeader) {
                $phar->setStub($default_stub);
            } else {
                $runnable = "#!/usr/bin/php" . PHP_EOL;
                $phar->setStub($runnable.$default_stub);
            }
            $phar->compressFiles(\Phar::GZ);
            if(!file_exists($phar_file)) {
                throw new \Exception("Unable to create file!");
            }
            $this->writeLn(sprintf("\nDone! File size: %.-4f", round(filesize($phar_file) / 1024 / 1024, 4)));
            return true;
        } catch(\Exception $e) {
            $this->writeLn("[ERROR] {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Load manifest by package folder path
     *
     * @param string $fullpath Path to package folder
     * @return bool Result
     */
    protected function loadManifest($fullpath)
    {
        $manifest_path = "{$fullpath}/manifest.mpr.json";
        if(!file_exists($manifest_path)) {
            return false;
        }
        $manifest_data = file_get_contents($manifest_path);
        $manifest = json_decode($manifest_data, true);
        if($manifest == null) {
            $this->writeLn("[ERROR] unable to parse manifest.mpr.json!");
            return false;
        }
        return $this->validateManifest($manifest) ? $manifest : false;
    }

    /**
     * Validate current manifest
     *
     * @param array $package Package manifest
     * @return bool Result
     */
    protected function validateManifest(&$package)
    {
        return (
                is_array($package) &&
                $this->checkParam($package, "name") &&
                $this->checkParam($package, "description") &&
                $this->checkParam($package, "package", true) &&
                $this->checkParam($package['package'], "path") &&
                $this->checkParam($package['package'], "init") &&
                $this->checkParam($package['package'], "version") &&
                $this->checkParam($package, "meta", true) &&
                $this->checkParam($package['meta'], "type") &&
                $this->checkParam($package['meta'], "tags") &&
                $this->checkParam($package, "depends", true)
        );
    }

    /**
     * Check param to be valid
     *
     * @param array $package Package manifest array
     * @param string $param Param key
     * @param bool $needBeArray Is this param need be an instance of array
     * @return bool Result
     */
    protected function checkParam(&$package, $param, $needBeArray = false)
    {
        if(!isset($package[$param])) {
            $this->writeLn("`{$param}` parameter is not set");
            return false;
        }
        if($needBeArray && !is_array($package[$param])) {
            $this->writeLn("`{$param}` parameter is not array");
            return false;
        }
        return true;
    }
}

if(!isset($GLOBALS['argv'][1]) || !isset($GLOBALS['argv'][2])) {
    print "Error!\n";
    print "Example usage: pharc /path/to/src /path/to/phar/file/phar\n";
    exit(1);
}

$lib_path = trim($GLOBALS['argv'][1]);
$pharfile = trim($GLOBALS['argv'][2]);

$helper = new helper();
$helper->createMprPackage(realpath($lib_path), realpath(dirname($pharfile)) . '/' . basename($pharfile));