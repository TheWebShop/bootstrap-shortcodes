<?php

class ShortcodeTooltipTest extends WP_UnitTestCase {

    public function test_tooltip_default() {
        $content = '[bs_tooltip]Hover Me[/bs_tooltip]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<a href="', $output);
        $this->assertStringContainsString('data-toggle="tooltip"', $output);
        $this->assertStringContainsString('title="Hover Me"', $output);
        $this->assertStringContainsString('data-placement="top"', $output);
        $this->assertStringContainsString('data-trigger="hover"', $output);
        $this->assertStringContainsString('>Hover Me</a>', $output);
    }
    
    public function test_tooltip_attributes() {
        $content = '[bs_tooltip placement="bottom" trigger="focus"]Focus Me[/bs_tooltip]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('data-placement="bottom"', $output);
        $this->assertStringContainsString('data-trigger="focus"', $output);
    }
}
