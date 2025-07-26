
<?php
/**
 * Plugin Name: Client Projects Manager
 * Description: Manage client projects with admin UI, shortcode, AJAX filtering, and REST API.
 * Version: 1.1
 * Author: Popoola Samuel
 */

defined('ABSPATH') || exit;

// Register Custom Post Type
add_action('init', 'cpm_register_custom_post_type');
function cpm_register_custom_post_type() {
    $labels = [
        'name' => 'Client Projects',
        'singular_name' => 'Client Project',
        'add_new' => 'Add New Project',
        'add_new_item' => 'Add New Client Project',
        'edit_item' => 'Edit Project',
        'new_item' => 'New Project',
        'view_item' => 'View Project',
        'search_items' => 'Search Projects',
        'not_found' => 'No projects found',
        'all_items' => 'All Projects',
        'menu_name' => 'Client Projects',
    ];

    $args = [
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-portfolio',
        'supports' => ['title', 'editor'],
        'show_in_rest' => true,
    ];

    register_post_type('client_project', $args);
}

// Add Meta Boxes
add_action('add_meta_boxes', 'cpm_add_meta_box');
function cpm_add_meta_box() {
    add_meta_box('cpm_project_meta', 'Project Details', 'cpm_render_meta_fields', 'client_project', 'normal', 'default');
}

function cpm_render_meta_fields($post) {
    $client_name = get_post_meta($post->ID, '_client_name', true);
    $status = get_post_meta($post->ID, '_project_status', true);
    $deadline = get_post_meta($post->ID, '_project_deadline', true);
    wp_nonce_field('cpm_save_meta_fields', 'cpm_meta_nonce');
    ?>
    <p><label>Client Name:</label><br>
        <input type="text" name="client_name" value="<?php echo esc_attr($client_name); ?>" style="width:100%;">
    </p>
    <p><label>Project Status:</label><br>
        <select name="project_status">
            <option value="Ongoing" <?php selected($status, 'Ongoing'); ?>>Ongoing</option>
            <option value="Completed" <?php selected($status, 'Completed'); ?>>Completed</option>
            <option value="Pending" <?php selected($status, 'Pending'); ?>>Pending</option>
        </select>
    </p>
    <p><label>Deadline:</label><br>
        <input type="date" name="project_deadline" value="<?php echo esc_attr($deadline); ?>">
    </p>
    <?php
}

add_action('save_post', 'cpm_save_meta_fields');
function cpm_save_meta_fields($post_id) {
    if (!isset($_POST['cpm_meta_nonce']) || !wp_verify_nonce($_POST['cpm_meta_nonce'], 'cpm_save_meta_fields')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['client_name'])) update_post_meta($post_id, '_client_name', sanitize_text_field($_POST['client_name']));
    if (isset($_POST['project_status'])) update_post_meta($post_id, '_project_status', sanitize_text_field($_POST['project_status']));
    if (isset($_POST['project_deadline'])) update_post_meta($post_id, '_project_deadline', sanitize_text_field($_POST['project_deadline']));
}

// Enqueue AJAX
add_action('wp_enqueue_scripts', 'cpm_enqueue_scripts');
function cpm_enqueue_scripts() {
    wp_enqueue_script('cpm-ajax', plugin_dir_url(__FILE__) . 'ajax.js', ['jquery'], '1.0', true);
    wp_localize_script('cpm-ajax', 'cpm_ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('cpm_ajax_nonce'),
    ]);
}

// Shortcode: [client_projects]
add_shortcode('client_projects', 'cpm_render_projects_shortcode');
function cpm_render_projects_shortcode() {
    ob_start();
    ?>
    <form id="cpm-filter-form" method="get">
        <label for="status">Filter by Status:</label>
        <select name="status">
            <option value="">-- All --</option>
            <option value="Ongoing">Ongoing</option>
            <option value="Completed">Completed</option>
            <option value="Pending">Pending</option>
        </select>
    </form>
    <div class="client-projects-grid" style="display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); margin-top: 20px;">
        <?php echo cpm_render_project_cards(); ?>
    </div>
    <?php
    return ob_get_clean();
}

function cpm_render_project_cards($status = '') {
    $args = ['post_type' => 'client_project', 'posts_per_page' => -1];
    if ($status) {
        $args['meta_query'] = [[
            'key' => '_project_status',
            'value' => $status,
            'compare' => '='
        ]];
    }
    $query = new WP_Query($args);
    ob_start();
    if ($query->have_posts()):
        while ($query->have_posts()): $query->the_post(); ?>
            <div class="client-project-box" style="border: 1px solid #ccc; padding: 15px;">
                <h3><?php the_title(); ?></h3>
                <p><strong>Client:</strong> <?php echo esc_html(get_post_meta(get_the_ID(), '_client_name', true)); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html(get_post_meta(get_the_ID(), '_project_status', true)); ?></p>
                <p><strong>Deadline:</strong> <?php echo esc_html(get_post_meta(get_the_ID(), '_project_deadline', true)); ?></p>
                <div><?php the_content(); ?></div>
            </div>
        <?php endwhile;
    else:
        echo '<p>No projects found.</p>';
    endif;
    wp_reset_postdata();
    return ob_get_clean();
}

