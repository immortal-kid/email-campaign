<?php
namespace EC;

defined( 'ABSPATH' ) || exit;

class Helper_Functions {
    public static function init() {}

    /**
     * Simple token replacement {FIRST_NAME}
     */
    public static function replace_tokens( $content, $data = [] ) {
        if ( isset( $data['name'] ) && ! empty( $data['name'] ) ) {
            $content = str_replace( '{FIRST_NAME}', $data['name'], $content );
        }
        return $content;
    }

    public static function unsubscribe_url( $email, $hash ) {
        return add_query_arg(
            [
                'email' => rawurlencode( $email ),
                'token' => $hash,
            ],
            site_url( '/unsubscribe' )
        );
    }
}
