<?php

class PluginTest extends TestCase
{
    public function test_plugin_installed() {
        activate_plugin( 'africa-dmm/africa-dmm.php' );

        $this->assertContains(
            'africa-dmm/africa-dmm.php',
            get_option( 'active_plugins' )
        );
    }
}
