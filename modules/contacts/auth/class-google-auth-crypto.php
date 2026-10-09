<?php
/**
 * Helper criptográfico para almacenamiento seguro de credenciales y tokens de Google.
 *
 * Utiliza AES-256-CBC con clave derivada de wp_salt('auth') y vectores de inicialización (IV) criptográficamente seguros.
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts\Auth
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Google_Auth_Crypto {

    /**
     * Cifra un texto plano para almacenamiento seguro en la base de datos (wp_options).
     *
     * @param string $plaintext
     * @return string Cadena codificada en base64 con IV + texto cifrado.
     */
    public static function encrypt( string $plaintext ): string {
        if ( empty( $plaintext ) ) {
            return '';
        }

        if ( ! function_exists( 'openssl_encrypt' ) ) {
            // Fallback si openssl no estuviera compilado
            return 'b64:' . base64_encode( $plaintext );
        }

        $salt = defined( 'AUTH_SALT' ) && AUTH_SALT ? wp_salt( 'auth' ) : 'aura_fallback_secret_key_contacts';
        $key  = hash( 'sha256', $salt, true );
        $iv   = openssl_random_pseudo_bytes( 16 );

        $ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $ciphertext ) {
            return '';
        }

        return 'enc:' . base64_encode( $iv . $ciphertext );
    }

    /**
     * Descifra un texto previamente cifrado.
     *
     * @param string $encrypted
     * @return string Texto original en plano o cadena vacía en caso de fallo.
     */
    public static function decrypt( string $encrypted ): string {
        if ( empty( $encrypted ) ) {
            return '';
        }

        if ( 0 === strpos( $encrypted, 'b64:' ) ) {
            return (string) base64_decode( substr( $encrypted, 4 ) );
        }

        if ( 0 !== strpos( $encrypted, 'enc:' ) ) {
            // Podría ser texto plano no migrado
            return $encrypted;
        }

        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return '';
        }

        $raw = base64_decode( substr( $encrypted, 4 ) );
        if ( strlen( $raw ) < 17 ) {
            return '';
        }

        $iv         = substr( $raw, 0, 16 );
        $ciphertext = substr( $raw, 16 );
        $salt       = defined( 'AUTH_SALT' ) && AUTH_SALT ? wp_salt( 'auth' ) : 'aura_fallback_secret_key_contacts';
        $key        = hash( 'sha256', $salt, true );

        $decrypted = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

        return false === $decrypted ? '' : (string) $decrypted;
    }
}
