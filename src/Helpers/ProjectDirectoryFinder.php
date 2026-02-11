<?php

declare(strict_types=1);

namespace Rector\ArgTyper\Helpers;

use Webmozart\Assert\Assert;

final class ProjectDirectoryFinder
{
    /**
     * Fallback directory names if composer.json cannot be read
     * @var string[]
     */
    private const array FALLBACK_CODE_DIRECTORIES = ['src', 'lib', 'app', 'test', 'tests'];

    /**
     * Returns relative paths to code directories and individual files
     * @return string[]
     */
    public function findCodeDirsRelative(string $projectPath): array
    {
        $relativePaths = [];
        foreach ($this->findCodeDirsAbsolute($projectPath) as $absolutePath) {
            $relativePaths[] = substr($absolutePath, strlen((string) realpath($projectPath)) + 1);
        }

        return $relativePaths;
    }

    /**
     * Returns absolute paths to code directories and individual files
     * @return string[]
     */
    public function findCodeDirsAbsolute(string $projectPath): array
    {
        $composerJsonPath = $projectPath . '/composer.json';

        // Try to read from composer.json autoload configuration
        if (file_exists($composerJsonPath)) {
            $autoloadPaths = $this->extractAutoloadPathsFromComposerJson($composerJsonPath, $projectPath);
            if ($autoloadPaths !== []) {
                return $autoloadPaths;
            }
        }

        // Fallback to hardcoded directory names
        return $this->findDirectoriesByNames($projectPath, self::FALLBACK_CODE_DIRECTORIES);
    }

    /**
     * @return string[]
     */
    private function extractAutoloadPathsFromComposerJson(string $composerJsonPath, string $projectPath): array
    {
        $composerJsonContent = file_get_contents($composerJsonPath);
        if ($composerJsonContent === false) {
            return [];
        }

        $composerConfig = json_decode($composerJsonContent, true);
        if (! is_array($composerConfig)) {
            return [];
        }

        $paths = [];

        // Extract from autoload section
        if (isset($composerConfig['autoload']) && is_array($composerConfig['autoload'])) {
            $paths = array_merge($paths, $this->extractPathsFromAutoloadSection($composerConfig['autoload']));
        }

        // Extract from autoload-dev section
        if (isset($composerConfig['autoload-dev']) && is_array($composerConfig['autoload-dev'])) {
            $paths = array_merge($paths, $this->extractPathsFromAutoloadSection($composerConfig['autoload-dev']));
        }

        // Normalize paths to absolute and ensure they exist (includes both directories and files)
        return $this->normalizeAndFilterPaths($paths, $projectPath);
    }

    /**
     * @param array<string, mixed> $autoloadSection
     * @return string[]
     */
    private function extractPathsFromAutoloadSection(array $autoloadSection): array
    {
        $paths = [];

        // Extract PSR-4 paths
        if (isset($autoloadSection['psr-4']) && is_array($autoloadSection['psr-4'])) {
            foreach ($autoloadSection['psr-4'] as $namespacePaths) {
                $paths = array_merge($paths, $this->normalizeAutoloadPaths($namespacePaths));
            }
        }

        // Extract PSR-0 paths
        if (isset($autoloadSection['psr-0']) && is_array($autoloadSection['psr-0'])) {
            foreach ($autoloadSection['psr-0'] as $namespacePaths) {
                $paths = array_merge($paths, $this->normalizeAutoloadPaths($namespacePaths));
            }
        }

        // Extract classmap paths (can include both directories and files)
        if (isset($autoloadSection['classmap']) && is_array($autoloadSection['classmap'])) {
            $paths = array_merge($paths, $autoloadSection['classmap']);
        }

        // Extract individual files from 'files' section
        if (isset($autoloadSection['files']) && is_array($autoloadSection['files'])) {
            return array_merge($paths, $autoloadSection['files']);
        }

        return $paths;
    }

    /**
     * @param string|string[] $paths
     * @return string[]
     */
    private function normalizeAutoloadPaths(string|array $paths): array
    {
        if (is_string($paths)) {
            return [$paths];
        }

        return $paths;
    }

    /**
     * Normalizes and filters paths, returning both directories and individual files
     * @param string[] $relativePaths
     * @return string[]
     */
    private function normalizeAndFilterPaths(array $relativePaths, string $projectPath): array
    {
        $absolutePaths = [];
        $realProjectPath = (string) realpath($projectPath);

        foreach ($relativePaths as $relativePath) {
            // Remove trailing slashes
            $relativePath = rtrim($relativePath, '/\\');

            // Skip empty paths (used for root namespace)
            if ($relativePath === '') {
                continue;
            }

            // Build absolute path
            $absolutePath = $realProjectPath . '/' . $relativePath;

            // If it's a directory, add it directly
            if (is_dir($absolutePath)) {
                $realPath = (string) realpath($absolutePath);
                if (! in_array($realPath, $absolutePaths, true)) {
                    $absolutePaths[] = $realPath;
                }
            } elseif (is_file($absolutePath)) {
                // Add only the file itself (not its parent directory)
                $realPath = (string) realpath($absolutePath);
                if (! in_array($realPath, $absolutePaths, true)) {
                    $absolutePaths[] = $realPath;
                }
            }
        }

        sort($absolutePaths);
        return $absolutePaths;
    }

    /**
     * @param string[] $directoryNames
     * @return string[]
     */
    private function findDirectoriesByNames(string $projectPath, array $directoryNames): array
    {
        Assert::allString($directoryNames);

        $absoluteDirs = [];
        $realProjectPath = (string) realpath($projectPath);

        foreach ($directoryNames as $directoryName) {
            $dirPath = $realProjectPath . '/' . $directoryName;
            if (is_dir($dirPath)) {
                $realPath = (string) realpath($dirPath);
                $absoluteDirs[] = $realPath;
            }
        }

        sort($absoluteDirs);
        return $absoluteDirs;
    }
}
