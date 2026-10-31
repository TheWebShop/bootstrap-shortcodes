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

    public function test_well_small() {
        $content = '[bs_well size="sm"]Small Content[/bs_well]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="well well-sm">', $output);
        $this->assertStringContainsString('Small Content', $output);
    }

    public function test_well_invalid_size_falls_back_to_default() {
        $content = '[bs_well size="xl"]Odd Content[/bs_well]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="well">', $output);
        $this->assertStringNotContainsString('well-xl', $output);
        $this->assertStringContainsString('Odd Content', $output);
    }

    public function test_well_empty_size_falls_back_to_default() {
        $content = '[bs_well size=""]Plain Content[/bs_well]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="well">', $output);
        $this->assertStringContainsString('Plain Content', $output);
    }
}
