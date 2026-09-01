<?php
/**
 * inc/rebuild-progress.php
 *
 * Admin dashboard widget — Rebuild Progress.
 *
 * Shows rebuilt vs. empty counts across all theme CPTs, with per-post edit
 * links and links to the equivalent page on the legacy site.
 *
 * "Empty" = no real block content (no <!-- wp: marker, or only the trivial
 * default-empty-paragraph block the editor silently adds on first open).
 *
 * Product posts with a `redirect_to_solution` field set are intentional
 * aliases that will never have their own content — they are counted separately
 * as "alias" and excluded from the empty/unrebuilt count.
 *
 * Data is cached in a transient for 5 minutes. A "Refresh" link (nonce-
 * protected) clears it on demand.
 */

// ── Constants ──────────────────────────────────────────────────────────────────

/** Legacy site base URL for generating comparison links. */
define( 'MOMENTIVE_RP_LEGACY_DOMAIN', 'https://momentivesoftware.com' );

/** Transient key and TTL. */
define( 'MOMENTIVE_RP_TRANSIENT',     'momentive_rebuild_progress_v2' );
define( 'MOMENTIVE_RP_TRANSIENT_TTL', 5 * MINUTE_IN_SECONDS );

/**
 * Regex that matches a post_content consisting of only the trivial empty
 * paragraph block the editor silently inserts on first open/save.
 * A post matching this pattern is not considered "rebuilt."
 * Must match the same pattern used in create-empty-posts.php and
 * report-rebuild-progress.php.
 */
define( 'MOMENTIVE_RP_TRIVIAL_EMPTY',
    '/^<!--\s*wp:paragraph\s*-->\s*<p[^>]*>\s*<\/p>\s*<!--\s*\/wp:paragraph\s*-->$/' );

/**
 * CPTs to track, in display order.
 * Any type not currently registered is silently skipped.
 *
 * Excluded from tracking (no singular URLs — content is not "rebuilt" in the
 * traditional sense, or the legacy site had no equivalent pages):
 *   - testimonials   no singular URL; assumed complete after migration
 *   - people         no legacy equivalent; new CPT, assumed current
 *   - integration    no singular URL; all complete (meta-only posts)
 *   - fundraiser     no singular URL; all complete (meta-only posts)
 */
define( 'MOMENTIVE_RP_CPTS', serialize( [
    'post', 'page', 'solutions', 'product', 'case-study',
    'webinar', 'whitepaper', 'press-article',
    'faq', 'guide', 'infographic', 'event', 'video',
    'interactive-tool', 'toolkit',
    'product-overview', 'who-we-serve',
] ) );

// ── Bootstrap ─────────────────────────────────────────────────────────────────

if ( is_admin() ) {
    add_action( 'wp_dashboard_setup', 'momentive_rp_register_widget' );
    add_action( 'admin_init',          'momentive_rp_handle_refresh' );
}

// ── Widget registration ───────────────────────────────────────────────────────

function momentive_rp_register_widget(): void {
    wp_add_dashboard_widget(
        'momentive_rebuild_progress',
        'Rebuild Progress',
        'momentive_rp_widget_cb',
        null,
        null,
        'normal',
        'high'
    );
}

// ── Cache-clear handler ───────────────────────────────────────────────────────

