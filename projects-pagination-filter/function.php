// -------------------------
// FILTER FORM (global, before tabs)
// -------------------------
function projekte_filter_form_global($taxonomy) {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);

    if ($terms && !is_wp_error($terms)) {
        echo '<form method="get" class="projekte-filter">';
        echo '<select id="filter-' . esc_attr($taxonomy) . '" name="' . esc_attr($taxonomy) . '">';
        echo '<option value="">Alle ' . ucfirst($taxonomy) . '</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
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

    // Pagination per tab
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
    const filterDropdown = document.querySelector(".projekte-filter select");

    // ✅ Restore saved filter in dropdown
    const savedFilter = localStorage.getItem("selectedFilter");
    if (filterDropdown && savedFilter) {
        filterDropdown.value = savedFilter;
    }

    // ✅ Save filter on change
    if (filterDropdown) {
        filterDropdown.addEventListener("change", function() {
            localStorage.setItem("selectedFilter", this.value);
            reloadWithParams();
        });
    }

    // ✅ Build full URL with tab + filter
    function reloadWithParams(tabName = null, page = null) {
        const baseUrl = window.location.origin + window.location.pathname;
        const filterVal = localStorage.getItem("selectedFilter") || "";
        const currentTab = tabName || (document.querySelector(".tab-nav li.active a")?.dataset.tab || "all");

        let query = [];
        if (filterVal) query.push("forschungsgruppe=" + encodeURIComponent(filterVal));
        if (page) query.push(page);

        // reload page with proper hash
        const url = baseUrl + (query.length ? "?" + query.join("&") : "") + "#" + currentTab;
        window.location.href = url;
    }

    function activateTab(tab) {
        tabs.forEach(t => t.parentElement.classList.remove("active"));
        contents.forEach(c => c.classList.remove("active"));

        const target = document.getElementById("tab-" + tab);
        if (target) {
            const navLink = document.querySelector('.tab-nav a[data-tab="' + tab + '"]');
            if (navLink) navLink.parentElement.classList.add("active");
            target.classList.add("active");
            updatePaginationLinks(tab);
        }
    }

    function updatePaginationLinks(tab) {
        const paginationLinks = document.querySelectorAll("#tab-" + tab + " .pagination a");
        paginationLinks.forEach(link => {
            const url = new URL(link.href);
            const filterVal = localStorage.getItem("selectedFilter") || "";
            if (filterVal) {
                url.searchParams.set("forschungsgruppe", filterVal);
            }
            link.href = url.pathname + url.search + "#" + tab;
        });
    }

    // ✅ Tab click → reload with filter
    tabs.forEach(tab => {
        tab.addEventListener("click", function(e) {
            e.preventDefault();
            const tabName = this.dataset.tab;
            reloadWithParams(tabName);
        });
    });

    // ✅ On page load → activate tab based on hash
    const initialTab = window.location.hash.replace("#", "") || "all";
    activateTab(initialTab);

    // ✅ Handle back/forward navigation
    window.addEventListener("hashchange", function() {
        const currentTab = window.location.hash.replace("#", "") || "all";
        activateTab(currentTab);
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const tabs = document.querySelectorAll(".tab-nav a");
    const contents = document.querySelectorAll(".tab-content");
    const filterDropdown = document.querySelector(".projekte-filter select");

    // ✅ Restore saved filter in dropdown
    const savedFilter = localStorage.getItem("selectedFilter");
    if (filterDropdown && savedFilter) {
        filterDropdown.value = savedFilter;
    }

    // ✅ Save filter on change
    if (filterDropdown) {
        filterDropdown.addEventListener("change", function() {
            localStorage.setItem("selectedFilter", this.value);
            reloadWithParams();
        });
    }

    // ✅ Build full URL with tab + filter
    function reloadWithParams(tabName = null, page = null) {
        const baseUrl = window.location.origin + window.location.pathname;
        const filterVal = localStorage.getItem("selectedFilter") || "";
        const currentTab = tabName || (document.querySelector(".tab-nav li.active a")?.dataset.tab || "all");

        let query = [];
        if (filterVal) query.push("forschungsgruppe=" + encodeURIComponent(filterVal));
        if (page) query.push(page);

        // reload page with proper hash
        const url = baseUrl + (query.length ? "?" + query.join("&") : "") + "#" + currentTab;
        window.location.href = url;
    }

    function activateTab(tab) {
        tabs.forEach(t => t.parentElement.classList.remove("active"));
        contents.forEach(c => c.classList.remove("active"));

        const target = document.getElementById("tab-" + tab);
        if (target) {
            const navLink = document.querySelector('.tab-nav a[data-tab="' + tab + '"]');
            if (navLink) navLink.parentElement.classList.add("active");
            target.classList.add("active");
            updatePaginationLinks(tab);
        }
    }

    function updatePaginationLinks(tab) {
        const paginationLinks = document.querySelectorAll("#tab-" + tab + " .pagination a");
        paginationLinks.forEach(link => {
            const url = new URL(link.href);
            const filterVal = localStorage.getItem("selectedFilter") || "";
            if (filterVal) {
                url.searchParams.set("forschungsgruppe", filterVal);
            }
            link.href = url.pathname + url.search + "#" + tab;
        });
    }

    // ✅ Tab click → reload with filter
    tabs.forEach(tab => {
        tab.addEventListener("click", function(e) {
            e.preventDefault();
            const tabName = this.dataset.tab;
            reloadWithParams(tabName);
        });
    });

    // ✅ On page load → activate tab based on hash
    const initialTab = window.location.hash.replace("#", "") || "all";
    activateTab(initialTab);

    // ✅ Handle back/forward navigation
    window.addEventListener("hashchange", function() {
        const currentTab = window.location.hash.replace("#", "") || "all";
        activateTab(currentTab);
    });
});
</script>


    <?php
    return ob_get_clean();
}
add_shortcode('projekte_tabs', 'projekte_tabs_shortcode');
