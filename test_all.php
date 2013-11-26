<?

require_once 'ZonesManager.php';

ob_start();

// invoke all tests in TESTS folder
$test_files = glob( dirname( __FILE__ ) . '/tests/*.php' );
foreach( $test_files as $fn )
    if( $fn[0] !== '_' )
    {
        require $fn;
        // on eny error, it's printed and buffered
    }

$errors = ob_get_clean();
if( $errors )
    echo "<h3 style='color:red'>Some of tests failed</h3>" . $errors;
else
    echo "<p style='color:green'>All tests passed</p>";