function momentive_rp_handle_refresh(): void {
    if ( ! isset( $_GET['msw_rp_refresh'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    check_admin_referer( 'msw_rp_refresh' );
    delete_transient( MOMENTIVE_RP_TRANSIENT );
    wp_safe_redirect( admin_url() );
    exit;
}

// ── Data layer ────────────────────────────────────────────────────────────────

/**
 * Returns the rebuild-progress data array, from cache if available.
 *
 * Shape per CPT:
 *   label     string   Human-readable name
 *   total     int      Total published posts
 *   built     int      Posts with real block content
 *   alias     int      Products aliased via redirect_to_solution (product CPT only)
 *   unrebuilt array    [ { id, title, edit_link, legacy_url } ]
 */
function momentive_rp_get_data(): array {
    $cached = get_transient( MOMENTIVE_RP_TRANSIENT );
    if ( false !== $cached ) return $cached;

    global $wpdb;

    $cpts = unserialize( MOMENTIVE_RP_CPTS );
    $data = [];

    // Pre-fetch product IDs that are redirect aliases (one query for all).
    $alias_ids = [];
    if ( post_type_exists( 'product' ) ) {
        $alias_ids = array_map( 'intval', (array) $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = 'redirect_to_solution'
             AND meta_value != ''
             AND meta_value != '0'"
        ) );
    }

    foreach ( $cpts as $cpt ) {
        if ( ! post_type_exists( $cpt ) ) continue;

        $obj   = get_post_type_object( $cpt );
        $label = $obj->labels->name ?? $cpt;

        // Fetch all published posts: ID + title + slug + content in one query.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title, post_name, post_content
               FROM {$wpdb->posts}
              WHERE post_type   = %s
                AND post_status = 'publish'
           ORDER BY post_title ASC",
            $cpt
        ) );

        $built     = 0;
        $alias     = 0;
        $unrebuilt = [];

        foreach ( $rows as $row ) {
            $id = (int) $row->ID;

            // Products that redirect to a Solution are intentional aliases —
            // they will never have their own block content.
            if ( 'product' === $cpt && in_array( $id, $alias_ids, true ) ) {
                $alias++;
                continue;
            }

            if ( momentive_rp_is_rebuilt( $row->post_content ) ) {
                $built++;
            } else {
                $unrebuilt[] = [
                    'id'         => $id,
                    'title'      => $row->post_title ?: $row->post_name,
                    'edit_link'  => get_edit_post_link( $id, 'raw' ),
                    'legacy_url' => momentive_rp_legacy_url( $id ),
                ];
            }
        }

        $data[ $cpt ] = [
            'label'     => $label,
            'total'     => count( $rows ),
            'built'     => $built,
            'alias'     => $alias,
            'unrebuilt' => $unrebuilt,
        ];
    }

    set_transient( MOMENTIVE_RP_TRANSIENT, $data, MOMENTIVE_RP_TRANSIENT_TTL );
    return $data;
}

/**
 * Returns true if the given post_content string contains real block content —
 * at least one block marker that isn't just the trivial empty paragraph.
 */
function momentive_rp_is_rebuilt( string $content ): bool {
    if ( ! str_contains( $content, '<!-- wp:' ) ) return false;
    return ! preg_match( MOMENTIVE_RP_TRIVIAL_EMPTY, trim( $content ) );
}

/**
 * Derives the legacy-site URL for a post by swapping the local hostname for
 * the production domain. Works for any post type because get_permalink()
 * already knows each CPT's rewrite rules.
 */
function momentive_rp_legacy_url( int $post_id ): string {
    return str_replace( home_url(), MOMENTIVE_RP_LEGACY_DOMAIN, (string) get_permalink( $post_id ) );
}

// ── Widget output ─────────────────────────────────────────────────────────────

