// Shortcode: [projects_listing]
function projects_listing_shortcode() {
    ob_start();

    // --- 1. Get params (defaults to 'all') ---
    $selected_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
    $selected_group  = isset($_GET['research_group']) ? sanitize_text_field($_GET['research_group']) : '';
    $paged           = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // --- 2. Map status slugs to term IDs ---
    $status_map = [
        'current'   => 28, // replace with actual ID
        'completed' => 29, // replace with actual ID
    ];

    // --- 3. Map research groups (slug => ID) ---
    $group_map = [
        'napro' => 5,
        'phowa' => 7,
        'rele'  => 12,
        'diwa'  => 39,
    ];

    // --- 4. Tabs ---
    echo '<div class="projects-tabs">';
    echo '<ul class="tabs-nav">';
    echo '<li><a href="?status=all' . ($selected_group ? '&research_group=' . esc_attr($selected_group) : '') . '" class="' . (($selected_status === 'all' || empty($selected_status)) ? 'active' : '') . '">All projects</a></li>';
    echo '<li><a href="?status=current' . ($selected_group ? '&research_group=' . esc_attr($selected_group) : '') . '" class="' . ($selected_status === 'current' ? 'active' : '') . '">Current projects</a></li>';
    echo '<li><a href="?status=completed' . ($selected_group ? '&research_group=' . esc_attr($selected_group) : '') . '" class="' . ($selected_status === 'completed' ? 'active' : '') . '">Completed projects</a></li>';
    echo '</ul>';
    echo '</div>';

    // --- 5. Research group dropdown ---
    echo '<form method="get" class="research-filter">';
    echo '<input type="hidden" name="status" value="' . esc_attr($selected_status) . '">';
    echo '<select name="research_group" onchange="this.form.submit()">';
    echo '<option value="">All research groups</option>';
    foreach ($group_map as $slug => $id) {
        echo '<option value="' . esc_attr($slug) . '" ' . selected($selected_group, $slug, false) . '>' . strtoupper($slug) . '</option>';
    }
    echo '</select>';
    echo '</form>';

    // --- 6. Build query ---
    $args = [
        'post_type'      => 'project',
        'posts_per_page' => 6,
        'paged'          => $paged,
    ];

    $meta_query = ['relation' => 'AND'];

    // Only add filters if not "all"
    if ($selected_status !== 'all' && isset($status_map[$selected_status])) {
        $meta_query[] = [
            'key'   => 'project_status',
            'value' => $status_map[$selected_status],
        ];
    }

    if (!empty($selected_group) && isset($group_map[$selected_group])) {
        $meta_query[] = [
            'key'     => 'research group',
            'value'   => '"' . $group_map[$selected_group] . '"',
            'compare' => 'LIKE',
        ];
    }

    // Add meta_query only if we actually have filters
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    // --- 7. Query ---
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        echo '<div class="projects-list">';
        while ($query->have_posts()) {
            $query->the_post();

            $short_desc = get_post_meta(get_the_ID(), 'project_short_description', true);
            $logo_id    = get_post_meta(get_the_ID(), 'project_logo', true);
            $logo       = $logo_id ? wp_get_attachment_image($logo_id, 'medium') : '';

            echo '<div class="project-card">';
            if ($logo) echo '<div class="project-logo">' . $logo . '</div>';
            echo '<h3>' . get_the_title() . '</h3>';
            if ($short_desc) echo '<p>' . esc_html($short_desc) . '</p>';
            echo '<a href="' . get_permalink() . '">About the project →</a>';
            echo '</div>';
        }
        echo '</div>';

        // Pagination
        echo '<div class="pagination">';
        echo paginate_links([
            'total'   => $query->max_num_pages,
            'current' => $paged,
        ]);
        echo '</div>';
    } else {
        echo '<p>No projects found.</p>';
    }

    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('projects_listing', 'projects_listing_shortcode');
