<?php
/**
 * Classe responsável pela área administrativa do plugin.
 *
 * @package FLCR\Admin
 */

namespace FLCR\Admin;

// Previne o acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class FLCR_Admin
 */
class FLCR_Admin {

    /**
     * Registra os hooks do WordPress.
     */
    public function register_hooks() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_force_login_metabox' ] );
        add_action( 'save_post', [ $this, 'save_force_login_metabox' ] );
    }

    /**
     * Adiciona a página de configurações ao menu do WordPress.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Restrição de Acesso', 'flcr' ),
            __( 'Restrição de Acesso', 'flcr' ),
            'manage_options',
            'flcr-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Renderiza o HTML da página de configurações.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'flcr_settings_group' );
                do_settings_sections( 'flcr-settings' );
                submit_button( __( 'Salvar Alterações', 'flcr' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registra as seções e campos usando a Settings API.
     */
    public function register_settings() {
        register_setting( 'flcr_settings_group', 'flcr_settings', [ $this, 'sanitize_settings' ] );

        // Seção Geral
        add_settings_section(
            'flcr_general_section',
            __( 'Configurações Gerais', 'flcr' ),
            '__return_false',
            'flcr-settings'
        );

        add_settings_field(
            'login_page_id',
            __( 'Página de Login/Registro', 'flcr' ),
            [ $this, 'render_page_dropdown_field' ],
            'flcr-settings',
            'flcr_general_section',
            [
                'name'    => 'login_page_id',
                'label'   => __( 'Selecione a página para onde os usuários não logados serão redirecionados. Se nada for selecionado, a página "Minha Conta" do WooCommerce será usada como padrão.', 'flcr' ),
            ]
        );

        // Seção WooCommerce
        add_settings_section(
            'flcr_woocommerce_section',
            __( 'Configurações do WooCommerce', 'flcr' ),
            '__return_false',
            'flcr-settings'
        );

        add_settings_field(
            'force_checkout_login',
            __( 'Forçar Login no Checkout', 'flcr' ),
            [ $this, 'render_checkbox_field' ],
            'flcr-settings',
            'flcr_woocommerce_section',
            [
                'name'    => 'force_checkout_login',
                'label'   => __( 'Ativar para exigir que os usuários façam login antes de acessar a página de checkout.', 'flcr' ),
            ]
        );
        
        add_settings_field(
            'force_shop_login',
            __( 'Forçar Login na Loja', 'flcr' ),
            [ $this, 'render_checkbox_field' ],
            'flcr-settings',
            'flcr_woocommerce_section',
            [
                'name'    => 'force_shop_login',
                'label'   => __( 'Ativar para exigir login para visualizar a página principal da loja e arquivos de produtos.', 'flcr' ),
            ]
        );

        add_settings_field(
            'force_product_login',
            __( 'Forçar Login nos Produtos', 'flcr' ),
            [ $this, 'render_checkbox_field' ],
            'flcr-settings',
            'flcr_woocommerce_section',
            [
                'name'    => 'force_product_login',
                'label'   => __( 'Ativar para exigir login para visualizar páginas de produtos individuais.', 'flcr' ),
            ]
        );
    }
    
    /**
     * Renderiza um campo de dropdown com todas as páginas do site.
     *
     * @param array $args Argumentos para o campo.
     */
    public function render_page_dropdown_field( $args ) {
        $options = get_option( 'flcr_settings', [] );
        $name = esc_attr( $args['name'] );
        $selected_value = isset( $options[ $name ] ) ? (int) $options[ $name ] : 0;
        
        $pages = get_pages();
        ?>
        <select name="flcr_settings[<?php echo $name; ?>]" id="<?php echo $name; ?>">
            <option value="0"><?php esc_html_e( '— Padrão (Minha Conta do WooCommerce) —', 'flcr' ); ?></option>
            <?php foreach ( $pages as $page ) : ?>
                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $selected_value, $page->ID ); ?>>
                    <?php echo esc_html( $page->post_title ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php echo esc_html( $args['label'] ); ?></p>
        <?php
    }

    /**
     * Renderiza um campo de checkbox genérico.
     *
     * @param array $args Argumentos para o campo.
     */
    public function render_checkbox_field( $args ) {
        $options = get_option( 'flcr_settings', [] );
        $name = esc_attr( $args['name'] );
        $checked = isset( $options[ $name ] ) && 'yes' === $options[ $name ];
        ?>
        <label>
            <input type="checkbox" name="flcr_settings[<?php echo $name; ?>]" value="yes" <?php checked( $checked, true ); ?> />
            <?php echo esc_html( $args['label'] ); ?>
        </label>
        <?php
    }

    /**
     * Sanitiza os dados das configurações antes de salvar.
     *
     * @param array $input Dados do formulário.
     * @return array Dados sanitizados.
     */
    public function sanitize_settings( $input ) {
        $sanitized_input = [];
        $checkbox_fields = [ 'force_checkout_login', 'force_shop_login', 'force_product_login' ];
        
        // Sanitiza o campo de dropdown da página.
        if ( isset( $input['login_page_id'] ) ) {
            $sanitized_input['login_page_id'] = absint( $input['login_page_id'] );
        }

        // Sanitiza os campos de checkbox.
        foreach ( $checkbox_fields as $field ) {
            $sanitized_input[ $field ] = isset( $input[ $field ] ) && 'yes' === $input[ $field ] ? 'yes' : 'no';
        }

        return $sanitized_input;
    }

    /**
     * Adiciona o metabox para forçar o login.
     */
    public function add_force_login_metabox() {
        $post_types = [ 'page' ]; // Pode ser expandido para ['page', 'post'] etc.
        add_meta_box(
            'flcr_force_login_metabox',
            __( 'Restringir Acesso', 'flcr' ),
            [ $this, 'render_force_login_metabox' ],
            $post_types,
            'side',
            'default'
        );
    }

    /**
     * Renderiza o conteúdo do metabox.
     *
     * @param \WP_Post $post O objeto do post atual.
     */
    public function render_force_login_metabox( $post ) {
        wp_nonce_field( 'flcr_save_metabox_data', 'flcr_metabox_nonce' );
        
        $value = get_post_meta( $post->ID, '_flcr_force_login', true );
        ?>
        <label>
            <input type="checkbox" name="flcr_force_login" value="yes" <?php checked( $value, 'yes' ); ?> />
            <?php esc_html_e( 'Exigir login para ver esta página?', 'flcr' ); ?>
        </label>
        <?php
    }

    /**
     * Salva os dados do metabox.
     *
     * @param int $post_id O ID do post que está sendo salvo.
     */
    public function save_force_login_metabox( $post_id ) {
        if ( ! isset( $_POST['flcr_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['flcr_metabox_nonce'] ), 'flcr_save_metabox_data' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $new_value = isset( $_POST['flcr_force_login'] ) && 'yes' === $_POST['flcr_force_login'] ? 'yes' : 'no';
        update_post_meta( $post_id, '_flcr_force_login', $new_value );
    }
}