function momentive_rp_widget_cb(): void {
    $data        = momentive_rp_get_data();
    $refresh_url = wp_nonce_url( admin_url( '?msw_rp_refresh=1' ), 'msw_rp_refresh' );

    // Totals — alias posts are excluded from both built and total so they don't
    // skew the overall percentage.
    $grand_total    = 0;
    $grand_built    = 0;
    $grand_alias    = 0;
    $grand_unrebuilt = 0;
    foreach ( $data as $row ) {
        $trackable       = $row['total'] - $row['alias'];
        $grand_total    += $trackable;
        $grand_built    += $row['built'];
        $grand_alias    += $row['alias'];
        $grand_unrebuilt += count( $row['unrebuilt'] );
    }
    $pct_all = $grand_total ? (int) round( ( $grand_built / $grand_total ) * 100 ) : 0;
    ?>
    <style>
    .msw-rp-bar-wrap{background:#e0e0e0;border-radius:3px;height:8px;overflow:hidden}
    .msw-rp-bar{height:8px;border-radius:3px;transition:width .4s}
    .msw-rp-bar.ok{background:#00a32a}
    .msw-rp-bar.partial{background:#2271b1}
    .msw-rp-header{display:flex;align-items:center;gap:12px;margin-bottom:10px}
    .msw-rp-header .msw-rp-bar-wrap{flex:1;margin:0}
    .msw-rp-header .msw-rp-pct{font-size:13px;font-weight:600;white-space:nowrap}
    .msw-rp-refresh-link{font-size:11px;color:#888;white-space:nowrap}
    .msw-rp-table{margin-top:12px;font-size:13px}
    .msw-rp-table td,.msw-rp-table th{vertical-align:middle!important;padding:5px 8px!important}
    .msw-rp-table .col-bar{width:90px}
    .msw-rp-table .col-num{text-align:center!important;width:56px}
    .msw-rp-table .ok-check{color:#00a32a;font-weight:700}
    .msw-rp-toggle{cursor:pointer;background:none;border:none;padding:0;color:#2271b1;text-decoration:underline;font-size:13px}
    .msw-rp-alias{color:#888;font-size:12px}
    .msw-rp-details{background:#f6f6f6!important}
    .msw-rp-details td{padding:6px 12px 8px!important}
    .msw-rp-details ul{margin:4px 0;padding-left:18px}
    .msw-rp-details li{margin:3px 0;font-size:12px}
    .msw-rp-legacy{margin-left:6px;font-size:11px;color:#888}
    </style>

    <div class="msw-rp-header">
        <div class="msw-rp-bar-wrap">
            <div class="msw-rp-bar <?php echo $pct_all >= 100 ? 'ok' : 'partial'; ?>"
                 style="width:<?php echo $pct_all; ?>%"></div>
        </div>
        <span class="msw-rp-pct"><?php echo $grand_built; ?> / <?php echo $grand_total; ?> rebuilt (<?php echo $pct_all; ?>%)</span>
        <a href="<?php echo esc_url( $refresh_url ); ?>" class="msw-rp-refresh-link">Refresh</a>
    </div>

    <table class="widefat striped msw-rp-table">
        <thead>
            <tr>
                <th>Post type</th>
                <th class="col-num">Built</th>
                <th class="col-num">Empty</th>
                <th class="col-num">Total</th>
                <th class="col-bar">Progress</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $data as $cpt => $row ) :
            $trackable = $row['total'] - $row['alias'];
            $empty     = $trackable - $row['built'];
            $pct       = $trackable ? (int) round( ( $row['built'] / $trackable ) * 100 ) : 0;
            $uid       = 'msw-rp-' . sanitize_key( $cpt );
        ?>
            <tr>
                <td>
                    <strong><?php echo esc_html( $row['label'] ); ?></strong>
                    <small style="color:#999;display:block"><?php echo esc_html( $cpt ); ?></small>
                </td>
                <td class="col-num"><?php echo (int) $row['built']; ?></td>
                <td class="col-num">
                    <?php if ( $empty > 0 ) : ?>
                        <button type="button" class="msw-rp-toggle" data-target="<?php echo esc_attr( $uid ); ?>">
                            <?php echo (int) $empty; ?>
                        </button>
                    <?php else : ?>
                        <span class="ok-check">✓</span>
                    <?php endif; ?>
                    <?php if ( $row['alias'] > 0 ) : ?>
                        <span class="msw-rp-alias" title="Product posts aliased via redirect_to_solution — no content needed">
                            +<?php echo (int) $row['alias']; ?> alias
                        </span>
                    <?php endif; ?>
                </td>
                <td class="col-num"><?php echo (int) $trackable; ?></td>
                <td class="col-bar">
                    <div class="msw-rp-bar-wrap">
                        <div class="msw-rp-bar <?php echo $pct >= 100 ? 'ok' : 'partial'; ?>"
                             style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <span style="font-size:11px;color:#777"><?php echo $pct; ?>%</span>
                </td>
            </tr>

            <?php if ( ! empty( $row['unrebuilt'] ) ) : ?>
            <tr class="msw-rp-details" id="<?php echo esc_attr( $uid ); ?>" style="display:none">
                <td colspan="5">
                    <ul>
                    <?php foreach ( $row['unrebuilt'] as $p ) : ?>
                        <li>
                            <a href="<?php echo esc_url( $p['edit_link'] ); ?>">
                                <?php echo esc_html( $p['title'] ); ?>
                            </a>
                            <a href="<?php echo esc_url( $p['legacy_url'] ); ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="msw-rp-legacy">↗ legacy</a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
            <?php endif; ?>

        <?php endforeach; ?>
        </tbody>
    </table>

    <script>
    (function(){
        document.querySelectorAll('.msw-rp-toggle').forEach(function(btn){
            btn.addEventListener('click', function(){
                var row = document.getElementById(this.dataset.target);
                if (!row) return;
                var open = row.style.display !== 'none';
                row.style.display = open ? 'none' : '';
                this.style.fontWeight = open ? '' : '600';
            });
        });
    })();
    </script>
    <?php
}
