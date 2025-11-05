<?php
/**
 * Plugin Name:         Forçar Login para Conteúdo Restrito
 * Plugin URI:          https://dsantosinfo.com.br/
 * Description:         Força usuários a fazerem login ou se registrarem para acessar páginas específicas e o checkout do WooCommerce.
 * Version:             1.1.0
 * Author:              DSantos Info
 * Author URI:          https://dsantosinfo.com.br/
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         flcr
 * Domain Path:         /languages
 * Requires PHP:        7.4
 * Requires at least:   5.0
 * Tested up to:        6.4
 */

// Previne o acesso direto ao arquivo.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define as constantes do plugin.
define( 'FLCR_VERSION', '1.1.0' );
define( 'FLCR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Verifica se o WooCommerce está ativo. Essencial para carregar as funcionalidades dependentes.
 *
 * @return bool
 */
function flcr_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Adiciona um aviso no painel de administração se o WooCommerce não estiver ativo.
 */
function flcr_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <?php
            echo wp_kses_post(
                __( 'O plugin "Forçar Login para Conteúdo Restrito" requer que o WooCommerce esteja instalado e ativo para funcionar corretamente. Por favor, instale ou ative o WooCommerce.', 'flcr' )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Carrega os arquivos principais do plugin e inicializa as classes.
 */
function flcr_run_plugin() {
    // Carrega as classes principais.
    require_once FLCR_PLUGIN_PATH . 'includes/class-flcr-core.php';
    require_once FLCR_PLUGIN_PATH . 'admin/class-flcr-admin.php';

    // Instancia as classes.
    $core = new \FLCR\Includes\FLCR_Core();
    $core->register_hooks();

    $admin = new \FLCR\Admin\FLCR_Admin();
    $admin->register_hooks();
}

/**
 * Função principal que verifica as dependências e inicializa o plugin.
 */
function flcr_init() {
    if ( ! flcr_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'flcr_woocommerce_missing_notice' );
        return; // Interrompe a execução se o WooCommerce não estiver ativo.
    }
    flcr_run_plugin();
}
add_action( 'plugins_loaded', 'flcr_init' );

/**
 * Define as opções padrão na ativação do plugin.
 */
function flcr_activate() {
    // Define um array de opções padrão.
    $default_options = [
        'login_page_id'          => 0, // 0 significa que usará o padrão do WooCommerce.
        'force_checkout_login'   => 'no',
        'force_shop_login'       => 'no',
        'force_product_login'    => 'no',
    ];

    // Adiciona a opção ao banco de dados apenas se ela não existir.
    add_option( 'flcr_settings', $default_options, '', 'no' );
}
register_activation_hook( __FILE__, 'flcr_activate' );

/**
 * Limpa as opções na desativação do plugin (opcional).
 */
function flcr_deactivate() {
    // Ação a ser executada na desativação, se necessário.
}
register_deactivation_hook( __FILE__, 'flcr_deactivate' );