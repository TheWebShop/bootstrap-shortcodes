<?php

class ShortcodeLabelsTest extends WP_UnitTestCase {

    public function test_label_default() {
        $content = '[bs_label]Label Text[/bs_label]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<span class="label label-default">Label Text</span>', $output);
    }
    
    public function test_label_success() {
        $content = '[bs_label type="success"]Success Text[/bs_label]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<span class="label label-success">Success Text</span>', $output);
    }
}
