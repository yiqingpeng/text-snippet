<?php
namespace Revhub\Snippet;

trait TextCleaner{
    
    /*
     * https://developer.wordpress.org/reference/functions/wp_strip_all_tags/
     */
    public static function stripAllTags( $text, $removeBreaks = false ) {
        $text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
        $text = strip_tags( $text );
        if ( $removeBreaks ) {
            $text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
        }
        return trim( $text );
    }
    
    public static function stripLineBreaks( $text, $replaced = '' ) {
        return trim( preg_replace("/\r\n/", $replaced, $text) );
    }
}