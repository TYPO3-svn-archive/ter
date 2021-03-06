<?php
/**
 * Autoloader definition for the Execution component.
 *
 * @copyright Copyright (C) 2005, 2006 eZ systems as. All rights reserved.
 * @license http://ez.no/licenses/new_bsd New BSD License
 * @version 1.0.1
 * @filesource
 * @package Execution
 */

return array(
    'ezcExecution'                            => 'Execution/execution.php',
    'ezcExecutionErrorHandler'                => 'Execution/interfaces/execution_handler.php',
    'ezcExecutionBasicErrorHandler'           => 'Execution/handlers/basic_handler.php',

    'ezcExecutionException'                   => 'Execution/exceptions/exception.php',
    'ezcExecutionAlreadyInitializedException' => 'Execution/exceptions/already_initialized.php',
    'ezcExecutionInvalidCallbackException'    => 'Execution/exceptions/invalid_callback.php',
    'ezcExecutionNotInitializedException'     => 'Execution/exceptions/not_initialized.php',
    'ezcExecutionWrongClassException'         => 'Execution/exceptions/wrong_class.php',
);

?>
