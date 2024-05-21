<?php

set_error_handler(
    static function($errno, $errstr, $errfile, $errline) {
        $message = trim((string) $errstr) ?: 'Error {$errno}';
        if ($errfile && $errfile !== 'Unknown') {
            $message .= "\nFile: {$errfile}";
            if ($errline) {
                $message .= "\nLine: {$errline}";
            }
        }
        throw new RuntimeException($message);
    },
    -1
);

class Checker
{
    /**
     * @var string
     */
    private $rootDir;

    public function __construct()
    {
        $this->rootDir = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
    }

    /**
     * @return string[]
     */
    public function check()
    {
        $errors = [];
        foreach ($this->listFiles('concrete') as $phpFile) {
            if ($this->isSkipFile($phpFile)) {
                continue;
            }
            $error = $this->checkFile($phpFile);
            if ($error !== '') {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @param string $phpFile
     *
     * @return string
     */
    private function checkFile($phpFile)
    {
        $message = '';
        try {
            $compiled = opcache_compile_file($this->rootDir . '/' . $phpFile);
        } catch (RuntimeException $x) {
            $message = trim($x->getMessage());
            $compiled = false;
        }
        if ($compiled !== true) {
            if (!$message) {
                $message = 'Compilation failed';
            }
            return "{$message}\nFile: {$phpFile}";
        }
        return '';
    }

    /**
     * @param string $parentRelativeDir
     *
     * @return \Generator<string>
     */
    private function listFiles($parentRelativeDir)
    {
        $parentAbsoluteDir = $this->rootDir . ($parentRelativeDir === '' ? '' : "/{$parentRelativeDir}");
        $items = scandir($parentAbsoluteDir);
        if ($items === false) {
            throw new RuntimeException("Failed to list contents of directory {$parentAbsoluteDir}");
        }
        foreach ($items as $item) {
            if (in_array($item, ['.', '..', 'vendor'], true)) {
                continue;
            }
            $itemRelative = ($parentRelativeDir === '' ? '' : "{$parentRelativeDir}/") . $item;
            if (is_dir($parentAbsoluteDir . '/' . $item)) {
                foreach ($this->listFiles($itemRelative) as $found) {
                    yield $found;
                }
            } elseif (preg_match('/^[^.].*\.php$/', $item)) {
                yield $itemRelative;
            }
        }
    }

    private function isSkipFile($phpFile)
    {
        return in_array($phpFile, [
            'concrete/src/Support/__IDE_SYMBOLS__.php',
            'concrete/src/Support/.phpstorm.meta.php',
        ], true);
    }
}

if (!function_exists('opcache_get_status')) {
    echo "OPcache is not installed.\n";
    exit(1);
}
if (opcache_get_status() === false) {
    echo "OPcache is not enabled.\nYou may need to add this line to php.ini:\nopcache.enable_cli=1\n";
    exit(2);
}

$checker = new Checker();
printf("Checking files with PHP %s (please be sure it's the minimum version supported by the core)... ", PHP_VERSION);
$errors = $checker->check();
if ($errors === []) {
    echo "no errors found.\n";
} else {
    echo "ERRORS FOUND!\n";
    foreach ($errors as $index => $error) {
        echo 'Error #', $index + 1, ') ', $error, "\n";
    }
}

exit($errors === [] ? 0 : 3);