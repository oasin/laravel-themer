<?php

namespace Oasin\Theme;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Oasin\Theme\Models\Theme as ThemeModel;
use Oasin\ThemeModels\User;
use Oasin\Theme\Models\UserTheme;
use League\Flysystem\Plugin\AbstractPlugin;
use PhpZip\Exception\ZipException;
use Illuminate\Support\Facades\Storage;

use PhpZip\ZipFile;

class Theme
{
    const DEFAULT_STATE = 'active';

    public static function finder()
    {
        return app('theme.finder');
    }

    public static function set(string $theme, string $parentTheme = null): void
    {
        self::finder()->setActiveTheme($theme, $parentTheme);
    }

    public static function clear(): void
    {
        self::finder()->clearThemes();
    }

    public static function active(): ?string
    {
        return self::finder()->getActiveTheme();
    }

    public static function parent(): ?string
    {
        return self::finder()->getParentTheme();
    }

    public static function viewPath(string $theme = null): ?string
    {
        $theme = $theme ?? self::active();

        if ($theme) {
            return self::finder()->getThemeViewPath($theme);
        }

        return null;
    }

    public static function path(string $path = null, string $theme = null): ?string
    {
        $theme = $theme ?? self::active();

        if ($theme) {
            return self::finder()->getThemePath($theme, $path);
        }

        return null;
    }

    public static function getViewPaths(): array
    {
        if (self::finder()) {
            return self::finder()->getViewFinder()->getPaths();
        }

        return app('view')->getFinder()->getPaths();
    }


