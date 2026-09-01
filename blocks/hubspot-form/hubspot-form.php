<?php
$is_preview = $is_preview ?? false;

// Resolution order (linked-products pattern):
//   1. Block-level embed code — explicit override, used as-is.
//   2. Post-level form fields — form_upcoming / form_ondemand resolved by
//      momentive_webinar_status(), so the correct form surfaces automatically
//      when the webinar transitions from upcoming to on-demand.
// The legacy form_source select field is no longer consulted; it remains in
// the UI as a no-op for blocks that have it stored in their serialized data.
$embed_code = (string) get_field( 'hubspot_embed_code' );

if ( ! $embed_code ) {
	$post_id    = $post_id ?? get_the_ID();
	$embed_code = $post_id ? (string) momentive_resolve_webinar_form( $post_id ) : '';
}

// Strip any <br> tags that ACF's textarea new_lines setting may have injected
// into the embed code — they break the JavaScript.
$embed_code = $embed_code ? preg_replace( '/<br\s*\/?>/', "\n", $embed_code ) : $embed_code;

// Auto-inject the HubSpot loader script when the embed code contains only
// the hbspt.forms.create() call and the library <script> tag was omitted
// (a common copy-paste gap in HubSpot's own UI).
if ( $embed_code
	&& str_contains( $embed_code, 'hbspt.forms.create' )
	&& ! str_contains( $embed_code, 'js.hsforms.net' ) ) {
	$embed_code = '<script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>' . "\n" . $embed_code;
}

$two_step     = get_field( 'two_step' );
$button_modal = get_field( 'button_modal' );
$button_text  = trim( (string) get_field( 'button_text' ) );
if ( ! $button_text ) {
	$button_text = __( 'Watch this demo on-demand', 'momentive' );
}

// When enabled, the block renders a clean hbspt.forms.create() call with the
// standard thank-you redirect injected, rather than echoing the raw embed code.
// Default off so existing blocks with redirect logic already in their embed code
// are unaffected until an editor explicitly opts in and cleans the embed.
$redirect_to_thank_you = (bool) get_field( 'redirect_to_thank_you' );

// Extract HubSpot parameters from the embed code. All four values are preserved
// in a clean embed (portalId, formId, region, sfdcCampaignId) and parsed here
// so the render template can reconstruct the create() call without the raw JS.
$portal_id        = '';
$form_id          = '';
$region           = 'na1';
$sfdc_campaign_id = '';

if ( $embed_code ) {
	preg_match( '/portalId:\s*["\']?(\d+)["\']?/', $embed_code, $m );
	$portal_id = $m[1] ?? '';

	preg_match( '/formId:\s*["\']?([\w-]+)["\']?/', $embed_code, $m );
	$form_id = $m[1] ?? '';

	preg_match( '/region:\s*["\']([^"\']+)["\']/', $embed_code, $m );
	$region = $m[1] ?? 'na1';

	preg_match( '/sfdcCampaignId:\s*["\']([^"\']+)["\']/', $embed_code, $m );
	$sfdc_campaign_id = $m[1] ?? '';
}

$wrapper_attrs = get_block_wrapper_attributes( [
	'data-two-step'              => $two_step ? 'true' : 'false',
	'data-button-modal'          => $button_modal ? 'true' : 'false',
	'data-portal-id'             => esc_attr( $portal_id ),
	'data-form-id'               => esc_attr( $form_id ),
	'data-redirect-to-thank-you' => $redirect_to_thank_you ? 'true' : 'false',
] );

