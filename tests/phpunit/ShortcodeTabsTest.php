<?php

class ShortcodeTabsTest extends WP_UnitTestCase {

    public function test_tabs_wrapper() {
        $content = '[bs_tabs]Content[/bs_tabs]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="tab_wrap">', $output);
        $this->assertStringContainsString('Content', $output);
    }

    public function test_thead_wrapper() {
        $content = '[bs_thead]Headers[/bs_thead]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<ul class="nav nav-tabs">', $output);
        $this->assertStringContainsString('Headers', $output);
    }

    public function test_tab_item() {
        $content = '[bs_tab href="#home" title="Home" class="active"]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<li class="active">', $output);
        $this->assertStringContainsString('<a data-toggle="tab" href="#home">Home</a>', $output);
    }

    public function test_dropdown_item() {
        $content = '[bs_dropdown title="Options" id="opts" class="my-drop"][bs_tab href="#opt1" title="Opt 1"][/bs_dropdown]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<li class="dropdown">', $output);
        $this->assertStringContainsString('class="my-drop dropdown-toggle"', $output);
        $this->assertStringContainsString('id="opts"', $output);
        $this->assertStringContainsString('data-toggle="dropdown"', $output);
        $this->assertStringContainsString('>Options<b class="caret"></b></a>', $output);
        $this->assertStringContainsString('<ul class="dropdown-menu">', $output);
        $this->assertStringContainsString('<a data-toggle="tab" href="#opt1">Opt 1</a>', $output);
    }

    public function test_tcontents_wrapper() {
        $content = '[bs_tcontents]Panes[/bs_tcontents]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="tab-content">', $output);
        $this->assertStringContainsString('Panes', $output);
    }

    public function test_tcontent_item() {
        $content = '[bs_tcontent id="home" class="active"]Content[/bs_tcontent]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="tab-pane fade active in" id=home>', $output);
        $this->assertStringContainsString('Content', $output);
    }
}
