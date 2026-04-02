<?php

namespace WPGPT\MCPBridge\WooCommerce;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCommerce_Service {
    private function ensure_wc() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new WP_Error( 'wpgpt_woocommerce_unavailable', __( 'WooCommerce no está activo.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        return true;
    }

    public function resource_query( array $input ) {
        $check = $this->ensure_wc(); if ( is_wp_error( $check ) ) { return $check; }
        $resource = sanitize_key( (string) ( $input['resource'] ?? '' ) );
        return match ( $resource ) {
            'products'   => $this->query_products( $input ),
            'orders'     => $this->query_orders( $input ),
            'coupons'    => $this->query_posts_resource( 'shop_coupon', $input ),
            'customers'  => $this->query_customers( $input ),
            'categories' => $this->query_terms_resource( 'product_cat', $input ),
            'attributes' => $this->query_attributes(),
            default      => new WP_Error( 'wpgpt_wc_invalid_resource', __( 'Recurso WooCommerce no soportado.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
    }

    public function resource_get( array $input ) {
        $check = $this->ensure_wc(); if ( is_wp_error( $check ) ) { return $check; }
        $resource = sanitize_key( (string) ( $input['resource'] ?? '' ) );
        $id = absint( $input['id'] ?? 0 );
        return match ( $resource ) {
            'products' => $this->normalize_product( wc_get_product( $id ) ),
            'orders'   => $this->normalize_order_response( wc_get_order( $id ) ),
            'coupons'  => $this->normalize_coupon_response( new \WC_Coupon( $id ) ),
            'customers'=> $this->normalize_customer_response( get_user_by( 'id', $id ) ),
            default    => new WP_Error( 'wpgpt_wc_invalid_resource', __( 'Recurso WooCommerce no soportado.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
    }

    public function resource_upsert( array $input ) {
        $check = $this->ensure_wc(); if ( is_wp_error( $check ) ) { return $check; }
        $resource = sanitize_key( (string) ( $input['resource'] ?? '' ) );
        $id       = absint( $input['id'] ?? 0 );
        $data     = is_array( $input['data'] ?? null ) ? $input['data'] : array();
        return match ( $resource ) {
            'products' => $this->upsert_product( $id, $data ),
            'orders'   => $this->upsert_order( $id, $data ),
            'coupons'  => $this->upsert_coupon( $id, $data ),
            default    => new WP_Error( 'wpgpt_wc_invalid_resource', __( 'Este recurso WooCommerce no admite upsert en esta versión.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
    }

    public function order_action( array $input ) {
        $check = $this->ensure_wc(); if ( is_wp_error( $check ) ) { return $check; }
        $order = wc_get_order( absint( $input['order_id'] ?? 0 ) );
        if ( ! $order ) {
            return new WP_Error( 'wpgpt_wc_order_not_found', __( 'Pedido no encontrado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $params = is_array( $input['params'] ?? null ) ? $input['params'] : array();
        if ( 'set_status' === $action && ! empty( $params['status'] ) ) {
            $order->update_status( sanitize_key( (string) $params['status'] ) );
        } elseif ( 'add_note' === $action && ! empty( $params['note'] ) ) {
            $order->add_order_note( sanitize_textarea_field( (string) $params['note'] ) );
        } elseif ( 'refund' === $action ) {
            $refund = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => (float) ( $params['amount'] ?? 0 ), 'reason' => sanitize_text_field( (string) ( $params['reason'] ?? '' ) ) ) );
            if ( is_wp_error( $refund ) ) { return $refund; }
        } else {
            return new WP_Error( 'wpgpt_wc_order_action_invalid', __( 'Acción de pedido no válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        return $this->normalize_order_response( $order );
    }

    public function report_summary( array $input ) {
        $check = $this->ensure_wc(); if ( is_wp_error( $check ) ) { return $check; }
        $report = sanitize_key( (string) ( $input['report'] ?? 'sales' ) );
        return match ( $report ) {
            'low_stock' => array( 'success' => true, 'report' => 'low_stock', 'items' => wc_get_products( array( 'limit' => min( 100, max( 1, absint( $input['limit'] ?? 20 ) ) ), 'stock_status' => 'instock', 'low_stock_amount' => true, 'return' => 'ids' ) ) ),
            'top_products' => $this->top_products_report( $input ),
            default => $this->sales_orders_report( $report, $input ),
        };
    }

    private function query_products( array $input ): array {
        $page = max( 1, absint( $input['page'] ?? 1 ) ); $per_page = min( 100, max( 1, absint( $input['per_page'] ?? 20 ) ) );
        $query = wc_get_products( array( 'limit' => $per_page, 'page' => $page, 'paginate' => true, 'search' => ! empty( $input['search'] ) ? wc_clean( (string) $input['search'] ) : '', 'orderby' => sanitize_key( (string) ( $input['orderby'] ?? 'date' ) ), 'order' => 'ASC' === strtoupper( (string) ( $input['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC' ) );
        return array( 'success' => true, 'resource' => 'products', 'items' => array_map( array( $this, 'normalize_product' ), $query->products ?? array() ), 'total' => (int) ( $query->total ?? 0 ), 'page' => $page, 'per_page' => $per_page );
    }

    private function query_orders( array $input ): array {
        $page = max( 1, absint( $input['page'] ?? 1 ) ); $per_page = min( 100, max( 1, absint( $input['per_page'] ?? 20 ) ) );
        $orders = wc_get_orders( array( 'limit' => $per_page, 'paged' => $page, 'paginate' => true, 'status' => ! empty( $input['filters']['status'] ) ? array( sanitize_key( (string) $input['filters']['status'] ) ) : array_keys( wc_get_order_statuses() ) ) );
        return array( 'success' => true, 'resource' => 'orders', 'items' => array_map( array( $this, 'normalize_order' ), $orders->orders ?? array() ), 'total' => (int) ( $orders->total ?? 0 ), 'page' => $page, 'per_page' => $per_page );
    }

    private function query_posts_resource( string $post_type, array $input ): array {
        $page = max( 1, absint( $input['page'] ?? 1 ) ); $per_page = min( 100, max( 1, absint( $input['per_page'] ?? 20 ) ) );
        $q = new \WP_Query( array( 'post_type' => $post_type, 'post_status' => 'any', 's' => sanitize_text_field( (string) ( $input['search'] ?? '' ) ), 'posts_per_page' => $per_page, 'paged' => $page ) );
        return array( 'success' => true, 'resource' => $post_type, 'items' => array_map( fn($p)=>array('id'=>$p->ID,'title'=>$p->post_title,'status'=>$p->post_status), $q->posts ), 'total' => (int) $q->found_posts, 'page' => $page, 'per_page' => $per_page );
    }

    private function query_customers( array $input ): array {
        $users = get_users( array( 'role__in' => array( 'customer' ), 'search' => ! empty( $input['search'] ) ? '*' . esc_attr( (string) $input['search'] ) . '*' : '', 'number' => min( 100, max( 1, absint( $input['per_page'] ?? 20 ) ) ), 'paged' => max( 1, absint( $input['page'] ?? 1 ) ) ) );
        return array( 'success' => true, 'resource' => 'customers', 'items' => array_map( array( $this, 'normalize_customer' ), $users ), 'total' => count( $users ) );
    }

    private function query_terms_resource( string $taxonomy, array $input ): array {
        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => sanitize_text_field( (string) ( $input['search'] ?? '' ) ), 'number' => min( 100, max( 1, absint( $input['per_page'] ?? 20 ) ) ) ) );
        return array( 'success' => true, 'resource' => $taxonomy, 'items' => array_map( fn($t)=>array('id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug,'count'=>$t->count), is_array($terms)?$terms:array() ), 'total' => is_array($terms)?count($terms):0 );
    }

    private function query_attributes(): array {
        $attrs = wc_get_attribute_taxonomies();
        return array( 'success' => true, 'resource' => 'attributes', 'items' => array_map( fn($a)=>array('id'=>(int)$a->attribute_id,'name'=>$a->attribute_name,'label'=>$a->attribute_label,'type'=>$a->attribute_type), (array)$attrs ), 'total' => count((array)$attrs) );
    }

    private function upsert_product( int $id, array $data ) {
        $product = $id > 0 ? wc_get_product( $id ) : new \WC_Product_Simple();
        if ( ! $product ) { return new WP_Error( 'wpgpt_wc_product_not_found', __( 'Producto no encontrado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) ); }
        foreach ( array( 'name', 'slug', 'status', 'sku' ) as $prop ) {
            if ( isset( $data[ $prop ] ) ) { $setter = 'set_' . $prop; if ( method_exists( $product, $setter ) ) { $product->{$setter}( is_string($data[$prop]) ? wc_clean( $data[$prop] ) : $data[$prop] ); } }
        }
        if ( isset( $data['regular_price'] ) ) { $product->set_regular_price( wc_format_decimal( $data['regular_price'] ) ); }
        if ( isset( $data['description'] ) ) { $product->set_description( wp_kses_post( (string) $data['description'] ) ); }
        if ( isset( $data['short_description'] ) ) { $product->set_short_description( wp_kses_post( (string) $data['short_description'] ) ); }
        $product->save();
        return array( 'success' => true, 'item' => $this->normalize_product( $product ) );
    }

    private function upsert_order( int $id, array $data ) {
        $order = $id > 0 ? wc_get_order( $id ) : wc_create_order();
        if ( is_wp_error( $order ) || ! $order ) { return new WP_Error( 'wpgpt_wc_order_create_failed', __( 'No se pudo crear o cargar el pedido.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) ); }
        if ( ! empty( $data['status'] ) ) { $order->set_status( sanitize_key( (string) $data['status'] ) ); }
        if ( ! empty( $data['billing_email'] ) ) { $order->set_billing_email( sanitize_email( (string) $data['billing_email'] ) ); }
        $order->save();
        return $this->normalize_order_response( $order );
    }

    private function upsert_coupon( int $id, array $data ) {
        $coupon = $id > 0 ? new \WC_Coupon( $id ) : new \WC_Coupon();
        if ( ! empty( $data['code'] ) ) { $coupon->set_code( wc_clean( (string) $data['code'] ) ); }
        if ( isset( $data['amount'] ) ) { $coupon->set_amount( wc_format_decimal( $data['amount'] ) ); }
        if ( ! empty( $data['discount_type'] ) ) { $coupon->set_discount_type( wc_clean( (string) $data['discount_type'] ) ); }
        $coupon->save();
        return $this->normalize_coupon_response( $coupon );
    }

    private function sales_orders_report( string $report, array $input ): array {
        $statuses = array_keys( wc_get_order_statuses() );
        $orders = wc_get_orders( array( 'limit' => -1, 'status' => $statuses, 'date_created' => $this->build_wc_date_range( $input ) ) );
        $gross = 0.0; $count = 0;
        foreach ( $orders as $order ) { $gross += (float) $order->get_total(); $count++; }
        return array( 'success' => true, 'report' => $report, 'summary' => array( 'order_count' => $count, 'gross_total' => $gross, 'average_order' => $count ? $gross / $count : 0 ) );
    }

    private function top_products_report( array $input ): array {
        global $wpdb;
        $limit = min( 50, max( 1, absint( $input['limit'] ?? 10 ) ) );
        $results = $wpdb->get_results( $wpdb->prepare("SELECT order_item_name as product_name, SUM(meta_qty.meta_value+0) as qty
            FROM {$wpdb->prefix}woocommerce_order_items items
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta meta_qty ON items.order_item_id = meta_qty.order_item_id AND meta_qty.meta_key = '_qty'
            WHERE items.order_item_type = 'line_item'
            GROUP BY order_item_name
            ORDER BY qty DESC
            LIMIT %d", $limit), ARRAY_A );
        return array( 'success' => true, 'report' => 'top_products', 'items' => $results );
    }

    private function build_wc_date_range( array $input ): string {
        $from = ! empty( $input['date_from'] ) ? sanitize_text_field( (string) $input['date_from'] ) : '';
        $to   = ! empty( $input['date_to'] ) ? sanitize_text_field( (string) $input['date_to'] ) : '';
        if ( $from && $to ) { return $from . '...' . $to; }
        if ( $from ) { return '>=' . $from; }
        if ( $to ) { return '<=' . $to; }
        return '';
    }

    private function normalize_product( $product ) {
        if ( ! $product ) { return new WP_Error( 'wpgpt_wc_product_not_found', __( 'Producto no encontrado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) ); }
        return array( 'success' => true, 'item' => array( 'id' => $product->get_id(), 'name' => $product->get_name(), 'slug' => $product->get_slug(), 'status' => $product->get_status(), 'sku' => $product->get_sku(), 'price' => $product->get_price(), 'type' => $product->get_type() ) );
    }
    private function normalize_order_response( $order ) {
        if ( ! $order ) { return new WP_Error( 'wpgpt_wc_order_not_found', __( 'Pedido no encontrado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) ); }
        return array( 'success' => true, 'item' => $this->normalize_order( $order ) );
    }
    private function normalize_coupon_response( $coupon ) {
        if ( ! $coupon || ! $coupon->get_id() ) { return new WP_Error( 'wpgpt_wc_coupon_not_found', __( 'Cupón no encontrado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) ); }
        return array( 'success' => true, 'item' => array( 'id' => $coupon->get_id(), 'code' => $coupon->get_code(), 'amount' => $coupon->get_amount(), 'discount_type' => $coupon->get_discount_type() ) );
    }
    private function normalize_customer_response( $user ) {
        if ( ! $user ) { return new WP_Error( 'wpgpt_wc_customer_not_found', __( 'Cliente no encontrado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) ); }
        return array( 'success' => true, 'item' => $this->normalize_customer( $user ) );
    }
    private function normalize_order( \WC_Order $order ): array { return array( 'id' => $order->get_id(), 'status' => $order->get_status(), 'currency' => $order->get_currency(), 'total' => (float) $order->get_total(), 'customer_id' => (int) $order->get_customer_id(), 'billing_email' => (string) $order->get_billing_email(), 'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '' ); }
    private function normalize_customer( \WP_User $user ): array { return array( 'id' => $user->ID, 'login' => $user->user_login, 'email' => $user->user_email, 'display_name' => $user->display_name, 'roles' => array_values( (array) $user->roles ) ); }
}