?>
<div <?php echo $wrapper_attrs; ?>>

	<?php if ( $is_preview ) : ?>

		<div class="momentive-block-placeholder">
			<strong>HubSpot Form<?php
				if ( $button_modal ) : ?> — Button Modal<?php
				elseif ( $two_step ) : ?> — Two-step<?php
				endif;
			?></strong>
			<p><?php echo $embed_code ? 'Embed code set.' : 'No embed code — edit block to add.'; ?></p>
		</div>

	<?php elseif ( $button_modal && $portal_id && $form_id ) : ?>

		<?php $uid = 'hs-modal-' . uniqid(); ?>

		<!-- Button-only modal trigger -->
		<button
			class="hubspot-form__modal-btn wp-block-button__link"
			type="button"
		>
			<?php echo esc_html( $button_text ); ?>
		</button>

		<!-- Modal containing the full HubSpot form -->
		<div
			id="<?php echo esc_attr( $uid ); ?>"
			class="hubspot-form__modal"
			role="dialog"
			aria-modal="true"
			aria-label="<?php echo esc_attr( $button_text ); ?>"
			hidden
		>
			<div class="hubspot-form__modal-panel">
				<button class="hubspot-form__modal-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'momentive' ); ?>">
					&times;
				</button>
				<div class="hubspot-form__modal-body"></div>
			</div>
		</div>

	<?php elseif ( $two_step && $portal_id && $form_id ) : ?>

		<?php
		// Unique ID so multiple two-step blocks on one page don't collide.
		$uid = 'hs-modal-' . uniqid();
		?>

		<!-- Step 1: inline email capture row -->
		<div class="hubspot-form__capture" aria-label="Request a demo">
			<label for="<?php echo esc_attr( $uid ); ?>-email" class="screen-reader-text">
				<?php esc_html_e( 'Email address', 'momentive' ); ?>
			</label>
			<input
				id="<?php echo esc_attr( $uid ); ?>-email"
				class="hubspot-form__email-input"
				type="email"
				placeholder="<?php esc_attr_e( 'Enter your email', 'momentive' ); ?>"
				autocomplete="email"
				data-modal-target="#<?php echo esc_attr( $uid ); ?>"
			/>
			<button
				class="hubspot-form__submit wp-block-button__link"
				type="button"
				data-modal-target="#<?php echo esc_attr( $uid ); ?>"
			>
				<?php esc_html_e( 'Request a Demo', 'momentive' ); ?>
			</button>
		</div>

		<!-- Step 2: modal containing the full HubSpot form -->
		<div
			id="<?php echo esc_attr( $uid ); ?>"
			class="hubspot-form__modal"
			role="dialog"
			aria-modal="true"
			aria-label="<?php esc_attr_e( 'Request a Demo', 'momentive' ); ?>"
			hidden
		>
			<div class="hubspot-form__modal-panel">
				<button class="hubspot-form__modal-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'momentive' ); ?>">
					&times;
				</button>
				<!-- JS renders the HubSpot form into this target div -->
				<div class="hubspot-form__modal-body"></div>
			</div>
		</div>

	<?php elseif ( $embed_code ) :

		if ( $redirect_to_thank_you && $portal_id && $form_id ) :
			// Render a clean hbspt.forms.create() call with the standard redirect
			// behaviour injected server-side. The pasted embed code only needs to
			// carry portalId, formId, region, and sfdcCampaignId — the onFormSubmit
			// callback and inlineMessage are added here so they never have to live
			// in the textarea or be updated per-form when the destination changes.
			// Uses a relative URL so the redirect works in any environment (local,
			// staging, production) without modification.
?>
<script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>
<script>
hbspt.forms.create({
	portalId:      <?php echo json_encode( $portal_id ); ?>,
	formId:        <?php echo json_encode( $form_id ); ?>,
	region:        <?php echo json_encode( $region ); ?>,<?php if ( $sfdc_campaign_id ) : ?>
	sfdcCampaignId: <?php echo json_encode( $sfdc_campaign_id ); ?>,<?php endif; ?>
	inlineMessage: 'Thank you for contacting us. Your scheduler is loading.',
	onFormSubmit: function( $form ) {
		setTimeout( function() {
			window.location = '/demo/thank-you/?' + $form.serialize();
		}, 250 );
	}
});
</script>
<?php
		else :
			echo $embed_code;
		endif;

	else : ?>

		<p class="hubspot-form__placeholder">No embed code set.</p>

	<?php endif; ?>

</div>
