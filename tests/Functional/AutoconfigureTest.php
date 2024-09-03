<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 21. 8. 2024
 * Time: 20:36
 */

namespace BugCatcher\Tests\Functional;

use BugCatcher\Tests\App\Kernel;
use BugCatcher\Tests\App\KernelTestCase;

class AutoconfigureTest extends KernelTestCase
{

    const RECIPE_DIR = __DIR__ . '/../../config/recipes';
    const CONFIG_DIR_NAME = 'configRecipe';
    const CONFIG_DIR = __DIR__ . '/../App/' . self::CONFIG_DIR_NAME;

    /**
     * @beforeClass
     */
    public static function setUpSomeSharedFixtures(): void
    {
        $kernel = self::bootKernel([
            "configDir" => self::CONFIG_DIR_NAME,
            "preBoot" => self::inicializeConfig(...),
        ]);
    }

    public static function inicializeConfig(Kernel $kernel): void
    {
        $configDir = $kernel->getConfigDir();

        $recipeDir = realpath(self::CONFIG_DIR);
        self::assertNotFalse($recipeDir);
        $directories = glob($recipeDir . '/*/*');
        foreach ($directories as $path) {
            $configPath = substr($path, strlen($recipeDir) + 1);
            $newFileName = "{$configDir}/{$configPath}";
            $dir = dirname($newFileName);
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            copy($path, $newFileName);
        }
        self::assertFileExists("{$configDir}/packages/doctrine.yaml");
    }

    /**
     * @afterClass
     */
    public static function tearDownSomeSharedFixtures(): void
    {
        // ...
    }

    private static function removeDir(string $dir): void
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_dir($dir . '/' . $file)) {
                self::removeDir($dir . '/' . $file);
            } else {
                unlink($dir . '/' . $file);
            }
        }
        rmdir($dir);
    }
}