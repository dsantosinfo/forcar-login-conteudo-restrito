<?php
/**
 * Classe principal do plugin, responsável pela lógica de frontend.
 *
 * @package FLCR\Includes
 */

namespace FLCR\Includes;

// Previne o acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class FLCR_Core
 */
class FLCR_Core {

    /**
     * Armazena as opções do plugin.
     *
     * @var array
     */
    private $options;

    /**
     * FLCR_Core constructor.
     */
    public function __construct() {
        $this->options = get_option( 'flcr_settings', [] );
    }

    /**
     * Registra os hooks do WordPress.
     */
    public function register_hooks() {
        // Hook de alta prioridade para redirecionar o acesso a wp-login.php e /wp-admin/
        add_action( 'wp_loaded', [ $this, 'safe_redirect_login_and_admin' ] );

        // Hooks para forçar o login em conteúdo restrito
        add_action( 'template_redirect', [ $this, 'check_for_forced_login' ] );
        add_filter( 'login_url', [ $this, 'filter_login_url' ], 10, 3 );
    }

    /**
     * Redireciona à força qualquer acesso a wp-login.php ou /wp-admin/
     * para a página de login customizada. Versão segura e aprimorada.
     */
    public function safe_redirect_login_and_admin() {
        // 1. Só executa a lógica se o usuário NÃO estiver logado.
        if ( is_user_logged_in() ) {
            return;
        }

        $current_url = $_SERVER['REQUEST_URI'];
        
        // Verifica se a página atual é wp-login.php OU se está dentro de /wp-admin/
        $is_login_page = ( strpos( $current_url, '/wp-login.php' ) !== false );
        $is_admin_page = ( strpos( $current_url, '/wp-admin' ) !== false );

        if ( $is_login_page || $is_admin_page ) {

            // 2. Permite que as chamadas AJAX do WordPress (essenciais para temas/plugins) funcionem.
            if ( strpos( $current_url, '/admin-ajax.php' ) !== false ) {
                return;
            }

            // 3. Permite que ações essenciais (logout, reset de senha) funcionem.
            $action = $_REQUEST['action'] ?? 'login';
            if ( in_array( $action, [ 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass' ], true ) ) {
                return;
            }
            
            // Determina a URL de login correta a partir das opções do plugin.
            $login_page_id = isset( $this->options['login_page_id'] ) ? (int) $this->options['login_page_id'] : 0;
            $redirect_url  = '';

            if ( $login_page_id > 0 && 'publish' === get_post_status( $login_page_id ) ) {
                $redirect_url = get_permalink( $login_page_id );
            }

            if ( empty( $redirect_url ) && function_exists( 'wc_get_page_permalink' ) ) {
                $redirect_url = wc_get_page_permalink( 'myaccount' );
            }

            // Se temos uma URL de destino, executa o redirecionamento.
            if ( ! empty( $redirect_url ) ) {
                // Preserva o parâmetro 'redirect_to' para retornar o usuário à página original.
                if ( isset( $_REQUEST['redirect_to'] ) ) {
                    $redirect_url = add_query_arg( 'redirect_to', urlencode( wp_unslash( $_REQUEST['redirect_to'] ) ), $redirect_url );
                }

                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
    }

    /**
     * Filtra a URL de login padrão do WordPress para usar a página definida nas configurações.
     */
    public function filter_login_url( $login_url, $redirect, $force_reauth ) {
        $login_page_id = isset( $this->options['login_page_id'] ) ? (int) $this->options['login_page_id'] : 0;

        if ( ! $login_page_id && function_exists( 'wc_get_page_permalink' ) ) {
            $login_page_id = (int) get_option( 'woocommerce_myaccount_page_id' );
        }

        if ( ! $login_page_id || 'publish' !== get_post_status( $login_page_id ) || is_page( $login_page_id ) ) {
            return $login_url;
        }

        $custom_login_url = get_permalink( $login_page_id );

        if ( ! empty( $redirect ) ) {
            $custom_login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $custom_login_url );
        }

        return $custom_login_url;
    }

    /**
     * Verifica se o login é necessário em páginas específicas e redireciona.
     */
    public function check_for_forced_login() {
        if ( is_user_logged_in() ) {
            return;
        }
        
        $login_page_id     = isset( $this->options['login_page_id'] ) ? (int) $this->options['login_page_id'] : 0;
        $myaccount_page_id = (int) get_option( 'woocommerce_myaccount_page_id' );

        if ( ( $login_page_id > 0 && is_page( $login_page_id ) ) || ( ! $login_page_id && is_account_page() ) ) {
            return;
        }
        
        $is_restricted = false;
        
        if ( $login_page_id > 0 && $login_page_id !== $myaccount_page_id && is_account_page() ) {
            $is_restricted = true;
        }
        
        $current_post_id = get_queried_object_id();

        if ( ! $is_restricted && is_singular() && 'yes' === get_post_meta( $current_post_id, '_flcr_force_login', true ) ) {
            $is_restricted = true;
        }

        if ( ! $is_restricted && function_exists( 'is_woocommerce' ) ) {
            if ( 'yes' === ( $this->options['force_checkout_login'] ?? 'no' ) && is_checkout() ) {
                $is_restricted = true;
            }
            if ( ! $is_restricted && 'yes' === ( $this->options['force_shop_login'] ?? 'no' ) && ( is_shop() || is_product_taxonomy() ) ) {
                $is_restricted = true;
            }
            if ( ! $is_restricted && 'yes' === ( $this->options['force_product_login'] ?? 'no' ) && is_product() ) {
                $is_restricted = true;
            }
        }

        if ( $is_restricted ) {
            $this->redirect_to_login_page();
        }
    }

    /**
     * Executa o redirecionamento para a página de login/registro.
     */
    private function redirect_to_login_page() {
        $login_page_id = isset( $this->options['login_page_id'] ) ? (int) $this->options['login_page_id'] : 0;
        $login_url     = '';

        if ( $login_page_id > 0 && 'publish' === get_post_status( $login_page_id ) ) {
            $login_url = get_permalink( $login_page_id );
        }

        if ( empty( $login_url ) ) {
            $login_url = wc_get_page_permalink( 'myaccount' );
        }

        $redirect_url = esc_url_raw( add_query_arg( 'redirect_to', urlencode( $this->get_current_url() ), $login_url ) );
        
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Obtém a URL completa da página atual.
     */
    private function get_current_url() {
        global $wp;
        return home_url( add_query_arg( [], $wp->request ) );
    }
}