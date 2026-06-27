<?php
$is_preview = $is_preview ?? false;

$source = get_field( 'form_source' ) ?: 'block'; // 'block' | 'post'

if ( 'post' === $source ) {
	$post_id    = get_the_ID();
	$embed_code = $post_id ? momentive_resolve_webinar_form( $post_id ) : '';
} else {
	$embed_code = get_field( 'hubspot_embed_code' );
}

$two_step   = get_field( 'two_step' );

// Extract the HubSpot portal/form IDs from the embed code so JS can call
// hbspt.forms.create() directly rather than re-parsing the script tag.
$portal_id = '';
$form_id   = '';

if ( $embed_code ) {
	preg_match( '/portalId:\s*["\']?(\d+)["\']?/', $embed_code, $m );
	$portal_id = $m[1] ?? '';
	preg_match( '/formId:\s*["\']?([\w-]+)["\']?/', $embed_code, $m );
	$form_id = $m[1] ?? '';
}

$wrapper_attrs = get_block_wrapper_attributes( [
	'data-two-step'  => $two_step ? 'true' : 'false',
	'data-portal-id' => esc_attr( $portal_id ),
	'data-form-id'   => esc_attr( $form_id ),
] );

?>
<div <?php echo $wrapper_attrs; ?>>

	<?php if ( $is_preview ) : ?>

		<div style="padding: 1rem; border: 1px dashed #ccc; text-align: center; color: #666;">
			<strong>HubSpot Form</strong><?php if ( $two_step ) : ?> &mdash; Two-step mode<?php endif; ?><br>
			<?= $embed_code ? 'Embed code set.' : 'No embed code — edit block to add.'; ?>
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

		echo $embed_code;
		
	else : ?>

		<p class="hubspot-form__placeholder">No embed code set.</p>

	<?php endif; ?>

</div>