    private static function rmdir(string $directory): bool
    {
        if (is_dir($directory)) {
            $objects = scandir($directory);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($directory . '/' . $object) == 'dir')
                        self::rmdir($directory . '/' . $object);
                    else
                        unlink($directory . '/' . $object);
                }
            }
            reset($objects);
            return rmdir($directory);
        }
        return false;
    }

    /**
     * @param string $themeFile
     * @param bool $setDefault
     * @return object
     */

    public static function themePath()
    {

        return config('theme.base_path') . DIRECTORY_SEPARATOR;
    }

    public static function install(string $themeFile, bool $setDefault = false): array
    {

        $theme_path = config('theme.base_path') . DIRECTORY_SEPARATOR;

        $tempDirectory = storage_path('temp');

        //  Storage::ensureDirectoryExists($theme_temp);
        File::ensureDirectoryExists($theme_path);

        $tempThemeDirectory = $tempDirectory . DIRECTORY_SEPARATOR . Str::random();

        $themeName = null;

        $paths = array(
            'views' => 'dir',
            //    'dist' => 'dir',
            'theme.json' => 'file',
        );

        $detailProperties = array(
            'name',
            'slug',
        );

        $details = new \StdClass;
        $settings = new \StdClass;

        /**
         * Check $themeFile is exists
         */
        if (!file_exists($themeFile)) {
            return array('valid' => false, 'installed' => false, 'message' => "File '{$themeFile}' not exists");
        }

        /**
         * Initialize archive
         */

        $zipFile = new ZipFile();

        try {

            $zipFile->openFile($themeFile);

            File::ensureDirectoryExists($tempDirectory);

            $entries = collect($zipFile->getEntries());

            $dir = '';

            if ($entries->first()->isDirectory()) {
                $dir = trim($entries->first()->getName(), '/');
            }

            File::ensureDirectoryExists($tempThemeDirectory);

            foreach ($entries as $path => $type) {
                if ($type == 'dir') {
                    if (!is_dir($tempThemeDirectory . '/' . $path))
                        return array('valid' => false, 'installed' => false, 'message' => "Directory not exists in theme: " . '/' . $path);
                } else if ($type == 'file') {
                    if (!file_exists($tempThemeDirectory . '/' . $path))
                        return array('valid' => false, 'installed' => false, 'message' => "File not exists in theme: " . '/' . $path);
                }
            }

            $details = json_decode($zipFile->getEntryContents('theme.json'), false);

            $settings = ($zipFile->hasEntry('settings.json') ?
                json_decode($zipFile->getEntryContents('settings.json'), false) : null);

            foreach ($detailProperties as $property)
                if (blank(data_get($details, $property, null)))
                    return array(
                        'valid' => false,
                        'message' => "'{$property}' property not defined in 'theme.json'"
                    );

            $zipFile->extractTo($tempThemeDirectory)
                ->deleteFromRegex('~^\.~');

            $slug = Str::slug(data_get($details, 'slug'), '_');

            $name = data_get($details, 'name', '');


            if (!self::recursiveChmod($tempThemeDirectory)) {
                return array('valid' => true, 'installed' => false, 'message' => "The theme `{$name}` has persmission error");
            }

            /**
             * Check theme is already installed
             */
            if (
                is_dir($theme_path . $slug) || ThemeModel::where('slug', $slug)->first() != null
            ) {
                File::deleteDirectory($tempThemeDirectory);
                return array('valid' => true, 'installed' => false, 'message' => "The theme `{$name}` is already installed");
            }

            /**
             * Install theme
             */
            $theme = ThemeModel::create([
                'state' => self::DEFAULT_STATE,
                'default' => $setDefault,
                'slug' => $slug,
                'name' => data_get($details, 'name'),
                'details' => $details,
                'settings' => $settings,
            ]);

            File::moveDirectory($tempThemeDirectory, self::themePath() . $slug);

            File::delete($themeName);

        } catch (ZipException $e) {
            return array('valid' => false, 'installed' => false, 'message' => $e->getMessage());
        } finally {
            $zipFile->close();
        }

        return array(
            'valid' => true,
            'installed' => true,
            'message' => "The theme `{$name}` has been installed installed."
        );
    }

    public static function recursiveChmod($path, $filePerm = 0644, $dirPerm = 0755)
    {
        // Check if the path exists
        if (!file_exists($path)) {
            return (false);
        }

        // See whether this is a file
        if (is_file($path)) {
            // Chmod the file with our given filepermissions
            chmod($path, $filePerm);

            // If this is a directory...
        } elseif (is_dir($path)) {
            // Then get an array of the contents
            $foldersAndFiles = scandir($path);

            // Remove "." and ".." from the list
            $entries = array_slice($foldersAndFiles, 2);

            // Parse every result...
            foreach ($entries as $entry) {
                // And call this function again recursively, with the same permissions
                self::recursiveChmod($path . "/" . $entry, $filePerm, $dirPerm);
            }

            // When we are done with the contents of the directory, we chmod the directory itself
            chmod($path, $dirPerm);
        }

        // Everything seemed to work out well, return true
        return (true);
    }


    public static function uninstall(string $themeName, bool $withFiles = false, bool $forceDelete = false): bool
    {
        /**
         * Check theme is installed
         */
        if (!self::isInstalled($themeName)) return false;

        /**
         * Get theme in database
         */
        $theme = ThemeModel::where('slug', $themeName)->first();

        /**
         * if force delete enabled just add deleted flag to theme else delete from database
         */
        if ($forceDelete) $theme->delete();

        else {
            $theme->state = 'deleted';
            $theme->save();
        }

        /**
         * check if delete with files
         */
        if ($withFiles) {
            /**
             * try to delete theme files
             */
            if (self::rmdir(self::themePath() . $themeName)) return true;
            else return false;
        }
        return true;
    }


    public static function forceDelete($themeName)
    {
        return self::uninstall($themeName, true, true);
    }

    /**
     * @param string $themeName
     * @return bool
     */
    public static function isInstalled(string $themeName): bool
    {
        $theme = ThemeModel::where('slug', $themeName)->first();
        if ($theme == null) return false;
        if (is_dir(self::themePath() . $themeName)) return true;
        return false;
    }


    /**
     * Find asset file for theme asset.
     *
     * @param string $path
     * @param null|bool $secure
     *
     * @return string
     */
    public static function assets($path = null, $secure = null)
    {
        $splitThemeAndPath = explode(':', $path);

        if (count($splitThemeAndPath) > 1) {
            if (is_null($splitThemeAndPath[0])) {
                return;
            }
            $themeName = $splitThemeAndPath[0];
            $path = $splitThemeAndPath[1];
        } else {
            $themeName = self::active();
            $path = $splitThemeAndPath[0];
        }


        $themePath = self::themePath() . $themeName . '/';


        $assetPath = config('theme.folders.assets') . '/';
        $fullPath = $themePath . $assetPath . $path;

        if (!file_exists($fullPath)) {

            $themePath = str_replace(base_path() . '/', '', self::path()) . '/';
            $fullPath = $themePath . $assetPath . $path;

            return asset($fullPath, $secure);
        }

        return asset($fullPath, $secure);
    }


    /*--------------------------------------------------------------------------
    | Blade Helper Functions
    |--------------------------------------------------------------------------*/

    /**
     * Return css link for $href
     *
     * @param string $href
     * @return string
     */
    public function css($href)
    {
        return sprintf('<link media="all" type="text/css" rel="stylesheet" href="%s">', $this->assets($href));
    }

    /**
     * Return script link for $href
     *
     * @param string $href
     * @return string
     */
    public function js($href)
    {
        return sprintf('<script src="%s"></script>', $this->assets($href));
    }

    /**
     * Return img tag
     *
     * @param string $src
     * @param string $alt
     * @param string $Class
     * @param array $attributes
     * @return string
     */
    public function img($src, $alt = '', $class = '', $attributes = [])
    {
        return sprintf(
            '<img src="%s" alt="%s" class="%s" %s>',
            $this->assets($src),
            $alt,
            $class,
            $this->HtmlAttributes($attributes)
        );
    }


    /**
     * Return attributes in html format
     *
     * @param array $attributes
     * @return string
     */
    private function HtmlAttributes($attributes)
    {
        $formatted = join(' ', array_map(function ($key) use ($attributes) {
            if (is_bool($attributes[$key])) {
                return $attributes[$key] ? $key : '';
            }
            return $key . '="' . $attributes[$key] . '"';
        }, array_keys($attributes)));
        return $formatted;
    }


}
