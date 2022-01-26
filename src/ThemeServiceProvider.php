<?php

namespace Oasin\Theme;

use Carbon\Carbon;
use Facade\IgnitionContracts\SolutionProviderRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Oasin\Theme\Commands\MakeThemeCommand;
use Oasin\Theme\SolutionProviders\ThemeSolutionProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;


class ThemeServiceProvider extends ServiceProvider
{
    /**
     * @var string|null $css The path to use for the css directive, or null to not apply the directive.
     */
    private $css = "css";

    /**
     * @var string|null $js The path to use for the javascript directive, or null to not apply the directive.
     */
    private $js = "js";

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/theme.php' => config_path('theme.php'),
            ], 'config');

            $this->commands([
                MakeThemeCommand::class,
            ]);
        }

        $this->registerBladeDirectives();

    }

    public function register()
    {
        $this->mergeConfig();

        $this->registerHelper();

        $this->registerThemeFinder();

        $this->consoleCommand();

        $this->registerSolutionProvider();
    }

    /**
     * Add Commands.
     *
     * @return void
     */
    public function consoleCommand()
    {

        $this->commands([
            MakeThemeCommand::class,
        ]);

    }


    protected function mergeConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/theme.php', 'theme');
    }

    protected function registerSolutionProvider(): void
    {
        try {
            $solutionProvider = $this->app->make(SolutionProviderRepository::class);

            $solutionProvider->registerSolutionProvider(
                ThemeSolutionProvider::class
            );
        } catch (BindingResolutionException $error) {
        }
    }

    protected function registerThemeFinder(): void
    {
        $this->app->singleton('theme.finder', function ($app) {
            $themeFinder = new ThemeViewFinder(
                $app['files'],
                $app['config']['view.paths']
            );

            $themeFinder->setHints(
                $this->app->make('view')->getFinder()->getHints()
            );

            return $themeFinder;
        });

        if (config('theme.active')) {
            $this->app->make('theme.finder')->setActiveTheme(config('theme.active'), config('theme.parent'));
        }

        // If need to replace Laravel's view finder with package's theme.finder
        // $this->app->make('view')->setFinder($this->app->make('theme.finder'));
    }

    /**
     * Register All Helpers.
     *
     * @return void
     */
    public function registerHelper()
    {
        foreach (glob(__DIR__ . '/../Helpers/*.php') as $filename) {
            require_once $filename;
        }
    }

    protected function registerBladeDirectives()
    {
        /*--------------------------------------------------------------------------
        | Extend Blade to support Orcherstra\Asset (Asset Managment)
        |
        | Syntax:
        |
        |   @css (filename, alias, depends-on-alias)
        |   @js  (filename, alias, depends-on-alias)
        |--------------------------------------------------------------------------*/


        if ($this->css !== null) {
            Blade::directive("css", function ($parameter) {
                assert(is_string($this->css));
                $file = $this->assetify(($parameter), "css", $this->css);
                return sprintf('<link media="all" type="text/css" rel="stylesheet" href="%s">', $file);
            });
        }

        if ($this->js !== null) {
            Blade::directive("js", function ($parameter) {
                assert(is_string($this->js));
                $file = $this->assetify(($parameter), "js", $this->js);
                return sprintf('<script type="text/javascript" src="%s"></script>', $file);
            });
        }

        Blade::directive('asset', function ($file) {

            $file = str_replace(['(', ')', "'"], '', $file);
            $filename = $file;

            // Internal file
            if (!Str::startsWith($file, '//') && !Str::startsWith($file, 'http')) {
                // $version = File::lastModified(themes('js/') . '/' . $file);
                $filename = $file . '?v=' . time();
                if (!Str::startsWith($filename, '/')) {
                    $filename = themes($filename);
                }
            }

            $fileType = substr(strrchr($file, '.'), 1);

            if ($fileType == 'js') {
                return sprintf('<script type="text/javascript" src="%s"></script>', $filename);
            } else {
                return sprintf('<link media="all" type="text/css" rel="stylesheet" href="%s">', $filename);
            }

        });
    }


    /**
     * Convert a simple name into a full asset path.
     *
     * @param string $file The simple file name
     * @param string $type The type of asset (css/js)
     * @param string $path The path the asset is stored at
     *
     * @return string The full path to the asset
     */
    private function assetify(string $file, string $type, string $path): string
    {
        if (in_array(substr($file, 0, 1), ["'", '"'], true)) {
            $file = trim($file, "'\"");
        } else {
            return "{{ {$file} }}";
        }

        if (substr($file, 0, 8) === "https://") {
            return $file;
        }

        if (substr($file, 0, 7) === "http://") {
            return $file;
        }

        if (substr($file, 0, 1) !== "/") {
            $path = trim($path, "/");
            if (strlen($path) > 0) {
                $path = "{$path}/";
            } else {
                $path = "/";
            }
            $file = Theme::assets($path . $file);
        }

        if (substr($file, (strlen($type) + 1) * -1) !== ".{$type}") {
            $file .= ".{$type}";
        }

        return $file;
    }

}
