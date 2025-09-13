// -------------------------
// FILTER FORM (global, before tabs)
// -------------------------
function projekte_filter_form_global($taxonomy) {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);

    if ($terms && !is_wp_error($terms)) {
        echo '<form method="get" class="projekte-filter">';
        echo '<select name="' . esc_attr($taxonomy) . '" onchange="this.form.submit()">';
        echo '<option value="">Alle ' . ucfirst($taxonomy) . '</option>';
        foreach ($terms as $term) {
            $is_selected = (isset($_GET[$taxonomy]) && $_GET[$taxonomy] == $term->slug) ? 'selected' : '';
            echo '<option value="' . esc_attr($term->slug) . '" ' . $is_selected . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select>';
        echo '</form>';
    }
}

// -------------------------
// LIST SHORTCODE
// -------------------------
function projekte_list_shortcode($atts) {
    ob_start();

    $atts = shortcode_atts([
        'status' => 'all',  // all | aktuell | abgeschlossen
        'tab'    => 'all',
    ], $atts);

    $paged_key = 'paged_' . $atts['tab'];
    $paged = isset($_GET[$paged_key]) ? intval($_GET[$paged_key]) : 1;

    $args = [
        'post_type'      => 'projekt',
        'posts_per_page' => 9,
        'paged'          => $paged,
        'post_status'    => 'publish',
    ];

    // ✅ projektstatus filter
    if ($atts['status'] === 'aktuell') {
        $args['tax_query'][] = [
            'taxonomy' => 'projektstatus',
            'field'    => 'slug',
            'terms'    => ['aktuell'],
        ];
    } elseif ($atts['status'] === 'abgeschlossen') {
        $args['tax_query'][] = [
            'taxonomy' => 'projektstatus',
            'field'    => 'slug',
            'terms'    => ['abgeschlossen'],
        ];
    }

    // ✅ global filter (forschungsgruppe)
    if (!empty($_GET['forschungsgruppe'])) {
        $args['tax_query'][] = [
            'taxonomy' => 'forschungsgruppe',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_GET['forschungsgruppe']),
        ];
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        echo '<div class="projekte-list">';
        while ($query->have_posts()) {
            $query->the_post();

            $short_desc = get_post_meta(get_the_ID(), 'projekt_kurzbeschreibung', true);
            $logo_id    = get_post_meta(get_the_ID(), 'projekt_logo', true);
            $logo       = $logo_id ? wp_get_attachment_image($logo_id, 'large', false, ['class' => 'projekt-logo-img']) : '';

            echo '<div class="projekt-card">';
            if ($logo) {
                echo '<div class="projekt-image">' . $logo . '</div>';
            }

            echo '<h3>' . get_the_title() . '</h3>';

            // ✅ projektstatus name
            $status_id = get_post_meta(get_the_ID(), 'projekt_status', true);
            if ($status_id) {
                $status_term = get_term_by('id', intval($status_id), 'projektstatus');
                if ($status_term && !is_wp_error($status_term)) {
                    echo '<div class="projekt-status">' . esc_html($status_term->name) . '</div>';
                }
            }

            if ($short_desc) {
                echo '<p>' . esc_html($short_desc) . '</p>';
            }

            echo '<a href="' . get_permalink() . '">Zum Projekt →</a>';
            echo '</div>';
        }
        echo '</div>';

        // ✅ Pagination
        echo '<div class="pagination">';
        echo paginate_links([
            'total'   => $query->max_num_pages,
            'current' => $paged,
            'format'  => '?' . $paged_key . '=%#%',
            'add_args' => array_filter($_GET), // keep filter param
        ]);
        echo '</div>';
    } else {
        echo '<p>Keine Projekte gefunden.</p>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('projekte_list', 'projekte_list_shortcode');

// -------------------------
// TABS SHORTCODE
// -------------------------
function projekte_tabs_shortcode() {
    ob_start(); ?>
    <div class="projekte-tabs">

        <!-- ✅ Global Filter before tabs -->
        <?php projekte_filter_form_global('forschungsgruppe'); ?>

        <ul class="tab-nav">
            <li class="active"><a href="#all" data-tab="all">Alle Projekte</a></li>
            <li><a href="#aktuell" data-tab="aktuell">Aktuelle Projekte</a></li>
            <li><a href="#abgeschlossen" data-tab="abgeschlossen">Abgeschlossene Projekte</a></li>
        </ul>

        <div class="tab-content active" id="tab-all">
            <?php echo do_shortcode('[projekte_list status="all" tab="all"]'); ?>
        </div>
        <div class="tab-content" id="tab-aktuell">
            <?php echo do_shortcode('[projekte_list status="aktuell" tab="aktuell"]'); ?>
        </div>
        <div class="tab-content" id="tab-abgeschlossen">
            <?php echo do_shortcode('[projekte_list status="abgeschlossen" tab="abgeschlossen"]'); ?>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const tabs = document.querySelectorAll(".tab-nav a");
        const contents = document.querySelectorAll(".tab-content");

        function activateTab(tab) {
            tabs.forEach(t => t.parentElement.classList.remove("active"));
            contents.forEach(c => c.classList.remove("active"));

            const target = document.getElementById("tab-" + tab);
            if (target) {
                document.querySelector('.tab-nav a[data-tab="' + tab + '"]').parentElement.classList.add("active");
                target.classList.add("active");
                updatePaginationLinks(tab);
            }
        }

        function updatePaginationLinks(tab) {
            const paginationLinks = document.querySelectorAll("#tab-" + tab + " .pagination a");
            paginationLinks.forEach(link => {
                const url = new URL(link.href);
                link.href = url.pathname + url.search + "#" + tab;
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener("click", function(e) {
                e.preventDefault();
                const tabName = this.dataset.tab;
                history.replaceState(null, null, "#" + tabName);
                activateTab(tabName);
            });
        });

        const initialTab = window.location.hash.replace("#", "") || "all";
        activateTab(initialTab);
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('projekte_tabs', 'projekte_tabs_shortcode');
