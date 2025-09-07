// -------------------------
// FILTER FORM (global, works for all tabs)
// -------------------------
function projekte_filter_form($taxonomy, $selected = '', $tab = 'all') {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);

    if ($terms && !is_wp_error($terms)) {
        echo '<form method="get" class="projekte-filter">';
        echo '<select name="' . esc_attr($taxonomy . '_' . $tab) . '" onchange="this.form.submit()">';
        echo '<option value="">Alle ' . ucfirst($taxonomy) . '</option>';
        foreach ($terms as $term) {
            $is_selected = ($selected == $term->slug) ? 'selected' : '';
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

    // Detect pagination separately per tab
    $paged_key = 'paged_' . $atts['tab'];
    $paged = isset($_GET[$paged_key]) ? intval($_GET[$paged_key]) : 1;

    $args = [
        'post_type'      => 'projekt',
        'posts_per_page' => 9,
        'paged'          => $paged,
        'post_status'    => 'publish',
    ];

    // ✅ Filter by projektstatus taxonomy
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

    // ✅ Extra filter: Forschungsgruppe
    $tax_filter_key = 'forschungsgruppe_' . $atts['tab'];
    if (!empty($_GET[$tax_filter_key])) {
        $args['tax_query'][] = [
            'taxonomy' => 'forschungsgruppe',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_GET[$tax_filter_key]),
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
            
            // ✅ Full-width Logo
            if ($logo) {
                echo '<div class="projekt-image">' . $logo . '</div>';
            }

            // Title
            echo '<h3>' . get_the_title() . '</h3>';

            // ✅ Show projektstatus name
            $status_id = get_post_meta(get_the_ID(), 'projekt_status', true);
            if ($status_id) {
                $status_term = get_term_by('id', intval($status_id), 'projektstatus');
                if ($status_term && !is_wp_error($status_term)) {
                    echo '<div class="projekt-status">' . esc_html($status_term->name) . '</div>';
                }
            }

            // Short description
            if ($short_desc) {
                echo '<p>' . esc_html($short_desc) . '</p>';
            }

            // Link
            echo '<a href="' . get_permalink() . '">Zum Projekt →</a>';

            echo '</div>'; // .projekt-card
        }
        echo '</div>'; // .projekte-list

        // ✅ Separate pagination per tab
        echo '<div class="pagination">';
        echo paginate_links([
            'total'   => $query->max_num_pages,
            'current' => $paged,
            'format'  => '?' . $paged_key . '=%#%',
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
    ob_start();
    ?>
    <div class="projekte-tabs">

        <!-- ✅ Global Filter Above Tabs -->
        <div class="projekte-filter">
            <?php projekte_filter_form('forschungsgruppe', $_GET['forschungsgruppe_all'] ?? '', 'all'); ?>
        </div>

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
        // deactivate all
        tabs.forEach(t => t.parentElement.classList.remove("active"));
        contents.forEach(c => c.classList.remove("active"));

        // activate selected tab
        const target = document.getElementById("tab-" + tab);
        if (target) {
            document.querySelector('.tab-nav a[data-tab="' + tab + '"]').parentElement.classList.add("active");
            target.classList.add("active");

            // fix pagination links for this tab
            updatePaginationLinks(tab);
        }
    }

    function updatePaginationLinks(tab) {
        const paginationLinks = document.querySelectorAll("#tab-" + tab + " .pagination a");
        paginationLinks.forEach(link => {
            const url = new URL(link.href);

            // ✅ keep the pagination param (?paged_tab=X), remove everything else
            const searchParams = new URLSearchParams(url.search);
            let newSearch = "";

            for (const [key, value] of searchParams.entries()) {
                if (key.startsWith("paged_")) {
                    newSearch = "?" + key + "=" + value;
                }
            }

            link.href = url.pathname + newSearch + "#" + tab;
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener("click", function(e) {
            e.preventDefault();
            const tabName = this.dataset.tab;

            // ✅ Reset URL to only hash (no filters, no pagination)
            history.replaceState(null, null, "#" + tabName);

            activateTab(tabName);
        });
    });

    // on page load
    const initialTab = window.location.hash.replace("#", "") || "all";
    activateTab(initialTab);
});
</script>

    <?php
    return ob_get_clean();
}
add_shortcode('projekte_tabs', 'projekte_tabs_shortcode');
