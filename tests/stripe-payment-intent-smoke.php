<?php
/**
 * Smoke: Stripe session scalar extraction handles expanded arrays/objects
 * without array-to-string warnings.
 *
 * @package PCKZCanonicalEngine
 */

require_once dirname( __DIR__ ) . '/tests/smoke-bootstrap.php';

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pckz-payments.php';

$provider_class = new ReflectionClass( 'PCKZ_Payment_Provider_Stripe' );
$provider       = $provider_class->newInstanceWithoutConstructor();
$extract        = $provider_class->getMethod( 'session_scalar_id' );
$extract->setAccessible( true );

$cases = array(
	array( 'input' => 'pi_123', 'expected' => 'pi_123' ),
	array( 'input' => array( 'id' => 'pi_456' ), 'expected' => 'pi_456' ),
	array( 'input' => (object) array( 'id' => 'pi_789' ), 'expected' => 'pi_789' ),
	array( 'input' => array( 'id' => array( 'bad' => 'value' ) ), 'expected' => '' ),
	array( 'input' => array( 'object' => 'payment_intent' ), 'expected' => '' ),
);

foreach ( $cases as $idx => $case ) {
	$out = (string) $extract->invoke( $provider, $case['input'], 'id' );
	if ( $out !== $case['expected'] ) {
		fwrite( STDERR, "Case {$idx} failed: expected '{$case['expected']}', got '{$out}'\n" );
		exit( 1 );
	}
}

echo "stripe-payment-intent-smoke: OK\n";
exit( 0 );
