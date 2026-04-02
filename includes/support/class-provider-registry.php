<?php

namespace WPGPT\MCPBridge\Support;

use WPGPT\MCPBridge\Content_Provider;
use WPGPT\MCPBridge\Content_Write_Provider;
use WPGPT\MCPBridge\Database_Provider;
use WPGPT\MCPBridge\Diagnostics_Provider;
use WPGPT\MCPBridge\Repository_Provider;
use WPGPT\MCPBridge\Plugin_Management_Provider;
use WPGPT\MCPBridge\Structure_Provider;
use WPGPT\MCPBridge\Runtime_Provider;
use WPGPT\MCPBridge\System_Provider;
use WPGPT\MCPBridge\Utility_Provider;
use WPGPT\MCPBridge\SEO_Provider;
use WPGPT\MCPBridge\Publishing_Provider;
use WPGPT\MCPBridge\JetEngine_Provider;
use WPGPT\MCPBridge\Filesystem_Provider;
use WPGPT\MCPBridge\Media_Provider;
use WPGPT\MCPBridge\User_Provider;
use WPGPT\MCPBridge\Settings_Provider;
use WPGPT\MCPBridge\Navigation_Provider;
use WPGPT\MCPBridge\Inspection_Provider;

use WPGPT\MCPBridge\Comments_Provider;
use WPGPT\MCPBridge\ACF_Provider;
use WPGPT\MCPBridge\WooCommerce_Provider;
use WPGPT\MCPBridge\Appearance_Provider;
use WPGPT\MCPBridge\Block_Editor_Provider;
use WPGPT\MCPBridge\Maintenance_Provider;
use WPGPT\MCPBridge\Transfer_Provider;
use WPGPT\MCPBridge\Environment_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Provider_Registry {
    public static function definitions(): array {
        return array(
            'system'      => System_Provider::class,
            'content'     => Content_Provider::class,
            'content_write' => Content_Write_Provider::class,
            'database'    => Database_Provider::class,
            'utility'     => Utility_Provider::class,
            'diagnostics' => Diagnostics_Provider::class,
            'runtime'     => Runtime_Provider::class,
            'repository'  => Repository_Provider::class,
            'structure'   => Structure_Provider::class,
            'plugin_management' => Plugin_Management_Provider::class,
            'seo' => SEO_Provider::class,
            'publishing' => Publishing_Provider::class,
            'jetengine' => JetEngine_Provider::class,
            'filesystem' => Filesystem_Provider::class,
            'media'      => Media_Provider::class,
            'users'      => User_Provider::class,
            'settings'   => Settings_Provider::class,
            'navigation' => Navigation_Provider::class,
            'inspection' => Inspection_Provider::class,

            'comments'   => Comments_Provider::class,
            'acf'        => ACF_Provider::class,
            'woocommerce'=> WooCommerce_Provider::class,
            'appearance' => Appearance_Provider::class,
            'block_editor' => Block_Editor_Provider::class,
            'maintenance'=> Maintenance_Provider::class,
            'transfer'   => Transfer_Provider::class,
            'environment'=> Environment_Provider::class,
        );
    }
}
