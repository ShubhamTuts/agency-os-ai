<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Plugin {
    use AOSAI_Singleton;

    private AOSAI_Loader $loader;

    protected function __construct() {
        $this->loader = new AOSAI_Loader();
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_frontend_hooks();
        $this->define_api_hooks();
        $this->define_notification_hooks();
    }

    private function load_dependencies() {
        $models = array( 'project', 'task', 'task-list', 'milestone', 'message', 'comment', 'file', 'activity', 'setting', 'user', 'department', 'ticket', 'tag' );
        foreach ( $models as $model ) {
            require_once AOSAI_PLUGIN_DIR . "includes/models/class-aosai-{$model}.php";
        }

        require_once AOSAI_PLUGIN_DIR . 'includes/services/interface-aosai-ai-provider.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/services/class-aosai-ai-openai.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/services/class-aosai-ai-service.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/services/class-aosai-notification-service.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/services/class-aosai-email-service.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/services/class-aosai-permission-service.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/services/class-aosai-file-service.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/services/class-aosai-portal-service.php';

        require_once AOSAI_PLUGIN_DIR . 'includes/admin/class-aosai-admin.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/admin/class-aosai-admin-notices.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/admin/class-aosai-settings.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/frontend/class-aosai-frontend.php';

        $controllers = array(
            'projects', 'tasks', 'task-lists', 'milestones', 'messages',
            'comments', 'files', 'activities', 'ai', 'settings', 'users', 'reports', 'profile',
            'portal', 'tickets', 'departments', 'tags',
        );
        foreach ( $controllers as $ctrl ) {
            require_once AOSAI_PLUGIN_DIR . "includes/api/class-aosai-rest-{$ctrl}.php";
        }
    }

    private function set_locale() {
        $i18n = new AOSAI_i18n();
        $this->loader->add_action( 'init', $i18n, 'load_plugin_textdomain' );
    }

    private function define_admin_hooks() {
        $admin = new AOSAI_Admin();
        $this->loader->add_action( 'admin_menu', $admin, 'register_menu' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );
        $this->loader->add_filter( 'plugin_action_links_' . AOSAI_PLUGIN_BASENAME, $admin, 'add_action_links' );
    }

    private function define_frontend_hooks() {
        $frontend = AOSAI_Frontend::get_instance();
        $this->loader->add_action( 'init', $frontend, 'register_shortcodes' );
        $this->loader->add_action( 'init', $frontend, 'maybe_serve_virtual_assets' );
        $this->loader->add_action( 'admin_init', $frontend, 'maybe_redirect_portal_users' );
        $this->loader->add_action( 'wp_head', $frontend, 'print_pwa_meta' );
        $this->loader->add_filter( 'show_admin_bar', $frontend, 'maybe_hide_admin_bar' );
    }

    private function define_api_hooks() {
        $this->loader->add_action( 'rest_api_init', $this, 'register_rest_routes' );
    }

    public function register_rest_routes() {
        $controllers = array(
            new AOSAI_REST_Projects(),
            new AOSAI_REST_Tasks(),
            new AOSAI_REST_Task_Lists(),
            new AOSAI_REST_Milestones(),
            new AOSAI_REST_Messages(),
            new AOSAI_REST_Comments(),
            new AOSAI_REST_Files(),
            new AOSAI_REST_Activities(),
            new AOSAI_REST_AI(),
            new AOSAI_REST_Settings(),
            new AOSAI_REST_Users(),
            new AOSAI_REST_Reports(),
            new AOSAI_REST_Profile(),
            new AOSAI_REST_Portal(),
            new AOSAI_REST_Tickets(),
            new AOSAI_REST_Departments(),
            new AOSAI_REST_Tags(),
        );

        foreach ( $controllers as $controller ) {
            $controller->register_routes();
        }

        do_action( 'aosai_register_pro_routes' );
    }

    private function define_notification_hooks() {
        $notifications = AOSAI_Notification_Service::get_instance();
        $this->loader->add_action( 'aosai_task_assigned', $notifications, 'on_task_assigned', 10, 2 );
        $this->loader->add_action( 'aosai_task_completed', $notifications, 'on_task_completed', 10, 2 );
        $this->loader->add_action( 'aosai_comment_created', $notifications, 'on_comment_created', 10, 3 );
        $this->loader->add_action( 'aosai_milestone_completed', $notifications, 'on_milestone_completed', 10, 2 );
        $this->loader->add_action( 'aosai_message_created', $notifications, 'on_message_created', 10, 2 );
    }

    public function run() {
        $this->loader->run();
    }
}

