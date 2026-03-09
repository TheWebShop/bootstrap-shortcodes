<?php

class ShortcodeWellsTest extends WP_UnitTestCase {

    public function test_well_default() {
        $content = '[bs_well]Inner Content[/bs_well]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="well">', $output);
        $this->assertStringContainsString('Inner Content', $output);
    }
    
    public function test_well_large() {
        $content = '[bs_well size="lg"]Large Content[/bs_well]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="well well-lg">', $output);
        $this->assertStringContainsString('Large Content', $output);
    }
}
