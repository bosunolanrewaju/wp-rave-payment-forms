<?php
/**
 * Flutterwave Payments Integrations Page
 *
 * @package Flutterwave\Payments\Views
 * @version 1.0.6
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap">
	<div class="flw__integration">
		<h1 class="flw-heading"> Integrations and Connected Apps. </h1>
		<h2 class="flw-heading"> Connected Integrations </h2>
		<div id="integration-list">
			<p class="no-integrations">No Connections Yet.</p>
			<p class="connection-suggestion">Connections can help supercharge your payment and donation forms.</p>
		</div>
		<div><h2>All integrations </h2>
			<?php
			foreach ( $integrations as $owner => $services ) {
				echo "<div class='flw_thirdparty_service'><h3>" . esc_attr( ucfirst( $owner ) ) . '</h3>';
				foreach ( $services as $service ) {
					$name = $service->get_info()['name'];
					echo "<div class='flw-service-info'>";
					echo "<div class='flw-service-name'>" . esc_attr( $service->get_info()['name'] ) . '</div>';
					echo "<div class='flw-service-desc'>" . 'Trusted by over 1 million developers for secure and scalable REST APIs.
                All APIs have a free plan . No credit card required.' . '</div>';
					echo '<button>' . esc_attr__( 'view integration', 'rave-payment-forms' ) . '</button>';
					echo '</div>';
				}
				echo '</div>';
			}
			?>
			<div><div></div>
