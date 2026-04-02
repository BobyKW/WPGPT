<?php

namespace WPGPT\MCPBridge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Ability_Provider {
    public function get_abilities(): array;

    public function register(): void;
}
