<?php
if ( !defined( 'ABSPATH' ) ){
    exit;
} // Exit if accessed directly.

class Africa_DMM_Tile {
    private static $_instance = null;

    public static function instance(){
        if ( is_null( self::$_instance ) ){
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct(){
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields' ], 1, 2 );
    }

    /**
     * This function registers a new tile to a specific post type
     *
     * @param array $tiles
     * @param string $post_type
     * @return array
     */
    public function dt_details_additional_tiles( array $tiles, string $post_type = '' ){
        if ( $post_type === 'groups' ){
            $group_preferences = dt_get_option( 'group_preferences' );
            if ( empty( $group_preferences['four_fields'] ) ) {
                $group_preferences['four_fields'] = 1;
                update_option( 'group_preferences', $group_preferences );
            }
        }
        return $tiles;
    }

    /**
     * @param array $fields
     * @param string $post_type
     * @return array
     */
    public function dt_custom_fields( array $fields, string $post_type = '' ){
        if ( $post_type === 'groups' ){
            $fields['dmm_loader'] = [
                'name' => __( 'Group Leader', 'disciple-tools-plugin-starter-template' ),
                'type' => 'text',
                'default' => '',
                'tile' => 'four-fields',
            ];
            $fields['dmm_coach'] = [
                'name' => __( 'Group Coach', 'disciple-tools-plugin-starter-template' ),
                'type' => 'text',
                'default' => '',
                'tile' => 'four-fields',
            ];
        }
        return $fields;
    }
}

Africa_DMM_Tile::instance();
