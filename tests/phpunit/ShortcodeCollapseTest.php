<?php

class ShortcodeCollapseTest extends WP_UnitTestCase {

    public function test_collapse_wrapper() {
        $content = '[bs_collapse id="collapse-group"]Content[/bs_collapse]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="panel-group" id="collapse-group">', $output);
        $this->assertStringContainsString('Content', $output);
    }

    public function test_collapse_item() {
        $content = '[bs_citem id="c1" title="Item 1" parent="collapse-group"]Inner Content[/bs_citem]';
        $output = do_shortcode($content);
        
        $this->assertStringContainsString('<div class="panel panel-default">', $output);
        $this->assertStringContainsString('<div class="panel-heading" role="tab" id="heading_c1">', $output);
        $this->assertStringContainsString('<a class="accordion-toggle collapsed" data-toggle="collapse" aria-controls="heading_c1" data-parent="#collapse-group" href="#c1">Item 1</a>', $output);
        $this->assertStringContainsString('<div id="c1" class="panel-collapse collapse " role="tabpanel" aria-labelledby="heading_c1">', $output);
        $this->assertStringContainsString('<div class="panel-body">Inner Content        </div>', $output);
    }

    public function test_collapse_item_open() {
        $content = '[bs_citem id="c2" title="Item 2" parent="collapse-group" open="true"]Inner Content[/bs_citem]';
        $output = do_shortcode($content);
        
        $this->assertStringContainsString('<div id="c2" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="heading_c2">', $output);
    }
}