// AJAX Handler
add_action('wp_ajax_cpm_filter_projects', 'cpm_filter_projects_ajax');
add_action('wp_ajax_nopriv_cpm_filter_projects', 'cpm_filter_projects_ajax');
function cpm_filter_projects_ajax() {
    check_ajax_referer('cpm_ajax_nonce', 'nonce');
    $status = sanitize_text_field($_POST['status']);
    echo cpm_render_project_cards($status);
    wp_die();
}

// REST API Endpoint
add_action('rest_api_init', function () {
    register_rest_route('cpm/v1', '/projects', [
        'methods' => 'GET',
        'callback' => 'cpm_rest_get_projects',
        'permission_callback' => '__return_true',
    ]);
});

function cpm_rest_get_projects($request) {
    $status = sanitize_text_field($request->get_param('status'));
    $args = ['post_type' => 'client_project', 'posts_per_page' => -1];
    if ($status) {
        $args['meta_query'] = [[
            'key' => '_project_status',
            'value' => $status,
            'compare' => '='
        ]];
    }
    $query = new WP_Query($args);
    $projects = [];
    while ($query->have_posts()): $query->the_post();
        $projects[] = [
            'title' => get_the_title(),
            'client' => get_post_meta(get_the_ID(), '_client_name', true),
            'status' => get_post_meta(get_the_ID(), '_project_status', true),
            'deadline' => get_post_meta(get_the_ID(), '_project_deadline', true),
            'description' => get_the_content(),
        ];
    endwhile;
    return rest_ensure_response($projects);
}

// Admin Columns: Status + Deadline + Color
add_filter('manage_client_project_posts_columns', 'cpm_add_custom_columns');
function cpm_add_custom_columns($columns) {
    $columns['project_status'] = 'Status';
    $columns['project_deadline'] = 'Deadline';
    return $columns;
}

add_action('manage_client_project_posts_custom_column', 'cpm_fill_custom_columns', 10, 2);
function cpm_fill_custom_columns($column, $post_id) {
    if ($column === 'project_status') {
        $status = get_post_meta($post_id, '_project_status', true);
        $color = match ($status) {
            'Pending' => 'orange',
            'Ongoing' => 'blue',
            'Completed' => 'green',
            default => 'gray',
        };
        echo '<span style="background-color:' . esc_attr($color) . '; color:white; padding:2px 6px; border-radius:3px;">' . esc_html($status) . '</span>';
    }
    if ($column === 'project_deadline') {
        echo esc_html(get_post_meta($post_id, '_project_deadline', true));
    }
}

add_filter('manage_edit-client_project_sortable_columns', 'cpm_make_columns_sortable');
function cpm_make_columns_sortable($columns) {
    $columns['project_status'] = 'project_status';
    $columns['project_deadline'] = 'project_deadline';
    return $columns;
}

add_action('pre_get_posts', 'cpm_custom_column_orderby');
function cpm_custom_column_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('orderby') === 'project_status') {
        $query->set('meta_key', '_project_status');
        $query->set('orderby', 'meta_value');
    }
    if ($query->get('orderby') === 'project_deadline') {
        $query->set('meta_key', '_project_deadline');
        $query->set('orderby', 'meta_value');
    }
}

// Admin Filter Dropdown
add_action('restrict_manage_posts', 'cpm_admin_filter_by_status');
function cpm_admin_filter_by_status() {
    global $typenow;
    if ($typenow === 'client_project') {
        $current_status = $_GET['project_status'] ?? '';
        ?>
        <select name="project_status">
            <option value="">All Statuses</option>
            <option value="Pending" <?php selected($current_status, 'Pending'); ?>>Pending</option>
            <option value="Ongoing" <?php selected($current_status, 'Ongoing'); ?>>Ongoing</option>
            <option value="Completed" <?php selected($current_status, 'Completed'); ?>>Completed</option>
        </select>
        <?php
    }
}

add_filter('parse_query', 'cpm_filter_projects_by_status_in_admin');
function cpm_filter_projects_by_status_in_admin($query) {
    global $pagenow;
    if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'client_project' && !empty($_GET['project_status'])) {
        $query->query_vars['meta_key'] = '_project_status';
        $query->query_vars['meta_value'] = sanitize_text_field($_GET['project_status']);
    }
}
