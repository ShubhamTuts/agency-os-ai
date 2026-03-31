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
        $this->define_smtp_hooks();
        $this->define_security_hooks();
    }

    private function load_dependencies() {
        $models = array(
            'project', 'task', 'task-list', 'milestone', 'message', 'comment', 'file',
            'activity', 'setting', 'user', 'department', 'ticket', 'tag', 'login-activity',
            'client', 'invoice', 'workflow-stage', 'email-template', 'time-entry'
        );
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
        require_once AOSAI_PLUGIN_DIR . 'includes/services/class-aosai-smtp-service.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/services/class-aosai-webhook-service.php';

        require_once AOSAI_PLUGIN_DIR . 'includes/admin/class-aosai-admin.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/admin/class-aosai-admin-notices.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/admin/class-aosai-settings.php';
        require_once AOSAI_PLUGIN_DIR . 'includes/frontend/class-aosai-frontend.php';

        $controllers = array(
            'projects', 'tasks', 'task-lists', 'milestones', 'messages',
            'comments', 'files', 'activities', 'ai', 'settings', 'users', 'reports', 'profile',
            'portal', 'tickets', 'departments', 'tags', 'webhooks', 'inbound', 'notifications',
            'clients', 'invoices', 'time-entries',
        );
        foreach ( $controllers as $ctrl ) {
            require_once AOSAI_PLUGIN_DIR . "includes/api/class-aosai-rest-{$ctrl}.php";
        }
    }

    private function set_locale() {
        // WordPress handles plugin translation loading automatically.
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
        $this->loader->add_action( 'wp_head', $frontend, 'output_modulepreload_hints' );
        $this->loader->add_filter( 'show_admin_bar', $frontend, 'maybe_hide_admin_bar' );
        $this->loader->add_filter( 'template_include', $frontend, 'maybe_serve_portal_template' );
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
            new AOSAI_REST_Webhooks(),
            new AOSAI_REST_Inbound(),
            new AOSAI_REST_Notifications(),
            new AOSAI_REST_Clients(),
            new AOSAI_REST_Invoices(),
            new AOSAI_REST_Time_Entries(),
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

    private function define_smtp_hooks() {
        $smtp = AOSAI_SMTP_Service::get_instance();
        $this->loader->add_action( 'phpmailer_init', $smtp, 'configure_phpmailer' );
    }

    private function define_security_hooks() {
        $login_activity = AOSAI_Login_Activity::get_instance();
        $this->loader->add_action( 'init', $login_activity, 'ensure_table' );
        $this->loader->add_action( 'wp_login', $login_activity, 'on_user_login', 10, 2 );
        $this->loader->add_action( 'wp_login_failed', $login_activity, 'on_login_failed', 10, 1 );
        $this->loader->add_action( 'wp_logout', $login_activity, 'on_user_logout' );
    }

    public function run() {
        $this->loader->run();
    }
}
