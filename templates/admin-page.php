<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes its own output.
echo aosai_render_brand_preloader( 'aosai-admin-preloader', __( 'Loading Agency OS AI...', 'agency-os-ai' ) );
?>
<div id="aosai-admin-root" class="aosai-app-container"></div>
<noscript>
    <p><?php esc_html_e( 'Agency OS AI requires JavaScript to be enabled.', 'agency-os-ai' ); ?></p>
</noscript>
