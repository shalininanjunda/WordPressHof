function german_posts_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'per_page' => -1, // show all posts, JS will paginate
    ], $atts, 'german_posts' );

    ob_start();

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'     => '_external_source_link',
                'compare' => 'EXISTS',
            ],
        ],
    ];

    $query = new WP_Query($args);

    if ($query->have_posts()) :
        echo '<div id="posts-container">';
        echo '<div class="aktuelles-grid">';

        while ($query->have_posts()) : $query->the_post();
            $image = get_post_meta(get_the_ID(), '_external_thumbnail_url', true);
            $link  = get_post_meta(get_the_ID(), '_external_source_link', true);
            $categories = get_the_category();
            $cat_name   = $categories ? esc_html($categories[0]->name) : '';

            echo '<article class="aktuelles-item">';

            if ($image) {
                echo '<a href="'. esc_url($link) .'" target="_blank">
                        <img src="'. esc_url($image) .'" alt="'. esc_attr(get_the_title()) .'">
                      </a>';
            }

            if ($cat_name) {
                echo '<div class="news-category">'. $cat_name .'</div>';
            }

            echo '<h3><a href="'. esc_url($link) .'" target="_blank">'. get_the_title() .'</a></h3>';
            echo '<p>'. wp_trim_words(get_the_content(), 25, '...') .'</p>';
            echo '<span class="date">'. get_the_date() .'</span>';

            echo '</article>';
        endwhile;

        echo '</div>'; // .aktuelles-grid
        echo '<div class="pagination"></div>';
        echo '</div>'; // #posts-container

        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
          const pageSize = 6;
          const container = document.querySelector("#posts-container");
          if (!container) return;

          const cards = Array.from(container.querySelectorAll(".aktuelles-item"));
          const pagination = container.querySelector(".pagination");

          function getPage() {
            const hash = window.location.hash.replace("#", "");
            const params = new URLSearchParams(hash);
            let page = parseInt(params.get("page")) || 1;
            if (page < 1) page = 1;
            return page;
          }

          function setPage(page) {
            const params = new URLSearchParams(window.location.hash.replace("#", ""));
            params.set("page", page);
            window.location.hash = params.toString();
          }

          function renderPagination(totalItems, currentPage) {
            const totalPages = Math.ceil(totalItems / pageSize);
            pagination.innerHTML = "";
            if (totalPages <= 1) return;

            // Prev
            if (currentPage > 1) {
              const prev = document.createElement("a");
              prev.href = "#page=" + (currentPage - 1);
              prev.textContent = "« Zurück";
              prev.classList.add("prev", "page-numbers");
              pagination.appendChild(prev);
            }

            let range = 2;
            let startPage = Math.max(1, currentPage - range);
            let endPage = Math.min(totalPages, currentPage + range);

            if (startPage > 1) {
              const first = document.createElement("a");
              first.href = "#page=1";
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
                const link = document.createElement("a");
                link.href = "#page=" + i;
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

              const last = document.createElement("a");
              last.href = "#page=" + totalPages;
              last.textContent = totalPages;
              last.classList.add("page-numbers");
              pagination.appendChild(last);
            }

            // Next
            if (currentPage < totalPages) {
              const next = document.createElement("a");
              next.href = "#page=" + (currentPage + 1);
              next.textContent = "Weiter »";
              next.classList.add("next", "page-numbers");
              pagination.appendChild(next);
            }
          }

          function applyPagination() {
            const totalItems = cards.length;
            let currentPage = getPage();
            const totalPages = Math.ceil(totalItems / pageSize);
            if (currentPage > totalPages) currentPage = totalPages || 1;

            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;

          
            cards.forEach((card, i) => {
              if (i >= start && i < end) {
                card.classList.remove("hidden");
              } else {
                card.classList.add("hidden");
              }
            });

            renderPagination(totalItems, currentPage);
          }

          window.addEventListener("hashchange", applyPagination);

          // Always run at first load
          if (!window.location.hash.includes("page=")) {
            setPage(1);
          }
          applyPagination();
        });
        </script>
        <style>
        .aktuelles-item.hidden {
          display: none !important;
        }
        </style>
        <?php

        wp_reset_postdata();
    else :
        echo '<p>No posts found.</p>';
    endif;

    return ob_get_clean();
}
add_shortcode( 'german_posts', 'german_posts_shortcode' );
