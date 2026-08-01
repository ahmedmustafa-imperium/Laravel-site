<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StylesheetOrderTest extends TestCase
{
    public function test_responsive_styles_follow_base_styles_in_one_vite_entry(): void
    {
        $root = dirname(__DIR__, 2);
        $stylesheet = file_get_contents($root.'/resources/css/site.css');
        $catalogLayout = file_get_contents($root.'/resources/css/catalog-layout.css');
        $javascript = file_get_contents($root.'/resources/js/app.js');
        $viteConfig = file_get_contents($root.'/vite.config.js');

        $basePosition = strpos($stylesheet, "@import './app.css';");
        $responsivePosition = strpos($stylesheet, "@import './responsive.css';");
        $catalogPosition = strpos($stylesheet, "@import './catalog-layout.css';");

        $this->assertNotFalse($basePosition);
        $this->assertNotFalse($responsivePosition);
        $this->assertNotFalse($catalogPosition);
        $this->assertLessThan($responsivePosition, $basePosition);
        $this->assertLessThan($catalogPosition, $responsivePosition);
        $this->assertStringNotContainsString('responsive.css', $javascript);
        $this->assertStringContainsString("input: ['resources/css/site.css', 'resources/js/app.js']", $viteConfig);
        $this->assertStringContainsString('.filter-mobile-trigger.button', $catalogLayout);
        $this->assertStringContainsString('@media (min-width: 768px)', $catalogLayout);
        $this->assertStringContainsString('@media (max-width: 767px)', $catalogLayout);

        foreach (['storefront.blade.php', 'admin.blade.php'] as $layout) {
            $view = file_get_contents($root.'/resources/views/layouts/'.$layout);
            $this->assertStringContainsString('resources/css/site.css', $view);
            $this->assertStringNotContainsString('resources/css/app.css', $view);
        }
    }
}
