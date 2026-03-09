<?php

class ShortcodeIconsTest extends WP_UnitTestCase {

    public function test_icon_default() {
        $content = '[bs_icon]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<i class="default"></i>', $output);
    }
    
    public function test_icon_named() {
        $content = '[bs_icon name="glyphicon glyphicon-star"]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<i class="glyphicon glyphicon-star"></i>', $output);
    }
}
