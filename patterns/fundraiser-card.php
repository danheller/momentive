<?php
/**
 * Template Part: Fundraiser Card
 *
 * Renders a single fundraiser post as a card. Expected to run inside a
 * WP_Query loop (the_post() already called). Distinct from story-card.php:
 *
 *  - No permalink — the whole card links to the external `campaign_link` ACF field.
 *  - Organization type badge overlaid on the featured image (dark pill, top-left).
 *  - Fundraising feature pills below the image.
 *  - Organization name displayed as a sub-heading below the event title.
 *  - No date, no "Read more" link.
 *
 * @package Momentive
 */

$campaign_link     = (string) get_field( 'campaign_link' );
$organization_name = (string) get_field( 'organization_name' );
$title             = get_the_title();

// Organization type — show the first term as the badge.
$org_types = get_the_terms( get_the_ID(), 'organization_type' );
$org_badge = ( ! empty( $org_types ) && ! is_wp_error( $org_types ) )
	? $org_types[0]->name
	: '';

// Fundraising features — shown as pills below the image.
$features = get_the_terms( get_the_ID(), 'fundraising_features' );
if ( is_wp_error( $features ) ) {
	$features = [];
}

// Link target: external campaign URL, falls back to the post permalink (e.g. admin preview).
$card_href = $campaign_link ?: get_permalink();
$is_external = ! empty( $campaign_link );
?>
<div class="story-card fundraiser-card">

	<?php // ── Featured image with org-type badge overlay ─────────────────── ?>

	<figure class="fundraiser-card__image" style="aspect-ratio:16/9;position:relative;">
		<a href="<?php echo esc_url( $card_href ); ?>"
		   tabindex="-1"
		   aria-hidden="true"
		   <?php echo $is_external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
		>
			<?php if ( has_post_thumbnail() ) :
				the_post_thumbnail( 'large', [
					'style' => 'width:100%;height:100%;object-fit:cover;display:block;',
					'alt'   => '', // decorative — card heading link is the accessible label
				] );
			endif; ?>
		</a>

		<?php if ( $org_badge ) : ?>
		<span class="fundraiser-card__org-badge">
			<?php echo esc_html( $org_badge ); ?>
		</span>
		<?php endif; ?>
	</figure>

	<?php // ── Feature pills ──────────────────────────────────────────────── ?>

	<?php if ( ! empty( $features ) ) : ?>
	<div class="fundraiser-card__features">
		<?php foreach ( $features as $feature ) : ?>
		<span class="fundraiser-card__feature-pill">
			<?php echo esc_html( $feature->name ); ?>
		</span>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php // ── Card body ──────────────────────────────────────────────────── ?>

	<div class="story-content">

		<h3 class="wp-block-post-title">
			<a href="<?php echo esc_url( $card_href ); ?>"
			   <?php echo $is_external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
			>
				<?php echo esc_html( $title ); ?>
			</a>
		</h3>

		<?php if ( $organization_name ) : ?>
		<p class="fundraiser-card__org-name">
			<?php echo esc_html( $organization_name ); ?>
		</p>
		<?php endif; ?>

		<div class="wp-block-post-excerpt">
			<p><?php echo wp_trim_words( get_the_excerpt(), 20, '…' ); ?></p>
		</div>

	</div>

</div>
