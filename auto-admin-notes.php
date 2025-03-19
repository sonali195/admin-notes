<?php
/**
 * Plugin Name: Admin Notes Manager
 * Plugin URI:  https://wordpress.org/plugins/
 * Description: Automatically generates and stores admin notes based on site activity.
 * Version:     1.2
 * Author:      Sonali Prajapati
 * License:     GPL2
 * Text Domain: auto-admin-notes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access
}

class WP_Auto_Admin_Notes {
    private static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'load_admin_styles' ] );
        add_action( 'admin_post_export_admin_notes', [ $this, 'export_admin_notes_csv' ] );
        add_action( 'wp_ajax_add_admin_note', [ $this, 'add_admin_note' ] );
        add_action( 'wp_ajax_delete_admin_note', [ $this, 'delete_admin_note' ] );
        add_action( 'wp_ajax_update_admin_note', [ $this, 'update_admin_note' ] );
        add_action( 'admin_init', [ $this, 'initialize_default_notes' ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            esc_html__( 'Admin Notes', 'auto-admin-notes' ),
            esc_html__( 'Admin Notes', 'auto-admin-notes' ),
            'manage_options',
            'admin-notes-manager',
            [ $this, 'admin_page_content' ],
            'dashicons-welcome-write-blog',
            20
        );
    }

    public function load_admin_styles() {
        echo '<style>
            .admin-notes-container {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                max-width: 800px;
            }
            .admin-notes-list li {
                padding: 10px;
                margin-bottom: 8px;
                background: #f7f7f7;
                border-left: 5px solid #0073aa;
                display: flex;
                justify-content: space-between;
            }
            .admin-notes-export-btn, .admin-notes-add-btn {
                background: #0073aa;
                color: #fff;
                padding: 10px 15px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .admin-notes-export-btn:hover, .admin-notes-add-btn:hover {
                background: #005177;
            }
            .admin-notes-delete-btn {
                background: red;
                color: white;
                border: none;
                padding: 5px 10px;
                cursor: pointer;
            }
        </style>';
    }

    public function admin_page_content() {
        ?>
        <div class="wrap admin-notes-container">
            <h1><?php esc_html_e( 'WP Auto Admin Notes', 'auto-admin-notes' ); ?></h1>
            <input type="text" id="admin-note-input" placeholder="<?php esc_attr_e( 'Enter a new note', 'auto-admin-notes' ); ?>" style="width: 70%; padding: 5px;">
            <button class="admin-notes-add-btn" onclick="addAdminNote()"><?php esc_html_e( 'Add Note', 'auto-admin-notes' ); ?></button>
            <ul class="admin-notes-list" id="admin-notes-list">
                <?php
                $notes = get_option( 'wp_auto_admin_notes', [] );
                if ( empty( $notes ) ) {
                    echo '<li>' . esc_html__( 'No notes available.', 'auto-admin-notes' ) . '</li>';
                } else {
                    foreach ( $notes as $index => $note ) {
                        echo '<li>' . esc_html( $note ) . ' <button class="admin-notes-delete-btn" onclick="deleteAdminNote(' . esc_js( $index ) . ')">' . esc_html__( 'Delete', 'auto-admin-notes' ) . '</button></li>';
                    }
                }
                ?>
            </ul>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'export_admin_notes_action', 'export_admin_notes_nonce' ); ?>
                <input type="hidden" name="action" value="export_admin_notes">
                <button type="submit" class="admin-notes-export-btn"><?php esc_html_e( 'Export Notes as CSV', 'auto-admin-notes' ); ?></button>
            </form>
        </div>

        <script>
            function addAdminNote() {
                var note = document.getElementById('admin-note-input').value;
                if(note) {
                    var data = new FormData();
                    data.append('action', 'add_admin_note');
                    data.append('note', note);
                    data.append('add_admin_note_nonce', '<?php echo esc_js( wp_create_nonce( 'add_admin_note_action' ) ); ?>');
                    fetch(ajaxurl, { method: 'POST', body: data })
                        .then(response => response.text())
                        .then(() => location.reload());
                }
            }

            function deleteAdminNote(index) {
                var data = new FormData();
                data.append('action', 'delete_admin_note');
                data.append('index', index);
                data.append('delete_admin_note_nonce', '<?php echo esc_js( wp_create_nonce( 'delete_admin_note_action' ) ); ?>');
                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(response => response.text())
                    .then(() => location.reload());
            }
        </script>
        <?php
    }

    public function add_admin_note() {
        check_ajax_referer( 'add_admin_note_action', 'add_admin_note_nonce' );

        if ( isset( $_POST['note'] ) ) {
            $note = sanitize_text_field( wp_unslash( $_POST['note'] ) );
            $notes = get_option( 'wp_auto_admin_notes', [] );
            $notes[] = $note;
            update_option( 'wp_auto_admin_notes', $notes );
        }
        wp_die();
    }

    public function delete_admin_note() {
        check_ajax_referer( 'delete_admin_note_action', 'delete_admin_note_nonce' );

        $index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1;
        $notes = get_option('wp_auto_admin_notes', []);

        if ( $index >= 0 && isset( $notes[ $index ] ) ) {
            unset( $notes[ $index ] );
            update_option( 'wp_auto_admin_notes', array_values( $notes ) );
        }
        wp_die();
    }

    public function initialize_default_notes() {
        $notes = get_option( 'wp_auto_admin_notes', [] );
        if ( empty( $notes ) ) {
            $notes[] = esc_html__( 'Welcome to WP Auto Admin Notes!', 'auto-admin-notes' );
            $notes[] = esc_html__( 'This is a sample note. New notes will appear automatically.', 'auto-admin-notes' );
            update_option( 'wp_auto_admin_notes', $notes );
        }
    }
}

WP_Auto_Admin_Notes::get_instance();
