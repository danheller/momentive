<?php
$embed_code = get_field( 'hubspot_embed_code' );
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
	<?php if ( $is_preview ) : ?>
		<div style="padding: 1rem; border: 1px dashed #ccc; text-align: center; color: #666;">
			<strong>HubSpot Form</strong><br>
			<?= $embed_code ? 'Embed code set.' : 'No embed code — edit block to add.'; ?>
		</div>
	<?php elseif ( $embed_code ) : ?>
		<?php echo $embed_code; ?>
	<?php else : ?>
		<p class="hubspot-form__placeholder">No embed code set.</p>
	<?php endif; ?>
</div>