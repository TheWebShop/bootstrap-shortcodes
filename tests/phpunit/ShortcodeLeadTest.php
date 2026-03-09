<?php

class ShortcodeLeadTest extends WP_UnitTestCase {

    public function test_lead_default() {
        $content = '[bs_lead]Leading Content[/bs_lead]';
        $output = do_shortcode($content);
        $this->assertStringContainsString('<div class="lead">', $output);
        $this->assertStringContainsString('Leading Content', $output);
    }
}
