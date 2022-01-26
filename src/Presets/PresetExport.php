<?php

namespace Oasin\Theme\Presets;

use Oasin\Theme\Presets\Traits\HandleFiles;
use Oasin\Theme\Theme;

class PresetExport
{
    use HandleFiles;

    /**
     * @var string
     */
    protected $theme;

    /**
     * @var string
     */
    protected $slug;

    /**
     * @var string
     */
    public $cssFramework;

    /**
     * @var string
     */
    public $jsFramework;

    public function __construct(string $theme, string $slug , string $cssFramework, string $jsFramework)
    {
        $this->theme = $theme;
        $this->slug = $slug;
        $this->cssFramework = $cssFramework;
        $this->jsFramework = $jsFramework;

        $this->ensureDirectoryExists(Theme::path('', $slug));
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function export(): void
    {
        if ($this->cssPreset()) {
            $this->cssPreset()->export();
        }

        if ($this->jsPreset()) {
            $this->jsPreset()->export();
        }

        $this->exportWebpackMix();
    }

    public function getPreset($preset)
    {
        $preset = str_replace(' ', '', $preset);

        $presetClass = "\\Oasin\\Theme\\Presets\\{$preset}Preset";

        if (class_exists($presetClass)) {
            return new $presetClass($this);
        }
    }

    public function cssPreset()
    {
        return $this->getPreset($this->cssFramework);
    }

    /**
     * @return null|object
     */
    public function jsPreset()
    {
        return $this->getPreset($this->jsFramework);
    }

    public function exportWebpackMix(): void
    {
        copy(__DIR__ . '/../../stubs/Presets/webpack.mix.js', Theme::path('webpack.mix.js', $this->slug));

        $mix = '';

        if ($mixJs = $this->webpackJs()) {
            $mix .= "\n    " . $mixJs;
        }

        if ($mixCss = $this->webpackCss()) {
            $mix .= "\n    " . $mixCss;
        }

        if ($mix) {
            $mix = 'mix.setPublicPath("public/themes/' . $this->slug . '")' . $mix . ';';
        }

        $this->append(Theme::path('webpack.mix.js', $this->slug), $mix);
    }

    public function webpackJs()
    {
        if ($this->cssPreset() && method_exists($this->cssPreset(), 'webpackJs')) {
            return $this->cssPreset()->webpackJs();
        }

        if ($this->jsPreset() && method_exists($this->jsPreset(), 'webpackJs')) {
            return $this->jsPreset()->webpackJs();
        }
    }

    public function webpackCss()
    {
        if ($this->cssPreset() && method_exists($this->cssPreset(), 'webpackCss')) {
            return $this->cssPreset()->webpackCss();
        }
    }
}
