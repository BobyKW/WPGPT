<?php

namespace WPGPT\MCPBridge\Diagnostics;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Diagnostic_Operation {
    public function name(): string;

    public function label(): string;

    public function description(): string;

    public function input_schema(): array;

    public function execute( array $input ): array|WP_Error;
}
