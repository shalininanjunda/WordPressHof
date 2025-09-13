// -------------------------
// FILTER FORM (global, before tabs)
// -------------------------
function projekte_filter_form_global($taxonomy) {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);

    if ($terms && !is_wp_error($terms)) {
        echo '<form class="projekte-filter">';
        echo '<select>';
        echo '<option value="all">Alle ' . ucfirst($taxonomy) . '</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
        }
        echo '</select>';
        echo '</form>';
    }
}

// -------------------------
// LIST SHORTCODE – exports ALL, JS paginates 9
// -------------------------
function projekte_list_shortcode($atts) {
    ob_start();

    $atts = shortcode_atts([
        'status' => 'all',  // all | aktuell | abgeschlossen
        'tab'    => 'all',
    ], $atts);

    $args = [
        'post_type'      => 'projekt',
        'posts_per_page' => -1, // load all, JS handles pagination
        'post_status'    => 'publish',
    ];

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

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        echo '<div class="projekte-list">';
        while ($query->have_posts()) {
            $query->the_post();

            $short_desc = get_post_meta(get_the_ID(), 'projekt_kurzbeschreibung', true);
            $logo_id    = get_post_meta(get_the_ID(), 'projekt_logo', true);
            $logo       = $logo_id ? wp_get_attachment_image($logo_id, 'large', false, ['class' => 'projekt-logo-img']) : '';

            $groups = wp_get_post_terms(get_the_ID(), 'forschungsgruppe', ['fields' => 'slugs']);
            $group_slug = !empty($groups) ? $groups[0] : 'all';

            echo '<div class="projekt-card" data-group="' . esc_attr($group_slug) . '">';
            if ($logo) {
                echo '<div class="projekt-image">' . $logo . '</div>';
            }

            echo '<h3>' . get_the_title() . '</h3>';

            $status_terms = wp_get_post_terms(get_the_ID(), 'projektstatus');
            if (!empty($status_terms) && !is_wp_error($status_terms)) {
                echo '<div class="projekt-status">' . esc_html($status_terms[0]->name) . '</div>';
            }

            if ($short_desc) {
                echo '<p>' . esc_html($short_desc) . '</p>';
            }

            echo '<a href="' . get_permalink() . '">Zum Projekt →</a>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="pagination"></div>';
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

        <?php projekte_filter_form_global('forschungsgruppe'); ?>

        <ul class="tab-nav">
            <li class="active"><a href="#tab=all" data-tab="all">Alle Projekte</a></li>
            <li><a href="#tab=aktuell" data-tab="aktuell">Aktuelle Projekte</a></li>
            <li><a href="#tab=abgeschlossen" data-tab="abgeschlossen">Abgeschlossene Projekte</a></li>
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

    <!-- Inline JS -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
      const tabs = document.querySelectorAll(".tab-nav a");
      const contents = document.querySelectorAll(".tab-content");
      const filterSelect = document.querySelector(".projekte-filter select");
      const pageSize = 9;

      function getState() {
        const hash = window.location.hash.replace("#", "");
        const params = new URLSearchParams(hash);
        let page = parseInt(params.get("page")) || 1;
        if (page < 1) page = 1;
        return {
          filter: params.get("filter") || "all",
          tab: params.get("tab") || "all",
          page: page,
        };
      }

      function setState(newState) {
        const state = getState();
        const params = new URLSearchParams();
        params.set("filter", newState.filter ?? state.filter ?? "all");
        params.set("tab", newState.tab ?? state.tab ?? "all");
        params.set("page", newState.page ?? state.page ?? 1);
        window.location.hash = params.toString();
      }

      function activateTab(tab) {
        tabs.forEach(t => t.parentElement.classList.remove("active"));
        contents.forEach(c => c.classList.remove("active"));

        const target = document.getElementById("tab-" + tab);
        if (target) {
          document.querySelector('.tab-nav a[data-tab="' + tab + '"]').parentElement.classList.add("active");
          target.classList.add("active");
        }
      }

      function renderPagination(container, totalItems, currentPage, state) {
        const totalPages = Math.ceil(totalItems / pageSize);
        const pagination = container.querySelector(".pagination");
        if (!pagination) return;
        pagination.innerHTML = "";

        if (totalPages <= 1) return;

        // Prev link
        if (currentPage > 1) {
          const prevParams = new URLSearchParams();
          prevParams.set("filter", state.filter);
          prevParams.set("tab", state.tab);
          prevParams.set("page", currentPage - 1);
          const prev = document.createElement("a");
          prev.href = "#" + prevParams.toString();
          prev.textContent = "«";
          prev.classList.add("prev", "page-numbers");
          pagination.appendChild(prev);
        }

        let range = 2; // neighbors around current
        let startPage = Math.max(1, currentPage - range);
        let endPage = Math.min(totalPages, currentPage + range);

        if (startPage > 1) {
          // First page
          const firstParams = new URLSearchParams();
          firstParams.set("filter", state.filter);
          firstParams.set("tab", state.tab);
          firstParams.set("page", 1);
          const first = document.createElement("a");
          first.href = "#" + firstParams.toString();
          first.textContent = "1";
          first.classList.add("page-numbers");
          pagination.appendChild(first);

          if (startPage > 2) {
            const dots = document.createElement("span");
            dots.textContent = "…";
            dots.classList.add("page-numbers", "dots");
            pagination.appendChild(dots);
          }
        }

        for (let i = startPage; i <= endPage; i++) {
          if (i === currentPage) {
            const span = document.createElement("span");
            span.textContent = i;
            span.classList.add("page-numbers", "current");
            pagination.appendChild(span);
          } else {
            const params = new URLSearchParams();
            params.set("filter", state.filter);
            params.set("tab", state.tab);
            params.set("page", i);
            const link = document.createElement("a");
            link.href = "#" + params.toString();
            link.textContent = i;
            link.classList.add("page-numbers");
            pagination.appendChild(link);
          }
        }

        if (endPage < totalPages) {
          if (endPage < totalPages - 1) {
            const dots = document.createElement("span");
            dots.textContent = "…";
            dots.classList.add("page-numbers", "dots");
            pagination.appendChild(dots);
          }

          const lastParams = new URLSearchParams();
          lastParams.set("filter", state.filter);
          lastParams.set("tab", state.tab);
          lastParams.set("page", totalPages);
          const last = document.createElement("a");
          last.href = "#" + lastParams.toString();
          last.textContent = totalPages;
          last.classList.add("page-numbers");
          pagination.appendChild(last);
        }

        // Next link
        if (currentPage < totalPages) {
          const nextParams = new URLSearchParams();
          nextParams.set("filter", state.filter);
          nextParams.set("tab", state.tab);
          nextParams.set("page", currentPage + 1);
          const next = document.createElement("a");
          next.href = "#" + nextParams.toString();
          next.textContent = "»";
          next.classList.add("next", "page-numbers");
          pagination.appendChild(next);
        }
      }

      function applyState() {
        const state = getState();

        if (filterSelect) {
          if ([...filterSelect.options].some(opt => opt.value === state.filter)) {
            filterSelect.value = state.filter;
          } else {
            filterSelect.value = "all";
          }
        }

        activateTab(state.tab);

        const container = document.getElementById("tab-" + state.tab);
        if (!container) return;

        const cards = Array.from(container.querySelectorAll(".projekt-card"));
        let visibleCards = [];

        cards.forEach(card => {
          if (state.filter === "all" || card.dataset.group === state.filter) {
            visibleCards.push(card);
          }
        });

        const totalItems = visibleCards.length;
        const totalPages = Math.ceil(totalItems / pageSize);
        let currentPage = state.page;
        if (currentPage > totalPages) currentPage = totalPages || 1;

        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        cards.forEach(card => card.style.display = "none");
        visibleCards.slice(start, end).forEach(card => card.style.display = "");

        renderPagination(container, totalItems, currentPage, state);
      }

      // Events
      tabs.forEach(tab => {
        tab.addEventListener("click", function(e) {
          e.preventDefault();
          setState({ tab: this.dataset.tab, page: 1 });
        });
      });

      if (filterSelect) {
        filterSelect.addEventListener("change", function() {
          setState({ filter: this.value, tab: "all", page: 1 });
        });
      }

      window.addEventListener("hashchange", applyState);

      if (!window.location.hash) {
        setState({ filter: "all", tab: "all", page: 1 });
      } else {
        applyState();
      }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('projekte_tabs', 'projekte_tabs_shortcode');
